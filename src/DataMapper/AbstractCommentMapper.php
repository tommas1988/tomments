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
     * The number of comment row in the rowList
     * @vat int
     */
    protected $length = 0;

    /**
     * Constructor
     *
     * @param  PDO db Database connection
     * @param  string originTableName The origin comment table name
     * @param  string childTableName The child comment table name
     * @throws InvalidArgumentException If no field_name_mapper field in the config
     */
    public function __construct(
        PDO $db,
        $originTableName = 'ori_comment',
        $childTableName = 'chi_comment'
    ) {
        if (!isset($this->columnMapper) || !is_array($this->columnMapper)) {
            throw new LogicException(
                'colmnMapper must be defined by subclass');
        }
        $this->db              = $db;
        $this->originTableName = $originTableName;
        $this->childTableName  = $childTableName;
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
            . ' FROM ' . $this->originTableName
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
            . ' FROM ' . $this->childTableName
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
     */
    public function updateCommentStatement($updateColumns, $isChild = false)
    {
        $tableName = $isChild ? $this->childTableName : $this->originTableName;
        $sql       = 'UPDATE ' . $tableName . ' SET ';

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
        $tableName = $isChild ? $this->childTableName : $this->originTableName;
        $sql       = 'DELETE FROM ' . $tableName . ' WHERE id = ?';

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
            $offset = $this->getCommentRowOffset($startKey2, $isChild);
            $num -= ((int) $offset + 1);

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
            $sql = 'UPDATE ' . $this->originTableName
                . ' SET child_count = child_count + 1'
                . ' WHERE id = ?';

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, $comment->getOriginKey(), PDO::PARAM_INT);

            if (!$stmt->execute()) {
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
            $this->updateCommentStatement(
                array_keys($updateColumns), $comment->isChild()));

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
                . ' WHERE origin_id = ?';

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
     * @param  bool isChild Whether the startKey comment is a child
     * @return CommentInterface[]
     * @throws InvalidArgumentException If cannot find comment row node with start key
     */
    protected function loadComments($startKey, $length, $isChild)
    {
        if (!isset($this->rowList[$startKey][$isChild])) {
            throw new InvalidArgumentException(sprintf(
                'Cannot find comment row node with key: %d', $startKey));
        }

        $node     = $this->rowList[$startKey][$isChild];
        $comments = array();
        for ($i = 0; $i < $length; $i++) {
            if (null === $node) {
                break;
            }

            $comment = $this->getComment($node);
            if (!$node->isChild || $comment->getKey() === $startKey) {
                $comments[] = $comment;
            } else {
                $prevNode = (1 === $comment->getLevel())
                    ? $this->rowList[$comment->getParentKey()][0]
                    : $this->rowList[$comment->getParentKey()][1];
                $parentComment = $this->getComment($prevNode);
                $parentComment->addChild($comment);
            }

            $node = $node->next;
        }

        return $comments;
    }

    /**
     * Get a comment object from comment row list
     *
     * @param  stdClass node The node of comment row
     * @return CommentInterface
     * @throws InvalidArgumentException If the provided argument is not key or node
     */
    protected function getComment(stdClass $node)
    {
        if ($node->data instanceof CommentInterface) {
            return $node->data;
        }

        $comment = clone $this->getCommentManager()->getCommentPrototype();
        $comment->load($node->data)
            ->setKey($node->data['id']);
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
            $this->insertCommentRow($row);
            $count++;
        }
        return $count;
    }

    /**
     * Get a comment row offset in comment row list
     *
     * @param  int key The comment row key
     * @param  bool isChild Whether the key comment is child
     * @return false|int
     */
    protected function getCommentRowOffset($key, $isChild)
    {
        if (!isset($this->rowList[$key][$isChild])) {
            return false;
        }

        $node  = $this->rowList[$key][$isChild];
        $count = 1;
        while ($this->rear !== $node) {
            $node = $node->next;
            $count++;
        }

        return $this->length - $count;
    }

    /**
     * Insert a comment data row into comment row list
     *
     * @todo   Seperate the comment row list data structure into a class
     * @param  array row
     * @return self
     * @throws LogicException If child comment row dose not have same origin key
     */
    protected function insertCommentRow(array $row)
    {
        $isChild = !isset($row['child_count']);
        $key = $row['id'];
        if (!$isChild || !isset($this->rowList[$row['parent_id']][1])) {
            $node = new stdClass();
            $node->data    = $row;
            $node->next    = null;
            $node->isChild = $isChild;

            if (isset($this->rear)) {
                $this->rear->next = $node;
            }
            $this->rear = $node;
            $this->rowList[$key][$isChild] = $node;
        } else {
            $level     = $row['level'];
            $parentKey = $row['parent_id'];
            $originKey = $row['origin_id'];

            if (1 === $level && isset($this->rowList[$parentKey][0])) {
                $prevNode = $this->rowList[$parentKey][0];
            } elseif ($level > 1 && isset($this->rowList[$parentKey][1])) {
                $prevNode = $this->rowList[$parentKey][1];
            } else {
                throw new LogicException('Cannot find previous node');
            }
            while (
                $prevNode !== $this->rear
                && ($prevNode->isChild
                && ($level < $prevNode->data['level']
                || $parentKey === $prevNode->data['parent_id']))
            ) {
                $prevNode = $prevNode->next;
            }

            $originKey2 = $prevNode->isChild
                ? $prevNode->data['origin_id']
                : $prevNode->data['id'];
            if ($originKey !== $originKey2) {
                throw new LogicException(sprintf(
                    'Invalid child comment, origin key is not equal: %d != %d',
                    $originKey,
                    $originKey2));
            }

            $node = new stdClass();
            $node->data    = $row;
            $node->next    = $prevNode->next;
            $node->isChild = true;

            $prevNode->next = $node;

            if ($prevNode === $this->rear) {
                $this->rear = $node;
            }
            $this->rowList[$key][1] = $node;
        }

        $this->length++;

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

        error_log(sprintf(
            'Error message: %s, Sql error: %s',
            $message,
            var_export($errorInfo, 1)
        ));
    }
}
