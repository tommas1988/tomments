<?php
namespace Tomments\Comment;

interface CommentInterface
{
    /**
     * Load comment
     *
     * @param  array data
     * @return self
     */
    public function load(array $data);

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
     * Set the origin comment key
     *
     * The offset of origin comment is 0.
     *
     * @param  int originKey
     * @return self
     */
    public function setOriginKey($originKey);

    /**
     * Get the origin comment key
     *
     * @return int
     */
    public function getOriginKey();

    /**
     * Set the level of the sorted comment rows
     *
     * @param  int offset
     * @return self
     */
    public function setLevel($level);

    /**
     * Get the level of sorted comment rows
     *
     * @return int
     */
    public function getLevel();
}
