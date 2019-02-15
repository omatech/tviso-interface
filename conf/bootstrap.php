<?php
//à

$config = new \Doctrine\DBAL\Configuration();
//..
$connectionParams = array(
    'dbname' => DBNAME,
    'user' => DBUSER,
    'password' => DBPASS,
    'host' => DBHOST,
    'driver' => 'pdo_mysql',
		'charset' => 'utf8mb4'
);
$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);


