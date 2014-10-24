<?php
namespace Tomments;

use Tomments\DataMapper\CommentMapperInterface;
use Tomments\Comment\CommentInterface;
use LogicException;
use InvalidArgumentException;
use DomainException;

class CommentManager
{
    /**
     * Comment data mapper
     * @var CommnetMapperInterface
     */
    protected $commentMapper;

    /**
     * Comment Prototype
     * @var CommentInterface
     */
    protected $commentPrototype;

    /**
     * Constructor
     *
     * @param  array config Tomments configuration
     * @throws DomainException If required configuration is not provided
     */
    public function __construct(array $config)
    {
        if (!isset($config['comment'], $config['mapper'])) {
            throw new DomainException(sprintf(
                'Missing required configuration: %s', var_export($config, true)));
        }

        $this->setCommentPrototype($config['comment']);
        $this->setCommentMapper($config['mapper']);
    }

    /**
     * Get commmnts
     *
     * @param  int searchKey The key that start searching with
     * @param  int length The number of comments that need to return
     * @param  array|null searchParams Extras search params that need to perform search action
     * @param  int|null originKey The origin comment key of this child comment
     * @return CommentInterface[]
     * @throws InvalidArgumentException If searchKey is not int
     * @throws InvalidArgumentException If length is less than 1
     */
    public function getComments(
        $searchKey = -1, $length = 20, array $searchParams = null
    ) {
        if (!is_int($searchKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid search key: %s', gettype($searchKey)));
        }
        if (!is_int($length) || $length < 1) {
            throw new InvalidArgumentException('Length must be greater than 0');
        }

        if ($searchParams) {
            $this->commentMapper->setSearchParams($searchParams);
        }

        return $this->commentMapper->findComments($searchKey, $length);
    }

    /**
     * Proxy of AbstractCommentMapper::getNextSearchKey
     * @see AbstractCommentMapper::getNextSearchKey
     */
    public function getNextSearchKey()
    {
        return $this->commentMapper->getNextSearchKey();
    }

    /**
     * Add a comment
     *
     * @param  array params The comment params
     * @param  int|null parentKey The parent comment key
     * @return bool
     */
    public function addComment(array $params, $parentKey = null)
    {
        $comment = clone $this->commentPrototype;

        if (null !== $parentKey) {
            $comment->setParentKey($parentKey)
                ->markChildFlag();
        }

        // load comment data
        $comment->load($params);

        return $this->commentMapper->insert($comment);
    }

    /**
     * Update a comment
     *
     * @param  int key The comment key
     * @param  array updateParams The update params
     * @param  bool isChild If the update comment is a child
     * @return bool
     */
    public function updateComment($key, array $params, $isChild = false)
    {
        $comment = clone $this->commentPrototype;

        if ($isChild) {
            $comment->markChildFlag();
        }

        $comment->setKey($key)
            ->load($params);

        return $this->commentMapper->update($comment);
    }

    /**
     * Delete a comment
     *
     * @param  int key The comment key
     * @param  array|null params Comment params
     * @param  bool isChild If the comment is a child
     * @return bool
     */
    public function deleteComment($key, array $params = null, $isChild = false)
    {
        $comment = clone $this->commentPrototype;
        $comment->setKey($key);

        if ($params) {
            $comment->load($params);
        }

        if ($isChild) {
            $comment->markChildFlag();
        }

        return $this->commentMapper->delete($comment);
    }

    /**
     * Set comment prototype
     *
     * @param  string|CommentInterface comment The object or class of Comment
     * @return self
     * @throws InvalidArgumentException If comment is not array or Comment object
     */
    public function setCommentPrototype($comment)
    {
        if (is_string($comment)) {
            $this->commentPrototype = new $comment();
        } elseif ($comment instanceof CommentInterface) {
            $this->commentPrototype = $comment;
        } else {
            throw new InvalidArgumentException(sprintf(
                'Invalid comment: %s', var_export($comment, true)));
        }

        return $this;
    }

    /**
     * Get comment prototype
     *
     * @return CommentInterface
     */
    public function getCommentPrototype()
    {
        return $this->commentPrototype;
    }

    /**
     * Set comment data mapper
     *
     * @param  array|CommentMapperInterface The CommentMapper object or CommentMapper class and it`s configuration
     * @return self
     * @throws InvalidArgumentException If commentMapper is not object or array
     */
    public function setCommentMapper($commentMapper)
    {
        if (is_array($commentMapper) && isset($commentMapper['class'])) {
            $mapperConfig = isset($commentMapper['config'])
                ? $commentMapper['config']
                : array();
            $commentMapper = new $commentMapper['class']($mapperConfig);
        } elseif (!$commentMapper instanceof CommentMapperInterface) {
            throw new InvalidArgumentException(sprintf(
                'Invalid mapper: %s', var_export($commentMapper, true)));
        }

        if ($commentMapper instanceof InjectCommentManagerInterface) {
            $commentMapper->setCommentManager($this);
        }

        $this->commentMapper = $commentMapper;
        return $this;
    }

    /**
     * Get comment data mapper
     *
     * @return CommentMapperInterface
     */
    public function getCommentMapper()
    {
        return $this->commentMapper;
    }
}
