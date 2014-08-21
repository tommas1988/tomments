<?php
define('LIB_PATH', __DIR__ . '/../');

require LIB_PATH . 'autoload/autolode.php';
require LIB_PATH . 'example/tomments/Comment.php';
require LIB_PATH . 'example/tomments/CommentMapper.php';

$dbConfig = include 'config/db_config.php';
$dsn      = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}";
$options = array(
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
);

$pdo    = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
$mapper = new CommentMapper($pdo);

$commentManager = new \Tomments\CommentManager($mapper, new Comment());
