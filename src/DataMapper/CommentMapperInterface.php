<?php
namespace Tomments\DataMapper;

use Tomments\Comment\CommentInterface;

interface CommentMapperInterface
{
    /**
     * Find comments
     *
     * @param  int startKey The key that start searching with
     * @param  int length The number of comments need to search
     * @param  null|int originKey The origin comment key of the startKey comment
     * @return CommentInterface[]
     */
    public function findComments($startKey, $length, $originKey = null);

    /**
     * Insert a comment
     *
     * @param  CommentInterface comment
     * @return bool
     */
    public function insert(CommentInterface $comment);

    /**
     * Update a comment
     *
     * @param  CommentInterface comment
     * @return bool
     */
    public function update(CommentInterface $comment);

    /**
     * Delete a comment
     *
     * @param  CommentInterface comment
     * @return bool
     */
    public function delete(CommentInterface $comment);
}
