<?php
namespace Tomments\Comment;

use InvalidArgumentException;

abstract class AbstractComment implements CommentInterface
{
    /**
     * The comment key
     * @var int
     */
    protected $key;

    /**
     * The comment level
     * @var int
     */
    protected $level;

    /**
     * The parent comment key when the comment is child
     * @var int
     */
    protected $parentKey;

    /**
     * The origin comment key when the comment is child
     * @var int
     */
    protected $originKey;

    /**
     * Whether the comment is child
     * @var bool
     */
    protected $isChild = false;

    /**
     * @see    CommentInterface::setKey
     * @throws InvalidArgumentException If key is not integer
     */
    public function setKey($key)
    {
        if (!ctype_digit($key)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key: %s', $key));
        }

        $this->key = (int) $key;
        return $this;
    }

    /**
     * @see CommentInterface::getKey
     */
    public function getKey()
    {
        return $this->getParam('key');
    }

    /**
     * @see    CommentInterface::setLevel
     * @throws InvalidArgumentException If level is not integer
     */
    public function setLevel($level)
    {
        if (!ctype_digit($level)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid level: %', $level));
        }

        $this->level = (int) $level;
        if (!$this->isChild) {
            $this->markChildFlag();
        }

        return $this;
    }

    /**
     * @see CommentInterface::getLevel
     */
    public function getLevel()
    {
        return $this->getParam('level', 0);
    }

    /**
     * @see    CommentInterface::setParentkey
     * @throws InvalidArgumentException If parent key is not integer
     */
    public function setParentKey($parentKey)
    {
        if (!ctype_digit($parentKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid parent key: %s', $parentKey));
        }

        $this->parentKey = (int) $parentKey;
        if (!$this->isChild) {
            $this->markChildFlag();
        }

        return $this;
    }

    /**
     * @see CommentInterface::getParentkey
     */
    public function getParentKey()
    {
        return $this->getParam('parentKey');
    }

    /**
     * @see    CommentInterface::setOriginkey
     * @throws InvalidArgumentException If origin key is not integer
     */
    public function setOriginKey($originKey)
    {
        if (!ctype_digit($originKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid origin key: %s', $originKey));
        }

        $this->originKey = (int) $originKey;
        if (!$this->isChild) {
            $this->markChildFlag();
        }

        return $this;
    }

    /**
     * @see CommentInterface::getOriginkey
     */
    public function getOriginKey()
    {
        return $this->getParam('originKey');
    }

    /**
     * @see CommentInterface::isChild
     */
    public function isChild()
    {
        return $this->isChild;
    }

    /**
     * @see CommentInterface::markChildFlag
     */
    public function markChildFlag()
    {
        $this->isChild = true;
        return $this;
    }

    /**
     * Get param
     *
     * @param  string name Param name
     * @param  mixed default Default value if param is not set
     * @return mixed
     */
    protected function getParam($name, $default = false)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return $default;
    }
}
