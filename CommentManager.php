<?php
namespace Tomments;

use Tomments\DataMapper\CommentMapperInterface;
use Tomments\Comment\CommentInterface;
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
     * @param CommentInterface commentPrototype The commentPrototype
     */
    public function __construct(
        CommentMapperInterface $commentMapper,
        CommentInterface $commentPrototype
    ) {
        $this->commnetMapper    = $commentMapper;
        $this->commentPrototype = $commentPrototype;
    }

    /**
     * Get commmnts
     *
     * @param  int startKey The key that start searching with
     * @param  int count The number of comments that need to return
     * @param  bool isChild If the startKey map to a child comment
     * @param  int|null originKey The origin comment key of this child comment
     * @return array
     * @throws InvalidArgumentException If originKey is null and isChild is set to true
     */
    public function getComments($startKey, $count, $originKey = null)
    {
        return $this->mapper->findComments($startKey, $count, $originKey);
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

        return $this->mapper->insert($comment);
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

        return $this->mapper->update($comment);
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

        return $this->mapper->delete($comment);
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
