<?php
namespace Tomments\Test\TestAsset;

use PDO;
use PDOStatement;

class PDOStatementStub extends PDOStatement
{
    protected $resultSets   = array();
    protected $resultSet    = array();
    protected $status       = true;
    protected $boundColumns = array();
    protected $returnValus  = array();

    public function bindColumn(
        $column ,&$param, $type = PDO::PARAM_STR,
        $maxlen = -1, $driverdata = null
    ) {
        $param = true;
        $this->boundColumns[$column] = &$param;

        if (isset($this->returnValues[__METHOD__])) {
            return $this->returnValues[__METHOD__];
        }

        return true;
    }

    public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR)
    {
    }

    public function execute($input_parameters = null)
    {
        if (isset($this->returnValues[__METHOD__])) {
            return $this->returnValues[__METHOD__];
        }

        if (!empty($this->resultSets)) {
            $this->resultSet = array_shift($this->resultSets);
        }

        return true;
    }

    public function fetch($how = null, $orientation = null, $offset = null)
    {
        if (empty($this->resultSet)) {
            return false;
        }

        $return = array_shift($this->resultSet);
        foreach ($return as $key => $value) {
            if (isset($this->boundColumns[$key])) {
                $this->boundColumns[$key] = $value;
            }
        }

        return $return;
    }

    public function errorInfo()
    {
        return array('statement error');
    }

    public function addResultSet(array $resultSet)
    {
        $this->resultSets[] = $resultSet;
    }

    public function setReturnValue($method, $value)
    {
        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException('Invalid method: ' . $method);
        }

        $mehtod = __CLASS__ . '::' . $method;
        $this->returnValues[$method] = $value;
    }

    public function getResultSets()
    {
        return $this->resultSets;
    }
}
