<?php
namespace Tomments\DataMapper;

use Tomments\CommentManager;
use Tomments\InjectCommentManagerInterface;
use Tomments\Comment\CommentInterface;
use PDO;
use PDOStatement;
use stdClass;
use InvalidArgumentException;
use LogicException;

abstract class AbstractCommentMapper implements
    CommentMapperInterface,
    InjectCommentManagerInterface
{
    /**
     * CommentManager
     * @var CommentManager
     */
    protected $commentManager;

    /**
     * @var PDO Database connection
     */
    protected $db;

    /**
     * Comment table name
     * @var string
     */
    protected $tableName;

    /**
     * Comment data list
     * @var CommentDataList
     */
    protected $commentDataList;

    /**
     * Constructor
     *
     * @param  PDO db Database connection
     * @param  string tableName The comment table name
     * @throws InvalidArgumentException If no field_name_mapper field in the config
     */
    public function __construct(PDO $db, $tableName = 'comment')
    {
        if (!isset($this->columnMapper) || !is_array($this->columnMapper)) {
            throw new LogicException(
                'colmnMapper must be defined by subclass');
        }

        $this->db              = $db;
        $this->tableName       = $tableName;
        $this->commentDataList = new CommentDataList();
    }

    /**
     * @see InjectCommentManagerInterface::setCommentManager
     */
    public function setCommentManager(CommentManager $commentManager)
    {
        $this->commentManager = $commentManager;
        return $this;
    }

    /**
     * @see InjectCommentManagerInterface::getCommentManager
     * @throws LogicException If CommentManager dose not set
     */
    public function getCommentManager()
    {
        if (!$this->commentManager) {
            throw new LogicException('CommentManager dose not set');
        }

        return $this->commentManager;
    }

    /**
     * Find origin comment statement
     * Handy for test.
     *
     * @return string
     */
    public function findOriginCommentStatement()
    {
        $sql = 'SELECT id, child_count, '
            . implode(', ', $this->columnMapper)
            . ' FROM ' . $this->tableName
            . ' WHERE id <= ? LIMIT ? ORDER BY DESC';

        return $sql;
    }

    /**
     * Find child comment statment
     * Handy for test.
     *
     * @param  int originKeyCount The number of origin keys
     * @return string
     */
    public function findChildCommentStatement($originKeyCount)
    {
        $sql = 'SELECT id, level, parent_id, origin_id, '
            . implode(', ', $this->columnMapper)
            . ' FROM ' . $this->tableName
            . ' WHERE origin_id IN (';

        for ($i = 1; $i < $originKeyCount; $i++) {
            $sql .= '?, ';
        }
        $sql .= '?) ORDER BY ASC';

        return $sql;
    }

    /**
     * Insert comment statment
     * Handy for test.
     *
     * @param bool isChild
     * @return string
     */
    public function insertCommentStatement($isChild = false)
    {
        $sql = 'INSERT INTO ' . $this->tableName;
        if ($isChild) {
            $sql .= ' (level, parent_id, origin_id, ';
            $columnCount = 3 + count($this->columnMapper);
        } else {
            $sql .= ' (child_count, ';
            $columnCount = 1 + count($this->columnMapper);
        }

        $sql .= implode(', ', $this->columnMapper) . ') VALUES (';
        for ($i = 1; $i < $columnCount; $i++) {
            $sql .= '?, ';
        }
        $sql .= '?)';

        return $sql;
    }

    /**
     * Increment child_count column for origin comment
     * Handy for test.
     *
     * @return string
     */
    public function updateChildCountStatement()
    {
        $sql = 'UPDATE ' . $this->tableName
            . ' SET child_count = child_count + 1'
            . ' WHERE id = ?';
        return $sql;
    }

    /**
     * Update comment statement
     * Handy for test.
     *
     * @param  array updateColumns The columns need to update
     * @return string
     */
    public function updateCommentStatement(array $updateColumns)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET ';

        $setStr = '';
        foreach ($updateColumns as $column) {
            if ('' !== $setStr) {
                $setStr .= ',';
            }
            $setStr .= $column . ' = ?';
        }

        $sql .= $setStr . ' WHERE id = ?';

        return $sql;
    }

    /**
     * Delete comment statement
     * Handy for test.
     *
     * @param  bool isChild
     * @return string
     */
    public function deleteCommentStatement($isChild = false)
    {
        $sql = 'DELETE FROM ' . $this->tableName . ' WHERE id = ?';
        if (!$isChild) {
            $sql .= ' OR origin_id = ?';
        }

        return $sql;
    }

    /**
     * Find comments
     * @see CommentMapperInterface::findComments
     * @throws InvalidArgumentException If startKey is not int
     * @throws InvalidArgumentException If length is less than 1
     * @throws InvalidArgumentException If origin key is not int when provided
     */
    public function findComments($startKey, $length, $originKey = null)
    {
        if (!is_int($startKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid start key: %s', $startKey));
        }
        if (!is_int($length) || $length < 1) {
            throw new InvalidArgumentException('Length must be greater than 0');
        }

        $isChild   = $originKey ? true : false;
        $startKey2 = $startKey;
        $count     = $length;
        if ($isChild) {
            if (!is_int($originKey)) {
                throw new InvalidArgumentException(
                    'Invalid origin key: ' . $originKey);
            }

            $num    = $this->loadChildCommentRows(array($originKey));
            $offset = $this->commentDataList->getOffset($startKey2);
            $num -= ($offset + 1);

            $startKey2 = $originKey - 1;
            $count     = $count > $num ? $count - $num : 0;
        }

        if ($count > 0) {
            $this->loadCommentRows($startKey2, $count);
        }

        return $this->loadComments($startKey, $length, $isChild);
    }

    /**
     * Insert a comment
     * @see CommentMapperInterface::insert
     */
    public function insert(CommentInterface $comment)
    {
        $this->db->beginTransaction();

        $isChild = $comment->isChild();
        if ($isChild) {
            $stmt = $this->db->prepare($this->updateChildCountStatement());
            $stmt->bindValue(1, $comment->getOriginKey(), PDO::PARAM_INT);

            if (!$stmt->execute()) {
                $this->db->rollBack();
                $this->error(
                    'Cannot increment child_count with comment :%d',
                    $comment->getOriginKey());

                return false;
            }
        }

        $stmt = $this->db->prepare($this->insertCommentStatement($isChild));

        foreach ($this->columnMapper as $field => $column) {
            $stmt->bindValue(":$column", $comment->{$field});
        }

        if ($isChild) {
            $stmt->bindValue(':level', $comment->getLevel(), PDO::PARAM_INT);
            $stmt->bindValue(
                ':parent_id', $comment->getParentKey(), PDO::PARAM_INT);
            $stmt->bindValue(
                ':origin_id', $comment->getOriginKey(), PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':child_count', 0, PDO::PARAM_INT);
        }

        if (!$stmt->execute()) {
            $this->db->rollBack();
            $this->error('Cannot insert a comment', $stmt);
            return false;
        }

        $this->db->commit();
        return true;
    }

    /**
     * Update a comment
     * @see CommentMapperInterface::update
     * @throws LogicException If subclass dose not define updatableColumnMapper
     * @throws LogicException If updatableColumnMapper contains preserved columns
     */
    public function update(CommentInterface $comment)
    {
        if (
            !isset($this->updatableColumnMapper)
            || !is_array($this->updatableColumnMapper)
        ) {
            throw new LogicException(
                'updatableColumnMapper must be defined by subclass when update function');
        }

        $test = array_intersect(
            ['id', 'child_count', 'level', 'parent_id', 'origin_id'],
            $this->updatableColumnMapper);
        if (!empty($test)) {
            throw new LogicException(sprintf(
                'Cannot update preserved columns: %s',
                var_export($this->updatableColumnMapper, 1)));
        }

        $updateColumns = array();
        foreach ($this->updatableColumnMapper as $field => $column) {
            if (null !== $comment->{$field}) {
                $updateColumns[$column] = $comment->{$field};
            }
        }

        $this->db->beginTransaction();
        $stmt = $this->db->prepare(
            $this->updateCommentStatement(array_keys($updateColumns)));

        foreach ($updateColumns as $column => $value) {
            $stmt->bindValue(":$column", $value);
        }
        $stmt->bindValue(':id', $comment->getKey(), PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $this->db->rollBack();
            $this->error(
                'Cannot update comment: %d', $comment->getKey(), $stmt);

            return false;
        }

        $this->db->commit();
        return true;
    }

    /**
     * Detele a comment
     * @see CommentMapperInterface::delete
     */
    public function delete(CommentInterface $comment)
    {
        $this->db->beginTransaction();

        $isChild = $comment->isChild();
        $key     = $comment->getKey();

        $stmt = $this->db->prepare($this->deleteCommentStatement($isChild));
        $stmt->bindValue(':id', $key);

        if (!$isChild) {
            $stmt->bindValue(':origin_id', $key);
        }

        if (!$stmt->execute()) {
            $this->db->rollBack();
            $this->error('Cannot delete comment: %d', $key, $stmt);
            return false;
        }

        $this->db->commit();
        return true;
    }

    /**
     * Load comment objects from comment row list
     *
     * @param  int startKey The key start to load
     * @param  int length The length of comment to be loaded
     * @return CommentInterface[]
     * @throws InvalidArgumentException If cannot find comment row node with start key
     */
    protected function loadComments($startKey, $length)
    {
        $this->commentDataList->setIterationContext($startKey, $length);

        $loadedComments = array();
        $resultComments = array();
        foreach ($this->commentDataList as $key => $data) {
            $comment = clone $this->getCommentManager()->getCommentPrototype();
            $comment->load($data)
                ->setKey($data['id']);
            if (isset($data['parent_id'])) {
                $comment->markChildFlag()
                    ->setParentKey($data['parent_id'])
                    ->setOriginKey($data['origin_id'])
                    ->setLevel($data['level']);
            }

            if ($comment->isChild() && isset($loadedComments[$key])) {
                $loadedComments[$key]->addChild($comment);
            } else {
                $resultComments[$key] = $comment;
            }

            $loadedComments[$key] = $comment;
        }

        return $resultComments;
    }

    /**
     * Load comment row from database
     *
     * @param  int startKey The comment key start to search
     * @param  int count The number of comment row to load
     * @return int The number of actual loaded comment row
     * @throws LogicException If cannot bind with id column
     * @throws LogicException If cannot bind with child_count column
     */
    protected function loadCommentRows($startKey, $count)
    {
        $stmt = $this->db->prepare($this->findOriginCommentStatement());
        $stmt->bindValue(1, $startKey, PDO::PARAM_INT);
        $stmt->bindValue(2, $count, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $this->error('Can not execute findOriginCommentStatement', $stmt);
        }

        if (!$stmt->bindColumn('id', $key, PDO::PARAM_INT)) {
            throw new LogicException('Can not bind with id column');
        }
        if (!$stmt->bindColumn('child_count', $childCount, PDO::PARAM_INT)) {
            throw new LogicException(
                'Can not bind with child_count column');
        }

        $leftCount  = $count;
        $originKeys = array();
        while ($leftCount > 0 && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->commentDataList->insert($row);
            $leftCount--;

            if ($childCount > 0) {
                $originKeys[] = $key;
                $leftCount -= $childCount;
            }
        }

        if (!empty($originKeys)) {
            $this->loadChildCommentRows($originKeys);
        }

        return $count - $leftCount;
    }

    /**
     * Load child comment row from database
     *
     * @param  int[] originKeys
     * @return int The number of loaded comment rows
     * @throws LogicException If cannot bind with id column
     * @throws LogicException If cannot bind with level column
     * @throws LogicException If cannot bind with parent_id column
     * @throws LogicException If cannot bind with origin_id column
     */
    protected function loadChildCommentRows(array $originKeys)
    {
        $stmt = $this->db->prepare(
            $this->findChildCommentStatement(count($originKeys)));
        foreach ($originKeys as $key => $originKey) {
            $stmt->bindValue($key + 1, $originKey, PDO::PARAM_INT);
        }

        if (!$stmt->execute()) {
            $this->error('Cannot execute find child comment statement');
        }
        if (!$stmt->bindColumn('id', $key)) {
            throw new LogicException('Cannot bind with id column');
        }
        if (!$stmt->bindColumn('level', $level)){
            throw new LogicException('Cannot bind with level column');
        }
        if (!$stmt->bindColumn('parent_id', $parentKey)) {
            throw new LogicException('Cannot bind with parent_id column');
        }
        if (!$stmt->bindColumn('origin_id', $originKey)) {
            throw new LogicException('Cannot bind with origin_id column');
        }

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->commentDataList->insert($row);
            $count++;
        }
        return $count;
    }

    /**
     * Sql execute error
     *
     * @param  string message Error message
     * @param  array args The message args
     * @param  PDOStatement stmt
     * @throws LogicException
     */
    protected function error($message, array $args = null, PDOStatement $stmt)
    {
        $errorInfo = array(
            'database'  => $this->db->errorInfo(),
        );

        if ($stmt) {
            $errorInfo['statment'] = $stmt->errorInfo();
        }

        array_unshift($message);
        $message = call_user_func_array('sprintf', $args);

        error_log(sprintf(
            'Error message: %s, Sql error: %s',
            $message,
            var_export($errorInfo, 1)
        ));
    }
}
