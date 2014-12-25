<?php
use Tomments\DataMapper\AbstractCommentMapper;

class CommentMapper extends AbstractCommentMapper
{
    /**
     * Custom column infos
     * @var array
     */
    protected $cusColInfos = array(
        'content' => array(
            'field'      => 'content',
            'quote-type' => \PDO::PARAM_STR,
        ),
        'time' => array(
            'field'      => 'time',
            'quote-type' => \PDO::PARAM_STR,
        ),
    );

    /**
     * Updatable columns
     * @var array
     */
    protected $updatableColumns = array(
        'content', 'time');
}
