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
     * The parent comment key when the comment is child
     * @var int
     */
    protected $parentKey;

    /**
     * The comment target id
     * @var int
     */
    protected $targetId;

    /**
     * The comment level
     * @var int
     */
    protected $level;

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
     *
     */
    protected $children = array();

    /**
     * The actual load params method
     *
     * @param  array params
     */
    abstract protected function doLoad(array $params);

    /**
     * @see CommentInterface::load
     */
    public function load(array $params)
    {
        if (isset($params['level'])) {
            $this->setLevel($params['level']);
        }
        if (isset($params['originKey'])) {
            $this->setOriginKey($params['originKey']);
        }
        if (isset($params['targetId'])) {
            $this->setTargetId($params['targetId']);
        }

        $this->doLoad($params);
        return $this;
    }

    /**
     * @see    CommentInterface::setKey
     * @throws InvalidArgumentException If key is not integer
     */
    public function setKey($key)
    {
        $this->key = self::parseInt($key);
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
     * @see    CommentInterface::setParentkey
     * @throws InvalidArgumentException If parent key is not integer
     */
    public function setParentKey($parentKey)
    {
        $this->parentKey = self::parseInt($parentKey);
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
        return $this->getParam('parentKey', null);
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
     * @see CommentInterface::addChild
     */
    public function addChild(CommentInterface $comment)
    {
        $this->children[] = $comment;
        return $this;
    }

    /**
     * @see CommentInterface::hasChildren
     */
    public function hasChildren()
    {
        return !empty($this->children);
    }

    /**
     * @see CommentInterface::getChildren
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set comment target id
     *
     * @param int targetId comment target id
     * @return self
     * @throws InvalidArgumentException If target id is not int
     */
    public function setTargetId($targetId)
    {
        $this->targetId = self::parseInt($targetId);
        return $this;
    }

    /**
     * Get target id
     *
     * @return false|int
     */
    public function getTargetId()
    {
        return $this->getParam('targetId');
    }

    /**
     * Set comment level
     *
     * @param  int level
     * @return self
     * @throws InvalidArgumentException If level is not integer
     */
    public function setLevel($level)
    {
        $this->level = self::parseInt($level);
        if (!$this->isChild) {
            $this->markChildFlag();
        }

        return $this;
    }

    /**
     * Get comment level
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->getParam('level', 0);
    }

    /**
     * Set comment origin key
     *
     * @param originKey int The comment origin key
     * @return self
     * @throws InvalidArgumentException If origin key is not integer
     */
    public function setOriginKey($originKey)
    {
        $this->originKey = self::parseInt($originKey);
        if (!$this->isChild) {
            $this->markChildFlag();
        }

        return $this;
    }

    /**
     * Get the comment origin key
     *
     * @return int|null
     */
    public function getOriginKey()
    {
        return $this->getParam('originKey', null);
    }

    /**
     * Parse int value
     *
     * @param  mix value The value need to be parsed
     * @return int
     * @throws InvalidArgumentException If value is not int string or integer
     */
    public static function parseInt($value)
    {
        if (!is_int($value) && !ctype_digit($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid origin key: %s', $value));
        }

        return (int) $value;
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
