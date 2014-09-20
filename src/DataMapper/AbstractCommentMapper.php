<?php
namespace Tomments\DataMapper;

use Tomments\CommentManager;
use Tomments\InjectCommentManagerInterface;
use Tomments\Comment\CommentInterface;
use PDO;
use PDOStatement;
use PDOException;
use stdClass;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use DomainException;

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
     * Comment target column name
     * @var string
     */
    protected $targetColumn;

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
     * The last load comment key
     * @var int
     */
    protected $lastLoadCommentKey;

    /**
     * Constructor
     *
     * @param  array config Mapper configuration
     * @throws LogicException If columnMapper isn`t set or is invalid
     * @throws DomainException If required configuration field is not provided
     * @throws RuntimeException If cannot create a db connection
     * @throws InvalidArgumentException If targetCoumn is invalid
     * @thrwos InvalidArgumentException If the provided table name is invalid
     */
    public function __construct(array $config)
    {
        if (!isset($this->columnMapper) || !is_array($this->columnMapper)) {
            throw new LogicException(
                'colmnMapper must be defined by subclass');
        }

        if (!isset($config['db'], $config['target-column'])) {
            throw new DomainException(sprintf(
                'Missing required configuration', var_export($config, true)));
        }

        $dbConfig = $config['db'];
        if (
            is_array($dbConfig)
            && isset($dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'])
        ) {
            $options = isset($dbConfig['options']) ? $dbConfig['options'] : array();
            try {
                $pdo = new PDO($dbConfig['dsn'], $dbConfig['username'], $dbConfig['password'], $options);
            } catch (PDOException $e) {
                throw new RuntimeException('Cannot connect with database');
            }

            $this->db = $pdo;
        } elseif ($dbConfig instanceof PDO) {
            $this->db = $dbConfig;
        } else {
            throw new InvalidArgumentException(sprintf(
                'Invalid db config: %s', var_export($dbConfig, true)));
        }

        if (
            !is_string($config['target-column'])
            && !ctype_alpha($config['target-column'])
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid comment target column name: %s', $config['target-column']));
        }
        $this->targetColumn = $config['target-column'];

        if (isset($config['table-name'])) {
            $tableName = $config['table-name'];
            if (!is_string($tableName) || !ctype_alpha($tableName)) {
                throw new InvalidArgumentException(sprintg(
                    'Invalid table name: %s', $tableName));
            }
        } else {
            $tableName = 'comment';
        }
        $this->tableName = $tableName;

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
            . ' WHERE id <= ? AND ' . $this->targetColumn
            . ' = ? LIMIT ? ORDER BY DESC';

        return $sql;
    }

    /**
     * Find child comment statement
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
     * Get search key statement
     * Handy for test.
     *
     * @return string
     */
    public function getSearchKeyStatement()
    {
        $sql = 'SELECT MAX(id) FROM ' . $this->tableName
            . ' WHERE ' . $this->targetColumn . ' = ? AND level = ?';
        return $sql;
    }

    /**
     * Insert comment statement
     * Handy for test.
     *
     * @param bool isChild
     * @return string
     */
    public function insertCommentStatement($isChild = false)
    {
        $sql = 'INSERT INTO ' . $this->tableName . ' (level, ';
        if ($isChild) {
            $sql .= 'parent_id, origin_id, ';
            $columnCount = 3;
        } else {
            $sql .= 'child_count, ';
            $columnCount = 2;
        }

        $sql .= $this->targetColumn . ', ';
        $columnCount += (1 + count($this->columnMapper));

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
     * @throws InvalidArgumentException If target id is not int
     * @throws InvalidArgumentException If searchKey is not int
     * @throws InvalidArgumentException If length is less than 1
     * @throws InvalidArgumentException If origin key is not int when provided
     * @throws LogicException If search key is null but origin key isn`t
     * @throws LogicException Encounter a sql error when getting search key
     * @throws LogicException If cannot get search key when isn`t provided
     */
    public function findComments(
        $targetId, $searchKey = null, $length = 10, $originKey = null
    ) {
        if (!is_int($targetId)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid target id: %s', $targetId));
        }
        if (!is_int($searchKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid search key: %s', var_export($searchKey, 1)));
        }
        if (!is_int($length) || $length < 1) {
            throw new InvalidArgumentException('Length must be greater than 0');
        }
        if ($originKey && !is_int($originKey)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid origin key: %s', var_export($originKey, 1)));
        }

        $isChild = $originKey ? true : false;
        if ($isChild && !$searchKey) {
            throw new LogicException(
                'Search from the origin comment when search key is not provided');
        }

        $this->db->beginTransaction();

        if (!$searchKey) {
            $stmt = $this->db->prepare($this->getSearchKeyStatement());
            $stmt->bindValue(":{$this->targetColumn}", $targetId);
            $stmt->bindValue(':level', 0);

            if (!$stmt->execute()) {
                $this->error('Cannot get search key', $stmt);
                throw new LogicException('Cannot get search key');
            }

            $result = $this->fetch(PDO::FETCH_NUM);
            if (empty($result)) {
                $this->db->rollBack();
                throw new LogicException(sprintf(
                    'Cannot get search key with targetId: %d', $targetId));
            }
            $searchKey = $result[0];
        }

        $searchKey2 = $searchKey;
        // Increment by one to be able getting the next search key
        $count      = $length + 1;
        if ($isChild) {
            $num    = $this->loadChildCommentRows(array($originKey));
            $offset = $this->commentDataList->getOffset($searchKey2);
            $num -= ($offset + 1);

            $searchKey2 = $originKey - 1;
            $count      = $count > $num ? $count - $num : 0;
        }

        if ($count > 0) {
            $this->loadCommentRows($searchKey2, $count);
        }

        $this->db->commit();

        return $this->loadComments($searchKey, $length, $isChild);
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
                $this->error(
                    sprintf('Cannot increment child_count with comment :%d',
                        $comment->getOriginKey()),
                    null,
                    false);

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
            $stmt->bindValue(':level', 0, PDO::PARAM_INT);
            $stmt->bindValue(':child_count', 0, PDO::PARAM_INT);
        }
        $stmt->bindValue(
            ":{$this->targetColumn}", $comment->getTargetId(), PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $this->error('Cannot insert a comment', $stmt, false);
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
            $this->error(
                sprintf('Cannot update comment: %d', $comment->getKey()),
                $stmt,
                false);

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
            $this->error(
                sprintf('Cannot delete comment: %d', $key), $stmt, false);
            return false;
        }

        $this->db->commit();
        return true;
    }

    /**
     * Get next search key
     * When nextSearchKey is array, it contains key and is_child fields.
     * When nextSearchKey is bool, it can only be false which means no more
     * comment to find.
     *
     * @return array|null
     * @throws LogicException If comments is not loaded yet
     */
    public function getNextSearchKey()
    {
        if (is_null($this->lastLoadCommentKey)) {
            throw new LogicException('Comments have not load yet');
        }

        return $this->commentDataList->getNextCommentKey(
            $this->lastLoadCommentKey);
    }

    /**
     * Load comment objects from comment row list
     *
     * @param  int searchKey The key start to load
     * @param  int length The length of comment to be loaded
     * @return CommentInterface[]
     * @throws InvalidArgumentException If cannot find comment row node with search key
     */
    protected function loadComments($searchKey, $length)
    {
        $this->commentDataList->setIterationContext($searchKey, $length);

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

        // set last load comment key
        $this->lastLoadCommentKey = $key;

        return $resultComments;
    }

    /**
     * Load comment row from database
     *
     * @param  int searchKey The comment key start to search
     * @param  int count The number of comment row to load
     * @return bool|int The number of actual loaded comment row
     * @throws LogicException If cannot bind with id column
     * @throws LogicException If cannot bind with child_count column
     */
    protected function loadCommentRows($searchKey, $count)
    {
        $stmt = $this->db->prepare($this->findOriginCommentStatement());
        $stmt->bindValue(1, $searchKey, PDO::PARAM_INT);
        $stmt->bindValue(2, $count, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $this->error('Can not execute findOriginCommentStatement', $stmt);
        }

        if (!$stmt->bindColumn('id', $key, PDO::PARAM_INT)) {
            $this->db->rollBack();
            throw new LogicException('Can not bind with id column');
        }
        if (!$stmt->bindColumn('child_count', $childCount, PDO::PARAM_INT)) {
            $this->db->rollBack();
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
            $this->error('Cannot execute find child comment statement', $stmt);
        }
        if (!$stmt->bindColumn('id', $key)) {
            $this->db->rollBack();
            throw new LogicException('Cannot bind with id column');
        }
        if (!$stmt->bindColumn('level', $level)){
            $this->db->rollBack();
            throw new LogicException('Cannot bind with level column');
        }
        if (!$stmt->bindColumn('parent_id', $parentKey)) {
            $this->db->rollBack();
            throw new LogicException('Cannot bind with parent_id column');
        }
        if (!$stmt->bindColumn('origin_id', $originKey)) {
            $this->db->rollBack();
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
     * @param  PDOStatement stmt
     * @param  bool throwException Whether throw a exception
     * @throws RuntimeException
     */
    protected function error(
        $message, PDOStatement $stmt = null, $throwException = true)
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

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

        if ($throwException) {
            throw new RuntimeException($message);
        }
    }
}
