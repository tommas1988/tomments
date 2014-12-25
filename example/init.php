<?php
define('APP_PATH', __DIR__ . '/');

$config = include APP_PATH . 'config/config.php';

require APP_PATH . 'tomments/Comment.php';
require APP_PATH . 'tomments/CommentMapper.php';

TUtils\Profiler::setOptions(array(
    'xhprof_path' => LIB_PATH . 'vendors/xhprof',
    'rand_prof'   => true,
));

$commentManager = new \Tomments\CommentManager($config);
