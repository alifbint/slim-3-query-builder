<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// database driver
$container['db'] = function ($c) {
	$settings = $c->get('settings')['db'];
    $server = $settings['driver'].":host=".$settings['host'].";dbname=".$settings['database'].";charset=".$settings['charset'];
    $conn = new PDO($server, $settings['username'], $settings['password']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES ".$settings['charset']." COLLATE ".$settings['collation']);
    return $conn;
};

// model
$container['model'] = function ($c) {
	return new \App\Lib\Model($c);
};
