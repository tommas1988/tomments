<?php
namespace Tomments\DataMapper;

use Tomments\Comment\CommentInterface;

interface CommentMapperInterface
{
    /**
     * Set extra search params
     *
     * @param  array params
     * @return self
     */
    public function setSearchParams(array $params);

    /**
     * Find comments
     *
     * @param  int searchKey The key that start searching with
     * @param  int length The number of comments need to search
     * @return CommentInterface[]
     */
    public function findComments($searchKey, $length);

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
