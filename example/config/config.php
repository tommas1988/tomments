<?php
return array(
    'comment' => 'Comment',
    'mapper'  => array(
        'class'  => 'CommentMapper',
        'config' => array(
            'db'            => include __DIR__ . '/db_config.php',
            'organize-type' => \Tomments\DataMapper\AbstractCommentMapper::ORG_BY_TARGET_ID,
            'target-column' => 'target_id',
        ),
    ),
);
