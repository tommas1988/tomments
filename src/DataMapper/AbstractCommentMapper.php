<?php
namespace Tomments\DataMapper;

use Tomments\CommentManager;
use Tomments\InjectCommentManagerInterface;
use Tomments\Comment\CommentInterface;
use Tomments\Comment\AbstractComment;
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
     * Comments organize types
     */
    const ORG_BY_TARGET_ID = 1;
    const ORG_BY_TABLE     = 2;

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
     * Comments` organize type
     * @var int
     */
    protected $organizeType;

    /**
     * Comment target column name if organize by target id
     * @var string
     */
    protected $targetColumn;

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
     * Extras search params
     * @var array
     */
    protected $searchParams = array();

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
                'columnMapper must be defined by subclass');
        }

        if (!isset($config['db'], $config['target-column'])) {
            throw new DomainException(sprintf(
                'Missing required configuration: %s', var_export($config, true)));
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
            isset($config['organize-type'])
            && self::ORG_BY_TARGET_ID === $config['organize-type']
        ) {
            if (!isset($config['target-column'])) {
                throw new InvalidArgumentException('Target column is not provided');
            }
            $this->organizeType = self::ORG_BY_TARGET_ID;
            $this->targetColumn = $config['target-column'];
        } else {
            $this->organizeType = self::ORG_BY_TABLE;
        }

        if (isset($config['table-name'])) {
            $tableName = $config['table-name'];
            if (!is_string($tableName)) {
                throw new InvalidArgumentException(sprintg(
                    'Invalid table name: %s', $tableName));
            }
        } else {
            $tableName = 'comments';
        }
        $this->tableName = $tableName;

        // initialize CommentDataList
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
            . ' FROM ' . $this->tableName . ' WHERE id <= ?'
            . (self::ORG_BY_TARGET_ID == $this->organizeType ? ' AND ' . $this->targetColumn . ' = ?' : '')
            . ' AND level = 0 ORDER BY id DESC LIMIT ?';

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
        $sql .= '?) AND state = 1 ORDER BY id ASC';

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
        $sql = 'SELECT MAX(id) FROM ' . $this->tableName . ' WHERE level = ?'
            . (self::ORG_BY_TARGET_ID == $this->organizeType ? ' AND ' . $this->targetColumn . ' = ?' : '');
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

        if (self::ORG_BY_TARGET_ID == $this->organizeType) {
            $sql .= $this->targetColumn . ', ';
            $columnCount++;
        }

        $columnCount += count($this->columnMapper);
        $sql .= implode(', ', $this->columnMapper) . ') VALUES (';
        for ($i = 1; $i < $columnCount; $i++) {
            $sql .= '?, ';
        }
        $sql .= '?)';

        return $sql;
    }

    /**
     * Increment or decrement child_count column for origin comment
     * Handy for test.
     *
     * @param  bool addition Increase or decrease the child count
     * @param  int amount The amount to increase or decrease 
     * @return string
     */
    public function updateChildCountStatement($addition = true, $amount = 1)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET child_count = child_count '
            . ($addition ? '+' : '-') . ' ' . (int) $amount
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
     * Update comment state statement
     * Handy for test
     *
     * @param  true|int arg
     * @return string
     */
    public function updateCommentStateStatement($arg = true)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET state = 0 WHERE ';

        $isOrigin = is_int($arg) ? false : true;
        if ($isOrigin) {
            $sql .= 'origin_id = ?';
        } else {
            $sql .= 'id IN (';
            $inStr = '';
            for($i = 0; $i < $arg; $i++) {
                if ('' !== $inStr) {
                    $inStr .= ', ';
                }
                $inStr .= '?';
            }

            $sql .= $inStr . ')';
        }

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

        return $sql;
    }

    /**
     * Set extra search params
     * @see CommentMapperInterface::setSearchParams
     */
    public function setSearchParams(array $params)
    {
        // Set target id
        if (
            self::ORG_BY_TARGET_ID == $this->organizeType
            && isset($params['targetId'])
        ) {
            $this->searchParams['targetId'] = AbstractComment::parseInt($params['targetId']);
        }

        // Set isChild flag
        $this->searchParams['isChild'] = false;

        // Set origin key
        if (isset($params['originKey'])) {
            $this->searchParams['originKey'] = AbstractComment::parseInt($params['originKey']);
            $this->searchParams['isChild']   = true;
        }

        return $this;
    }

    /**
     * Get comment target id
     *
     * @return false|int False if comments are organize by table
     * @throws LogicException If comment target id is not set when organize by target id
     */
    public function getTargetId()
    {
        if (self::ORG_BY_TARGET_ID != $this->organizeType) {
            return false;
        }

        if (!isset($this->searchParams['targetId'])) {
            throw new LogicException('Comment target id is not set');
        }
        return $this->searchParams['targetId'];
    }

    /**
     * Find comments
     * @see CommentMapperInterface::findComments
     */
    public function findComments($searchKey, $length)
    {
        // force args to int
        $searchKey = (int) $searchKey;
        $length    = (int) $length;

        // Begin the transaction
        $this->db->beginTransaction();

        if ($searchKey < 0) {
            $stmt = $this->db->prepare($this->getSearchKeyStatement());
            $stmt->bindValue(1, 0);
            if (false !== ($targetId = $this->getTargetId())) {
                $stmt->bindValue(2, $targetId);
            }

            if (!$stmt->execute()) {
                $this->error('Cannot get search key', $stmt);
                throw new LogicException('Cannot get search key');
            }

            $result = $stmt->fetch(PDO::FETCH_NUM);
            if (empty($result)) {
                $this->db->rollBack();
                throw new LogicException(sprintf(
                    'Cannot get search key with targetId: %d', $targetId));
            }
            $searchKey = $result[0];
        }

        // copy the search key
        $searchKey2 = $searchKey;
        // Increment by one to be able getting the next search key
        $count      = $length + 1;
        $isChild = $this->searchParams['isChild'];
        if ($isChild) {
            $originKey = $this->searchParams['originKey'];
            $num       = $this->loadChildCommentRows($originKey);
            $offset    = $this->commentDataList->getOffset($searchKey2);
            $num -= $offset;

            $searchKey2 = $originKey - 1;
            $count      = $count > $num ? $count - $num : 0;
        }

        if ($count > 0) {
            $this->loadCommentRows($searchKey2, $count);
        }

        // Commit the transaction
        $this->db->commit();

        return $this->loadComments($searchKey, $length, $isChild);
    }

    /**
     * Insert a comment
     * @see CommentMapperInterface::insert
     * @throws LogicException If miss level, parentKey, originKey params when comment is a child
     * @throws LogicException If target id is not provided when organize by target id
     */
    public function insert(CommentInterface $comment)
    {
        // Begin the transaction
        $this->db->beginTransaction();

        $isChild = $comment->isChild();
        if ($isChild) {
            $level     = $comment->getLevel();
            $parentKey = $comment->getParentKey();
            $originKey = $comment->getOriginKey();
            if (0 === $level || null === $parentKey || null === $originKey) {
                throw new LogicException(sprintf(
                    'Missing required params, level: %s, parentKey: %s, originKey: %s',
                    $level, $parentKey, $originKey));
            }

            $stmt = $this->db->prepare($this->updateChildCountStatement());
            $stmt->bindValue(1, $originKey, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                $this->error(
                    sprintf('Cannot increment child_count with comment :%d', $originKey),
                    null, false);

                return false;
            }
        }

        $stmt = $this->db->prepare($this->insertCommentStatement($isChild));

        if ($isChild) {
            $stmt->bindValue(1, $level, PDO::PARAM_INT);
            $stmt->bindValue(2, $parentKey, PDO::PARAM_INT);
            $stmt->bindValue(3, $originKey, PDO::PARAM_INT);
            $count = 3;
        } else {
            $stmt->bindValue(1, 0, PDO::PARAM_INT);
            $stmt->bindValue(2, 0, PDO::PARAM_INT);
            $count = 2;
        }

        if (self::ORG_BY_TARGET_ID == $this->organizeType) {
            if (false === ($targetId = $comment->getTargetId())) {
                throw new LogicException('Missing target id');
            }

            $count++;
            $stmt->bindValue($count, $targetId, PDO::PARAM_INT);            
        }

        foreach ($this->columnMapper as $field => $column) {
            $stmt->bindValue(++$count, $comment->{$field});
        }

        if (!$stmt->execute()) {
            $this->error('Cannot insert a comment', $stmt, false);
            return false;
        }

        // Commit the transaction
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
            $updateColumns[$column] = $comment->{$field};
        }

        $this->db->beginTransaction();
        $stmt = $this->db->prepare(
            $this->updateCommentStatement(array_keys($updateColumns)));

        $count = 1;        
        foreach ($updateColumns as $value) {
            $stmt->bindValue($count++, $value);
        }
        $stmt->bindValue($count, $comment->getKey(), PDO::PARAM_INT);

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
     * @throws LogicException If missing originKey when delete a child comment
     * @throws LogicException if missing level when delete a child comment
     */
    public function delete(CommentInterface $comment)
    {
        $this->db->beginTransaction();

        $deleteKey = $comment->getKey();
        $isChild   = $comment->isChild();
        if ($isChild) {
            if (null === ($originKey = $comment->getOriginKey())) {
                throw new LogicException('Origin key is required for delete child comment operation');
            }

            if (0 === ($level = $comment->getLevel())) {
                throw new LogicException('Level is required for delete child comment operation');
            }

            $num = $this->loadChildCommentRows($originKey);
            $this->commentDataList->setIterationContext($deleteKey, $num);

            $level  = $comment->getLevel();
            $keys   = array();
            $amount = 0;
            foreach ($this->commentDataList as $key => $data) {
                if ($level > $data['level']) {
                    break;
                }
                $amount++;
                $keys[] = $key;
            }

            // Update comment state
            $count = $amount - 1;
            if ($count > 0) {
                $stmt = $this->db->prepare($this->updateCommentStateStatement($count));
                // cut off the  current child key
                $keys = array_slice($keys, 1, null, true);
                foreach ($keys as $index => $key) {
                    $stmt->bindValue($index, $key);
                }

                if (!$stmt->execute()) {
                    $this->error('Can not update comment state', $stmt);
                    return false;
                }
            }
            
            // Update child comment count
            $stmt = $this->db->prepare($this->updateChildCountStatement(false, $amount));
            $stmt->bindValue(1, $originKey, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                $this->error(
                    sprintf('Cannot decrement child_count with comment :%d', $originKey), $stmt);

                return false;
            }
        } else {
            $stmt = $this->db->prepare($this->updateCommentStateStatement());
            $stmt->bindValue(1, $deleteKey);

            if (!$stmt->execute()) {
                $this->error('Can not update comment state', $stmt, false);
                return false;
            }
        }

        // detele comment
        $stmt = $this->db->prepare($this->deleteCommentStatement($isChild));
        $stmt->bindValue(1, $deleteKey);

        if (!$stmt->execute()) {
            $this->error(
                sprintf('Cannot delete comment: %d', $deleteKey), $stmt);
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
     */
    public function getNextSearchKey()
    {
        if (is_null($this->lastLoadCommentKey)) {
            return null;
        }

        return $this->commentDataList->getNextCommentKey(
            $this->lastLoadCommentKey);
    }

    /**
     * Load comment objects from comment row list
     *
     * @param  int searchKey The key start to load
     * @param  int length The length of comment to be loaded
     * @return AbstractComment[]
     * @throws InvalidArgumentException If cannot find comment row node with search key
     */
    protected function loadComments($searchKey, $length)
    {
        if ($this->commentDataList->isEmpty()) {
            return array();
        }

        $this->commentDataList->setIterationContext($searchKey, $length);

        $loadedComments = array();
        $resultComments = array();
        foreach ($this->commentDataList as $key => $data) {
            $comment = clone $this->getCommentManager()->getCommentPrototype();
            $comment->load($data)
                ->setKey($data['id']);

            $parentKey = isset($data['parent_id']) ? $data['parent_id'] : -1;
            if ($parentKey >= 0) {
                $comment->markChildFlag()
                    ->setParentKey($parentKey)
                    ->setOriginKey($data['origin_id'])
                    ->setLevel($data['level']);
            }

            if ($comment->isChild() && isset($loadedComments[$parentKey])) {
                $loadedComments[$parentKey]->addChild($comment);
            } else {
                $resultComments[] = $comment;
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

        $next = 2;
        if (false !== ($targetId = $this->getTargetId())) {
            $stmt->bindValue(2, $targetId, PDO::PARAM_INT);
            $next = 3;
        }
        $stmt->bindValue($next, $count, PDO::PARAM_INT);

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
     * @param  int[]|int originKeys
     * @return int The number of loaded comment rows
     * @throws LogicException If cannot bind with id column
     * @throws LogicException If cannot bind with level column
     * @throws LogicException If cannot bind with parent_id column
     * @throws LogicException If cannot bind with origin_id column
     */
    protected function loadChildCommentRows($originKeys)
    {
        // Convert single key to array
        $originKeys = !is_array($originKeys) ? array($originKeys) : $originKeys;

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

        $infoStr = function(array $errorInfo) {
            $infoTypeMapper = array(
                'SQLSTATE error code: ',
                'Driver error code: ',
                'Driver error message: ',
            );

            $str = '';
            array_walk(
                $errorInfo,
                function($value, $index) use (&$str, $infoTypeMapper) {
                    $str .= $infoTypeMapper[$index] . $value . ', ';
                }
            );

            return $str;
        };

        $message = "System error: $message\nDatabase error:\n"
            . $infoStr($this->db->errorInfo()) . "\n";

        if ($stmt) {
            $message .= "Statement error:\n" . $infoStr($stmt->errorInfo());
        }

        error_log($message);

        if ($throwException) {
            throw new RuntimeException($message);
        }
    }
}
