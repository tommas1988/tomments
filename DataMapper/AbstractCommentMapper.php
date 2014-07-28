<?php
namespace Tomments\DataMapper;

use PDO;
use PDOStatement;
use InvalidArgumentException;
use LogicException;

abstract class AbstractCommentMapper implements CommentMapperInterface
{
    /**
     * Find child comment statement
     * @var PDOStatement
     */
    protected $findChildCommentStatement;

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
     * Origin comment table name
     * @var string
     */
    protected $originTableName;

    /**
     * Child comment table name
     * @var string
     */
    protected $childTableName;

    /**
     * Comment row list storage
     * Each node is a stdClass object with three properties: data, next, isChild
     * @var stdClass[]
     * @var ResultSet
     */
    protected $rowList;

    /**
     * The rear node of rowList
     * @var stdClass
     */
    protected $rear;

    /**
     * Constructor
     *
     * @param  CommentManager commentManager
     * @param  PDO db Database connection
     * @throws InvalidArgumentException If no field_name_mapper field in the config
     */
    public function __contruct(
        CommentManager $commentManager,
        PDO $db,
        $originTableName = 'ori_comment',
        $childTableName = 'chi_comment'
    ) {
        if (!is_array($this->columnMapper)) {
            throw new LogicException(
                'colmnMapper must be defined by subclass');
        }
        $this->commentManager  = $commentManager;
        $this->db              = $db;
        $this->originTableName = $originTableName;
        $this->childTableName  = $childTableName;
    }

    /**
     * Find origin comment statement
     * Handy for test.
     *
     * @return string
     */
    public function findOirginCommentStatement()
    {
        $sql = 'SELECT id, child_count, '
            . implode(', ', $this->columnMapper)
            . 'FROM' . $this->originTableName
            . 'WHERE id >= ? LIMIT ?';

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
            . 'FROM' . $this->childTableName
            . 'WHERE origin_id IN (';

        for ($i = 1; $i < $originKeyCount; $i++) {
            $sql .= '?, ';
        }
        $sql .= '?)';

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
        if ($isChild) {
            $sql = 'INSERT INTO ' . $this->childTableName
                . ' (id, level, parent_id, origin_id, ';
            $columnCount = 4;
        } else {
            $sql = 'INSERT INTO ' . $this->originTableName
                . ' (id, child_count, ';
            $columnCount = 2;
        }

        $sql .= implode(', ', $this->columnMapper) . ') VALUES (';

        $columnCount += count($this->columnMapper);
        for ($i = 1; $i < $columnCount; $i++) {
            $sql .= '?, ';
        }
        $sql .= '?)';

        return $sql;
    }

    /**
     * Update comment statement
     * Handy for test.
     *
     * @param  array updateColumns The columns need to update
     * @param  bool isChild
     * @return string
     * @throws LogicException If updateColumns contain preserved column name
     */
    public function updateCommentStatement($updateColumns, $isChild = false)
    {
        $tableName = $isChild ? $this->childTableName : $this->originTableName;
        $sql       = 'UPDATE ' . $tableName . 'SET ';

        $setStr = '';
        foreach ($updateColumns as $column) {
            if ('' !== $setStr) {
                $setStr .= ',';
            }
            $setStr .= $column . ' = ?';
        }

        $sql .= $setStr . 'WHERE id = ?';

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
        $tableName = $isChild ? $this->childTableName : $this->originTableName;
        $sql       = 'DELETE FROM ' . $tableName . 'WHERE id = ?';

        return $sql;
    }

    /**
     * Find comments
     * @see CommentMapperInterface::findComments
     * @throws InvalidArgumentException If startKey is not int
     * @throws InvalidArgumentException If length is less than 1
     */
    public function findComments($startKey, $length, $originKey = null)
    {
        if (!ctype_digit($startKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid start key: %s', $startKey));
        }
        if ($length < 1) {
            throw new InvalidArgumentException('Count must be greater than 0');
        }

        $count = $length;
        if (!$originKey) {
            $childCount = $this->loadChildCommentRows(array($originKey));
            $startKey++;
            $count = $count > $childcCount ? $count - $childCount : 0;
        }

        if ($count > 0) {
            $this->loadCommentRows($startKey, $count);
        }

        return $this->loadComments($startKey, $length);
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
            $sql = 'UPDATE ' . $this->originTableName . 'set child_count = child_count + 1';
            if (1 !== $this->db->exec($sql)) {
                $this->db->rollBack();
                $this->error(
                    'Cannot update child_count when insert a child comment');
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
     */
    public function update(CommentInterface $comment)
    {
        if (!is_array($this->updatableColumnMapper)) {
            throw new LogicException(
                'updatableColumnMapper must be defined by subclass when update function');
        }

        $updateColumns = array();
        $preserved     = array(
            'id', 'child_count', 'level',
            'parent_id', 'origin_id'
        );
        foreach ($this->updatableColumnMapper as $field => $column) {
            if (in_array($column, $preserved)) {
                throw new LogicException(sprintf(
                    'Cannot update preserved columns: %s',
                    var_export($preserved, 1)));
            }

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
            $this->error('Cannot update a comment', $stmt);
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

        if (!$isChild) {
            $sql = 'DELETE FROM ' . $this->childTableName
                . 'WHERE origin_id = ?';

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, $comment->getOriginKey());

            if (!$stmt->execute()) {
                $this->db->rollBack();
                $this->error(sprintf(
                    'Cannot delete child comments with origin key: %s',
                    $comment->getOriginKey()), $stmt);

                return false;
            }
        }

        $stmt = $this->db->prepare($this->deleteCommentStatement($isChild));
        $stmt->bindValue(':id', $comment->getKey());

        if (!$stmt->execute()) {
            $this->db->rollBack();
            $this->error('Cannot delete a comment', $stmt);
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
     */
    protected function loadComments($startKey, $length)
    {
        $comments = array();
        $key      = $startKey;
        for ($i = 0; $i < $length; $i++) {
            if (null === $key || !isset($this->rowList[$key])) {
                break;
            }

            $node    = $this->listRow[$key];
            $comment = $this->getComment($node);
            if (!$node->isChild || $key === $startKey) {
                $comments[] = $comment;
            } else {
                $parentComment = $this->getComment($comment->getParentKey());
                $parentComment->addChild($comment);
            }

            $key = $node->next;
        }

        return $comments;
    }

    /**
     * Get a comment object from comment row list
     *
     * @param  int|stdClass keyOrNode The key or node of comment row
     * @return CommentInterface
     * @throws InvalidArgumentException If the provided argument is not key or node
     */
    protected function getComment($keyOrNode)
    {
        if (is_int($key) && isset($this->rowList[$key])) {
            $node = $this->rowList[$key];
        } elseif (is_object($keyOrNode)) {
            $node = $keyOrNode;
        } else {
            throw new InvalidArgumentException(sprintf(
                'Cannot find the comment: %s', var_export($keyOrNode, 1)));
        }

        if ($node->data instanceof CommentInterface) {
            return $node->data;
        }

        $comment = clone $this->commentManager->getCommentPrototype();
        $comment->load($node->data)
            ->setKey($key);
        if ($node->isChild) {
            $comment->setLevel($node->data['level'])
                ->setParentKey($node->data['parent_id'])
                ->setOriginKey($node->data['origin_id'])
                ->markChildFlag();
        }
        $node->data = $comment;

        return $comment;
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
        $stmt = $this->db->prepare($this->getFindOriginCommentSql());
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
            $this->insertCommentRow($row);
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
            $this->getFindChildCommentSql(count($originKeys)));
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
            $this->insertCommentRow($row);
            $count++;
        }

        return $count;
    }

    /**
     * Insert a comment data row into comment row list
     *
     * @param  array row
     * @return self
     * @throws LogicException If child comment row dose not have same origin key
     */
    protected function insertCommentRow(array $row)
    {
        $key = $row['id'];
        if (
            !isset($row['parent_id'])
            || !isset($this->rowList[$row['parent_id']])
        ) {
            $node = new stdClass();
            $node->data = $row;
            $node->next = null;
            if (!isset($row['parent_id'])) {
                $node->isChild = false;
            }

            if (isset($this->rear)) {
                $this->rear['next'] = $node;
            }
            $this->rear = $node;
            $this->rowList[$key] = $node;
        } else {
            $level     = $row['level'];
            $parentKey = $row['parent_id'];
            $originKey = $row['origin_id'];
            $prevNode  = $this->rowList[$parentKey];

            while (
                $prevNode === $this->rear
                && ($level < $prevNode->data['level']
                || $parentKey === $prevNode->data['parent_id'])
            ) {
                $prevNode = $prevNode->next;
            }

            if ($originKey !== $prevNode->data['origin_id']) {
                throw new LogicException(
                    'Invalid child comment, origin key is not equal');
            }

            $node = new stdClass();
            $node->data    = $row;
            $node->next    = $prevNode->next;
            $node->isChild = true;

            $prevNode->next = $node;

            if ($prevNode === $this->rear) {
                $this->rear = $node;
            }
            $this->rowList[$key] = $node;
        }

        return $this;
    }

    /**
     * Sql execute error
     *
     * @param  string message Error message
     * @param  PDOStatement stmt
     * @throws LogicException
     */
    protected function error($message, PDOStatement $stmt = null)
    {
        $errorInfo = array(
            'database'  => $this->db->errorInfo(),
        );

        if ($stmt) {
            $errorInfo['statment'] = $stmt->errorInfo();
        }

        throw new LogicException(sprintf(
            'Error message: %s, Sql error: %s', $message, var_export($errorInfo, 1)));
    }
}
