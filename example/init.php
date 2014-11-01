<?php
define('LIB_PATH', __DIR__ . '/../');

require LIB_PATH . 'autoload/autoloader.php';
require LIB_PATH . 'example/tomments/Comment.php';
require LIB_PATH . 'example/tomments/CommentMapper.php';
require LIB_PATH . 'example/lib/TUtils/Profiler.php';

TUtils\Profiler::setOptions(array(
    'xhprof_path' => LIB_PATH . 'example/lib/xhprof',
    'rand_prof'   => true,
));

$config = include LIB_PATH . 'example/config/config.php';
$commentManager = new \Tomments\CommentManager($config);
