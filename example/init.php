<?php
define('LIB_PATH', __DIR__ . '/../');

require LIB_PATH . 'autoload/autolode.php';
require LIB_PATH . 'example/tomments/Comment.php';
require LIB_PATH . 'example/tomments/CommentMapper.php';

$config = include LIB_PATH . 'example/config/config.php';
$commentManager = new \Tomments\CommentManager($config);
