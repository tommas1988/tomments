<?php
namespace Tomments\Test\TestAsset;

use PDO;
use LogicException;
use OutOfRangeException;

class PDOStub extends PDO
{
    protected $statements = array();
    protected $pdoStmt;

    public function __construct()
    {
    }

    public function beginTransaction()
    {
    }

    public function commit()
    {
    }

    public function rollBack()
    {
    }

    public function prepare($statement, $driver_options = array())
    {
        if (!$this->pdoStmt) {
            throw new LogicException('PDOStatementStub has not set yet');
        }

        $this->statements[] = $statement;
        return $this->pdoStmt;
    }

    public function errorInfo()
    {
        return array('pdo error');
    }

    public function getStatement($offset = 0)
    {
        if (!isset($this->statements[$offset])) {
            throw new OutOfRangeException(sprintf(
                'Cannot find a statment with offset: %d', $offset));
        }
        return $this->statements[$offset];
    }

    public function setPdoStatement(PDOStatementStub $stmt)
    {
        $this->pdoStmt = $stmt;
    }

    public function getPdoStatement()
    {
        return $this->pdoStmt;
    }
}
