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
     * SQL errors
     */
    const GET_SEARCH_KEY_FAILED       = 1;
    const UPDATE_CHILD_COUNT_FAILED   = 2;
    const INSERT_COMMENT_FAILED       = 3;
    const UPDATE_COMMENT_FAILED       = 4;
    const UPDATE_COMMENT_STATE_FAILED = 5;
    const DELETE_COMMENT_FAIELD       = 6;
    const FIND_ORIGIN_COMMENT_FAILED  = 7;
    const FIND_CHILD_COMMENT_FAILED   = 8;

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
     * Comment target id
     * @var int
     */
    protected $targetId;

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
     * @throws LogicException If cusColInfos isn`t set or is invalid
     * @throws DomainException If required configuration field is not provided
     * @throws RuntimeException If cannot create a db connection
     * @throws InvalidArgumentException If targetCoumn is invalid
     * @thrwos InvalidArgumentException If the provided table name is invalid
     */
    public function __construct(array $config)
    {
        if (!isset($this->cusColInfos) || !is_array($this->cusColInfos)) {
            throw new LogicException(
                'cusColInfos must be defined by subclass');
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
     * @param  int key
     * @param  int length
     * @return string
     */
    public function findOriginCommentStatement($key, $length)
    {
        $sql = 'SELECT id, child_count, '
            . implode(', ', array_keys($this->cusColInfos))
            . ' FROM ' . $this->tableName . ' WHERE id <= ' . $key;

        if (false !== ($targetId = $this->getTargetId())) {
            $sql .= ' AND ' . $this->targetColumn . ' = ' . $targetId;
        }

        $sql .= ' AND level = 0 ORDER BY id DESC LIMIT ' . $length;

        return $sql;
    }

    /**
     * Find child comment statement
     * Handy for test.
     *
     * @param  int[] originKeys
     * @return string
     */
    public function findChildCommentStatement(array $originKeys)
    {
        $sql = 'SELECT id, level, parent_id, origin_id, '
            . implode(', ', array_keys($this->cusColInfos)) . ' FROM '
            . $this->tableName . ' WHERE origin_id IN ('
            . implode(', ', $originKeys) . ') AND state = 1 ORDER BY id ASC';

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
        $sql = 'SELECT MAX(id) FROM ' . $this->tableName . ' WHERE level = 0';
        if (false !== ($targetId = $this->getTargetId())) {
            $sql .= ' AND ' . $this->targetColumn . ' = ' . $targetId;
        }

        return $sql;
    }

    /**
     * Insert comment statement
     * Handy for test.
     *
     * @param  int level
     * @param  int|null parentKey
     * @param  int|null originKey
     * @param  array cusValPairs Custom column value pairs
     * @return string
     */
    public function insertCommentStatement($level, $parentKey, $originKey, array $cusValPairs)
    {
        $sql    = 'INSERT INTO ' . $this->tableName . ' (level';
        $values = array($level);

        if (0 !== $level) {
            $sql .= ', parent_id, origin_id';
            $values[] = $parentKey;
            $values[] = $originKey;
        }

        if (false !== ($targetId = $this->getTargetId())) {
            $sql .= ', ' . $this->targetColumn;
            $values[] = $targetId;
        }

        foreach ($cusValPairs as $col => $value) {
            $sql .= ' ,' . $col;
            $values[] = $value;
        }
        $sql .= ') VALUES (' . implode(', ', $values) . ')';

        return $sql;
    }

    /**
     * Update child_count column for origin comment
     * Handy for test.
     *
     * @param  int key Comment key
     * @param  bool increase Increase or decrease the child count
     * @param  int amount The amount to increase or decrease
     * @return string
     */
    public function updateChildCountStatement($key, $increase = true, $amount = 1)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET child_count = child_count '
            . ($increase ? '+' : '-') . ' ' . (int) $amount
            . ' WHERE id = ' . $key;
        return $sql;
    }

    /**
     * Update comment statement
     * Handy for test.
     *
     * @param  array updateValPairs The column value pairs that need to update
     * @param  int key
     * @return string
     */
    public function updateCommentStatement(array $updateValPais, $key)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET ';

        $setStr = '';
        foreach ($updateValPais as $column => $value) {
            if ('' !== $setStr) {
                $setStr .= ',';
            }
            $setStr .= $column . ' = ' . $value;
        }

        $sql .= $setStr . ' WHERE id = ' . $key;

        return $sql;
    }

    /**
     * Update comment state statement
     * Handy for test
     *
     * @param  int[]|int keys
     * @return string
     */
    public function updateCommentStateStatement($keys)
    {
        $sql = 'UPDATE ' . $this->tableName . ' SET state = 0 WHERE ';

        $isOrigin = is_array($keys) ? false : true;
        if ($isOrigin) {
            $sql .= 'origin_id = ' . $keys;
        } else {
            $sql .= 'id IN (' . implode(', ', $keys) . ')';
        }

        return $sql;
    }

    /**
     * Delete comment statement
     * Handy for test.
     *
     * @param  int key
     * @return string
     */
    public function deleteCommentStatement($key)
    {
        $sql = 'DELETE FROM ' . $this->tableName . ' WHERE id = ' . $key;

        return $sql;
    }

    /**
     * Set extra search params
     * @see    CommentMapperInterface::setSearchParams
     * @throws LogicException If comment target id is not set when organize by target id
     */
    public function setSearchParams(array $params)
    {
        // Set target id
        if (self::ORG_BY_TARGET_ID === $this->organizeType) {
            if (!isset($params['targetId'])
                || !ctype_digit($params['targetId'])
            ) {
                throw new LogicException('Comment target id is not set or not digit');
            }

            $this->targetId = AbstractComment::parseInt($params['targetId']);
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
     */
    public function getTargetId()
    {
        if (self::ORG_BY_TARGET_ID != $this->organizeType) {
            return false;
        }

        return $this->targetId;
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
            $sql  = $this->getSearchKeyStatement();
            $stmt = $this->db->query($sql);
            if (false === $stmt) {
                $this->error(self::GET_SEARCH_KEY_FAILED, $sql);
            }

            $result = $stmt->fetch(PDO::FETCH_NUM);
            if (empty($result)) {
                $this->db->rollBack();
                throw new LogicException(sprintf(
                    'Cannot get search key with search params: %s',
                    var_export($this->searchParams, 1)));
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

        $isChild   = $comment->isChild();
        $level     = $comment->getLevel();
        $parentKey = $comment->getParentKey();
        $originKey = $comment->getOriginKey();
        if ($isChild) {
            if (0 === $level || null === $parentKey || null === $originKey) {
                throw new LogicException(sprintf(
                    'Missing required params, level: %s, parentKey: %s, originKey: %s',
                    $level, $parentKey, $originKey));
            }

            $sql  = $this->updateChildCountStatement($originKey);
            $stmt = $this->db->query($sql);
            if (false === $stmt) {
                $this->error(self::UPDATE_CHILD_COUNT_FAILED, $sql, array($originKey));
                return false;
            }
        }

        $cusValPairs = array();
        foreach ($this->cusColInfos as $column => $info) {
            $cusValPairs[$column] = $this->db->quote($comment->{$info['field']});
        }

        $this->targetId = $comment->getTargetId();

        $sql  = $this->insertCommentStatement($level, $parentKey, $originKey, $cusValPairs);
        $stmt = $this->db->query($sql);
        if (false === $stmt) {
            $params = array($level, $parentKey, $originKey, $cusValPairs);
            $this->error(self::INSERT_COMMENT_FAILED, $sql, $params);
            return false;
        }

        // Commit the transaction
        $this->db->commit();
        return true;
    }

    /**
     * Update a comment
     * @see CommentMapperInterface::update
     * @throws LogicException If subclass dose not define updatableColumns
     * @throws LogicException If updatableColumns contains preserved columns
     */
    public function update(CommentInterface $comment)
    {
        if (
            !isset($this->updatableColumns)
            || !is_array($this->updatableColumns)
        ) {
            throw new LogicException(
                'updatableColumns must be defined by subclass when update function');
        }

        $test = array_intersect(
            ['id', 'child_count', 'level', 'parent_id', 'origin_id'],
            $this->updatableColumns);
        if (!empty($test)) {
            throw new LogicException(sprintf(
                'Cannot update preserved columns: %s',
                var_export($this->updatableColumns, 1)));
        }

        $updateValPais = array();
        foreach ($this->updatableColumns as $column) {
            $colInfo                = $this->cusColInfos[$column];
            $updateValPais[$column] = $this->db->quote($comment->{$colInfo['field']});
        }

        $this->db->beginTransaction();

        $sql  = $this->updateCommentStatement($updateValPais, $comment->getKey());
        $stmt = $this->db->query($sql);
        if (false === $stmt) {
            $params = array($updateValPais, $comment->getKey());
            $this->error(self::UPDATE_COMMENT_FAILED, $sql, $params);
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

            $level = $comment->getLevel();
            $keys  = array();
            foreach ($this->commentDataList as $key => $data) {
                if ($level > $data['level']) {
                    break;
                }
                $keys[] = $key;
            }

            // Update comment state
            $childCount = count($keys) - 1;
            if ($childCount > 0) {
                $sql  = $this->updateCommentStateStatement(array_slice($keys, 1));
                $stmt = $this->db->query($sql);
                if (false === $stmt) {
                    $params = array(array_slice($keys, 1));
                    $this->error(self::UPDATE_COMMENT_STATE_FAILED, $sql, $params);
                    return false;
                }
            }

            // Update child comment count
            $sql  = $this->updateChildCountStatement($originKey, false, $childCount);
            $stmt = $this->db->query($sql);
            if (false === $stmt) {
                $params = array($originKey, false, $childCount);
                $this->error(self::UPDATE_CHILD_COUNT_FAILED, $sql, $params);
                return false;
            }
        } else {
            $sql  = $this->updateCommentStateStatement($deleteKey);
            $stmt = $this->db->query($sql);
            if (false === $stmt) {
                $this->error(self::UPDATE_COMMENT_STATE_FAILED, $sql, array($deleteKey));
                return false;
            }
        }

        // detele comment
        $sql  = $this->deleteCommentStatement($deleteKey);
        $stmt = $this->db->query($sql);
        if (false === $stmt) {
            $this->error(self::DELETE_COMMENT_FAIELD, $sql, array($deleteKey));
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
        $sql  = $this->findOriginCommentStatement($searchKey, $count);
        $stmt = $this->db->query($sql);
        if (false === $stmt) {
            $params = array($searchKey, $count);
            $this->error(self::FIND_ORIGIN_COMMENT_FAILED, $sql, $params);
            return false;
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
     */
    protected function loadChildCommentRows($originKeys)
    {
        // Convert single key to array
        $originKeys = !is_array($originKeys) ? array($originKeys) : $originKeys;

        $sql = $this->findChildCommentStatement($originKeys);
        $stmt = $this->db->query($sql);
        if (false === $stmt) {
            $this->error(self::FIND_CHILD_COMMENT_FAILED, $sql, array($originKeys));
            return false;
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
     * @throws RuntimeException
     */
    protected function error($code, $sql, array $params = array())
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

        $message = "Database error:\n" . $infoStr($this->db->errorInfo()) . "\n"
            . "SQL: $sql\nParams: " . var_export($params, true);

        throw new RuntimeException($message, $code);
    }
}
