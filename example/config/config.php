<?php
$localConfig = include APP_PATH . 'config/local.php';

if (!isset($localConfig['lib_path'], $localConfig['db'])) {
    throw new Exception('local config file must contain tomments lib path and db access info');
}

define('LIB_PATH', $localConfig['lib_path']);
require LIB_PATH . 'autoload/autoloader.php';

return array(
    'comment'  => 'Comment',
    'mapper'   => array(
        'class'  => 'CommentMapper',
        'config' => array(
            'db'            => $localConfig['db'],
            'organize-type' => \Tomments\DataMapper\AbstractCommentMapper::ORG_BY_TARGET_ID,
            'target-column' => 'target_id',
        ),
    ),
);
