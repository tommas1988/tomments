<?php
namespace Tomments\Comment;

interface CommentInterface
{
    /**
     * Load comment params
     *
     * @param  array params
     * @return self
     */
    public function load(array $params);

    /**
     * Whether the comment is child
     *
     * @return bool
     */
    public function isChild();

    /**
     * Mark child comment
     *
     * @return self
     */
    public function markChildFlag();

    /**
     * Set the comment key
     *
     * @param  int key
     * @return self
     */
    public function setKey($key);

    /**
     * Get the comment key
     *
     * @return int
     */
    public function getKey();

    /**
     * Set the parent comment key
     *
     * @param  int parentKey
     * @return self
     */
    public function setParentKey($parentKey);

    /**
     * Get the parent comment key
     *
     * @return int
     */
    public function getParentKey();

    /**
     * Add a child comment
     *
     * @param  CommentInterface comment
     * @return self
     */
    public function addChild(CommentInterface $comment);

    /**
     * Whether has children comments
     *
     * @return bool
     */
    public function hasChildren();

    /**
     * Get children comments
     *
     * @return CommentInterface[]
     */
    public function getChildren();
}
