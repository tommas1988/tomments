<?php
use Tomments\DataMapper\AbstractCommentMapper;

class CommentMapper extends AbstractCommentMapper
{
    /**
     * Customize defined fields columns mapper
     * @var array
     */
    protected $columnMapper = array(
        'content' => 'content',
        'time'    => 'time',
    );

    /**
     * Updatable fields columns mapper
     * @var array
     */
    protected $updatableColumnMapper = array(
        'content' => 'content',
        'time'    => 'time',
    );
}
