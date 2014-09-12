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
     * @throws InvalidArgumentException If comment config field is invalid
     * @throws InvalidArgumentException If mapper config filed is invalid
     */
    public function __construct(array $config)
    {
        if (!isset($config['comment'], $config['mapper'])) {
            throw new DomainException(sprintf(
                'Missing required configuration: %s', var_export($config, true)));
        }

        if (is_string($config['comment'])) {
            $this->commentPrototype = new $config['comment']();
        } elseif ($config['comment'] instanceof CommentInterface) {
            $this->commentPrototype = $config['comment'];
        } else {
            throw new InvalidArgumentException(sprintf(
                'Invalid comment: %s', var_export($config['comment'], true)));
        }

        if (is_array($config['mapper']) && isset($config['mapper']['class'])) {
            $mapperConfig = isset($config['mapper']['config'])
                ? $config['mapper']['config']
                : array();
            $commentMapper = new $config['mapper']['class']($mapperConfig);
        } elseif ($config['mapper'] instanceof CommentMapperInterface) {
            $commentMapper = $config['mapper'];
        } else {
            throw new InvalidArgumentException(sprintf(
                'Invalid mapper: %s', var_export($config['mapper'], true)));
        }

        if ($commentMapper instanceof InjectCommentManagerInterface) {
            $commentMapper->setCommentManager($this);
        }

        $this->commentMapper = $commentMapper;
    }

    /**
     * Get commmnts
     *
     * @param  int tartgetId The comment target id
     * @param  int searchKey The key that start searching with
     * @param  int length The number of comments that need to return
     * @param  bool isChild If the startKey map to a child comment
     * @param  int|null originKey The origin comment key of this child comment
     * @return array
     * @throws LogicException If cannot get search key when isn`t provided
     */
    public function getComments(
        $targetId, $searchKey = null, $length = 10, $originKey = null
    ) {
        return $this->commentMapper->findComments(
            $targetId, $searchKey, $length, $originKey);
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
     * @param  int|null originKey The origin comment key
     * @return bool
     */
    public function addComment(
        array $params, $parentKey = null, $originKey = null
    ) {
        $comment = clone $this->commentPrototype;

        if (null !== $parentKey && null !== $originKey) {
            $comment->setParentKey($parentKey)
                ->setOriginKey($originKey)
                ->markChildFlag();
        }

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
     * @param  int|null originKey The origin comment key
     * @return bool
     */
    public function deleteComment($key, $originKey = null)
    {
        $comment = clone $this->commentPrototype;

        if (null !== $originKey) {
            $comment->setOriginKey($originKey)
                ->markChildFlag();
        }

        $comment->setKey($key);

        return $this->commentMapper->delete($comment);
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
}
