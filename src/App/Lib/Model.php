<?php
namespace App\Lib;

use Interop\Container\ContainerInterface;

class Model
{
	protected $db;
	private $selectFields = '*';
	private $where = array();
	private $whereArr = array();
	private $join = array();
	private $fromTable;
	private $groupBy = array();
	private $orderBy = array();
	private $limit;
	private $offset;
	private $statement;

	public function __construct(ContainerInterface $ci)
	{
		$this->db = $ci->db;
	}

	public function num_rows()
	{
		return $this->statement->rowCount();
	}

	public function affected_rows()
	{
		return $this->statement->rowCount();
	}

	public function insert_id()
	{
		return $this->db->lastInsertId();
	}

	public function select($select = array())
	{
		$this->selectFields = $select;
		return $this;
	}

	public function from($table)
	{
		$this->fromTable = $table;
		return $this;
	}

	public function join($table, $on, $type = null)
	{
		$this->join[] = array($table, $on, $type);
		return $this;
	}

	public function where($column, $condition = null)
	{
		if(!is_array($column)){
			$this->where[] = array($column, $condition, 'AND', 'normal');
		}
		else{
			foreach($column as $key => $val){
				$this->where[] = array($key, $val, 'AND', 'normal');
			}
		}
		return $this;
	}

	public function or_where($column, $condition = null)
	{
		if(!is_array($column)){
			$this->where[] = array($column, $condition, 'OR', 'normal');
		}
		else{
			foreach($column as $key => $val){
				$this->where[] = array($key, $val, 'OR', 'normal');
			}
		}
		return $this;
	}

	public function where_like($column, $condition)
	{
		if(!is_array($column)){
			$this->where[] = array($column, $condition, 'AND', 'like');
		}
		else{
			foreach($column as $key => $val){
				$this->where[] = array($key, $val, 'AND', 'like');
			}
		}
		return $this;
	}

	public function or_where_like($column, $condition)
	{
		if(!is_array($column)){
			$this->where[] = array($column, $condition, 'OR', 'like');
		}
		else{
			foreach($column as $key => $val){
				$this->where[] = array($key, $val, 'OR', 'like');
			}
		}
		return $this;
	}

	public function where_in($column, $items)
	{
		$this->where[] = array($column, sprintf('(%s)', implode(', ', $items)), 'AND', 'in');
		return $this;
	}

	public function where_not_in($column, $items)
	{
		$this->where[] = array($column, sprintf('(%s)', implode(', ', $items)), 'AND', 'not_in');
		return $this;
	}

	public function where_between($column, $a, $b)
	{
		$this->where[] = array($column, $a.' AND '.$b, 'AND', 'between');
		return $this;
	}

	public function or_where_between($column, $a, $b)
	{
		$this->where[] = array($column, $a.' AND '.$b, 'OR', 'between');
		return $this;
	}

	public function where_not_between($column, $a, $b)
	{
		$this->where[] = array($column, $a.' AND '.$b, 'AND', 'not_between');
		return $this;
	}

	public function or_where_not_between($column, $a, $b)
	{
		$this->where[] = array($column, $a.' AND '.$b, 'OR', 'not_between');
		return $this;
	}

	public function group_by($column)
	{
		$this->groupBy[] = $column;
		return $this;
	}

	public function order_by($order, $by)
	{
		$this->orderBy[] = array($order, strtoupper($by));
		return $this;
	}

	private function buildSelectClause()
    {
    	if($this->selectFields == '*')
    		return sprintf('SELECT %s', $this->selectFields);
		return sprintf('SELECT %s', implode(', ', $this->selectFields));
    }

    private function appendFromClause($sql)
    {
    	return sprintf('%s FROM %s', $sql, $this->fromTable);
    }

    private function appendJoinClause($sql)
    {
    	$join = sprintf('%s', $sql);
    	foreach($this->join as $row){
    		if(empty($row[2])){
    			$join .= sprintf(' JOIN %s ON %s', $row[0], $row[1]);
    		}
    		else{
    			$join .= sprintf(' %s JOIN %s ON %s', strtoupper($row[2]), $row[0], $row[1]);
    		}
    	}
    	return $join;
    }

    private function regexWhereClause($column)
    {
    	$column = strtoupper($column);
    	if(preg_match('/[=><!]=?|( IS (NOT NULL|NOT|NULL)? )|( (NOT )?BETWEEN )/', $column, $output)){
    		return $output[0];
    	}
    	else{
    		return null;
    	}
    }

    private function appendWhereClause($sql)
    {
    	$where = sprintf('%s', $sql);
    	foreach($this->where as $key => $row){
			$operator = $this->regexWhereClause($row[0]);
			if(!empty($operator)){
				$row[0] = trim(str_replace([strtolower($operator), $operator], ['',''], $row[0]));
			}

			switch($row[3]){
				case 'normal':
					if(!empty($row[1])){
						$operator = ((!empty($operator))?$operator:'=');
					}
					else{
						if($operator != 'IS' || $operator != 'IS NOT'){
							$operator = 'IS';
						}
						$row[1] = null;
					}
				break;

				case 'like':
					$operator = 'LIKE';
					$row[1] = sprintf('%%%s%%', $row[1]);
				break;

				case 'in':
					$operator = 'IN';
				break;

				case 'not_in':
					$operator = 'NOT IN';
				break;

				case 'between':
					$operator = 'BETWEEN';
				break;

				case 'not_between':
					$operator = 'NOT BETWEEN';
				break;
			}

    		if($key == 0){
    			$where .= sprintf(' WHERE %s %s %s', $row[0], $operator, sprintf(':c%s', $key));
    		}
    		else{
    			$where .= sprintf(' %s %s %s %s', $row[2], $row[0], $operator, sprintf(':c%s', $key));
    		}

    		$this->whereArr[sprintf(':c%s', $key)] = $row[1];
    	}
    	return $where;
    }

    private function appendGroupByClause($sql)
    {
    	$groupBy = sprintf('%s', $sql);
    	foreach($this->groupBy as $key => $row){
    		if($key == 0){
    			$groupBy .= sprintf(' %s', $row);
    		}
    		else{
    			$groupBy .= sprintf(', %s', $row);
    		}
    	}
    	return $groupBy;
    }

    private function appendOrderByClause($sql)
    {
    	$orderBy = sprintf('%s', $sql);
    	foreach($this->orderBy as $key => $row){
    		if($key == 0){
    			$orderBy .= sprintf(' %s %s', $row[0], $row[1]);
    		}
    		else{
    			$orderBy .= sprintf(', %s %s', $row[0], $row[1]);
    		}
    	}
    	return $orderBy;
    }

    private function appendLimitClause($sql)
    {
    	if(!empty($this->offset))
    		return sprintf('%s LIMIT %s, %s', $sql, $this->offset, $this->limit);
    	return sprintf('%s LIMIT %s', $sql, $this->limit);
    }

	public function get($table = null)
	{
		if(!empty($table))
			$this->fromTable = $table;

		$sql = $this->buildSelectClause();
		$sql = $this->appendFromClause($sql);
		
		if(!empty($this->join))
			$sql = $this->appendJoinClause($sql);
		
		if(!empty($this->where))
			$sql = $this->appendWhereClause($sql);

		if(!empty($this->groupBy))
			$sql = $this->appendGroupByClause($sql);

		if(!empty($this->orderBy))
			$sql = $this->appendOrderByClause($sql);

		if(!empty($this->limit))
			$sql = $this->appendLimitClause($sql);

		$stmt = $this->db->prepare($sql);
		if(!empty($this->whereArr)){
			$stmt->execute($this->whereArr);
		}
		else{
			$stmt->execute();
		}

		$this->statement = $stmt;

		return $this;
	}

	public function insert($table, $data)
	{
		$columnIdx = array();
		$dataExec = array();

		foreach($data as $key => $val){
			$columnIdx[] = sprintf(':%s', $key);
			$dataExec[sprintf(':%s', $key)] = $val;
		}

		$sql = sprintf('INSERT INTO %s(%s) VALUES (%s)', $table, implode(', ', array_keys($data)), implode(', ', $columnIdx));
		$stmt = $this->db->prepare($sql);
		$result = $stmt->execute($dataExec);
		$this->statement = $stmt;
		return $result;
	}

	public function insert_batch($table, $data)
	{
		$columnIdx = array();
		$dataExec = array();

		foreach($data as $key => $val){
			$tmpColumnIdx = array();
			$i = 0;
			foreach($val as $row){
				$tmpColumnIdx[] = sprintf(':%s_%s', $key, $i);
				$dataExec[sprintf(':%s_%s', $key, $i)] = $row;
				$i++;
			}
			$columnIdx[] = sprintf('(%s)', implode(', ', $tmpColumnIdx));
		}

		$sql = sprintf('INSERT INTO %s(%s) VALUES %s', $table, implode(', ', array_keys($data[0])), implode(', ', $columnIdx));
		$stmt = $this->db->prepare($sql);
		$result = $stmt->execute($dataExec);
		$this->statement = $stmt;
		return $result;
	}

	public function update($table, $data, $condition = array())
	{
		$columnIdx = array();
		$dataExec = array();

		foreach($data as $key => $val){
			$columnIdx[] = sprintf('%s = :%s', $key, $key);
			$dataExec[sprintf(':%s', $key)] = $val;
		}

		$sql = sprintf('UPDATE %s SET %s', $table, implode(', ', $columnIdx));

		if(!empty($condition)){
			foreach($condition as $key => $val){
				$this->where[] = array($key, $val, 'AND', 'normal');
			}
		}

		if(!empty($this->where))
			$sql = $this->appendWhereClause($sql);

		if(!empty($this->whereArr))
			$dataExec = array_merge($dataExec, $this->whereArr);

		$stmt = $this->db->prepare($sql);
		$result = $stmt->execute($dataExec);
		$this->statement = $stmt;
		return $result;
	}

	public function update_batch($table, $data, $column)
	{
		$columns = array_keys($data[0]);
		$dataExec = array();
		$sqlArr = array();
		$sql = sprintf('UPDATE %s SET', $table);

		foreach($columns as $key => $col){
			if($col != $column){
				$tmp = sprintf(' %s = CASE WHEN %s', $col, $column);
				foreach($data as $key2 => $val){
					$tmp = sprintf('%s = :%s_%s THEN %s', $tmp, $column, $key2, $val[$col]);
					if(!array_key_exists(sprintf(':%s_%s', $column, $key2), $dataExec)){
						$dataExec[sprintf(':%s_%s', $column, $key2)] = $val[$column];
					}
				}
				$tmp = sprintf('%s ELSE %s END', $tmp, $col);
				$sqlArr[] = $tmp;
			}
		}
		$sql = sprintf('%s %s WHERE %s IN (%s)', $sql, implode(',', $sqlArr), $column, implode(', ', array_keys($dataExec)));
		$stmt = $this->db->prepare($sql);
		$result = $stmt->execute($dataExec);
		$this->statement = $stmt;
		return $result;
	}

	public function delete($table, $condition = array())
	{
		$sql = sprintf('DELETE FROM %s', $table);

		if(!empty($condition)){
			foreach($condition as $key => $val){
				$this->where[] = array($key, $val, 'AND', 'normal');
			}
		}

		if(!empty($this->where))
			$sql = $this->appendWhereClause($sql);

		$stmt = $this->db->prepare($sql);
		$result = $stmt->execute($this->whereArr);
		$this->statement = $stmt;
		return $result;
	}

	public function row()
	{
		return $this->statement->fetch();
	}

	public function rows()
	{
		return $this->statement->fetchAll();
	}

}
