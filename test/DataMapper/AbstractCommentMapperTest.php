<?php
namespace Tomments\Test\DataMapper;

use Tomments\CommentManager;
use Tomments\Test\TestAsset\FooCommentMapper;
use Tomments\Test\TestAsset\FooComment;
use Tomments\Test\TestAsset\PDOStub;
use Tomments\Test\TestAsset\PDOStatementStub;

class AbstractCommentMapperTest extends \PHPUnit_Framework_TestCase
{
    protected $pdoStub;
    protected $mapper;
    protected static $childCommentData;
    protected static $originCommentData;

    protected function setUp()
    {
        $this->pdoStub = new PDOStub();
        $this->mapper  = new FooCommentMapper($this->pdoStub);

        $managerStub = $this->getMockBuilder('Tomments\CommentManager')
            ->disableOriginalConstructor()
            ->getMock();

        $managerStub->expects($this->any())
            ->method('getCommentPrototype')
            ->will($this->returnValue(new FooComment()));
        $this->mapper->setCommentManager($managerStub);
    }

    public static function setUpBeforeClass()
    {
        $path = __DIR__ . '/../data/';

        self::$childCommentData  = include $path . 'child_comments.php';
        self::$originCommentData = include $path . 'origin_comments.php';
    }

    public function testCannotCreateMapperIfNotDefineColumnMapper()
    {
        $this->setExpectedException(
            'LogicException', 'colmnMapper must be defined by subclass');

        $mapper = new FooCommentMapper(new PDOStub(), false);
    }

    public function testGetCommentManagerThrowExceptionIfNotSet()
    {
        $this->setExpectedException(
            'LogicException', 'CommentManager dose not set');

        $mapper = new FooCommentMapper($this->pdoStub);
        $mapper->getCommentManager();
    }

    public function testFindCommentsWithInvalidStartKey()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Invalid start key: start-key');

        $this->mapper->findComments('start-key', 1);
    }

    public function testFindCommentsWithInvalidLength()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Length must be greater than 0');
        $this->mapper->findComments(1, 0);

        $this->setExpectedException(
            'InvalidArgumentException',
            'Length must be greater than 0');
        $this->mapper->findComments(1, 'invalid-length');
    }

    public function testFindCommentsWithInvalidOriginKey()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Invalid origin key: origin-key');

        $this->mapper->findComments(1, 1, 'origin-key');
    }

    public function testFindCommentsWithChildStartKey()
    {
        $stmtStub = new PDOStatementStub();
        $stmtStub->addResultSet($this->getChildCommentResultSet([10]));
        $stmtStub->addResultSet($this->getOriginCommentResultSet(1, 9));
        $stmtStub->addResultSet($this->getChildCommentResultSet([4, 9]));

        $this->pdoStub->setPdoStatement($stmtStub);

        $comments = $this->mapper->findComments(3, 10, 10);

        $expected = 'SELECT id, level, parent_id, origin_id, text, date '
            . 'FROM chi_comment '
            . 'WHERE origin_id IN (?) ORDER BY ASC';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'SELECT id, child_count, text, date '
            . 'FROM ori_comment '
            . 'WHERE id <= ? LIMIT ? ORDER BY DESC';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));

        $expected = 'SELECT id, level, parent_id, origin_id, text, date '
            . 'FROM chi_comment '
            . 'WHERE origin_id IN (?, ?) ORDER BY ASC';
        $this->assertSame($expected, $this->pdoStub->getStatement(2));

        $childData  = self::$childCommentData;
        $originData = self::$originCommentData;
        foreach ($originData as &$data) {
            unset($data['child_count']);
        }
        $expected = array(
            $childData[2],
            $originData[8],
            $childData[3],
            $childData[14],
            $originData[7],
            $originData[6],
            $originData[5],
            $originData[4],
            $originData[3],
            $childData[4],
        );

        $result = array();
        foreach ($comments as $comment) {
            $result = array_merge($result, $comment->toArray());
        }
        $this->assertSame($expected, $result);
    }

    public function testFindCommentsWithOriginStartKey()
    {
        $stmtStub = new PDOStatementStub();
        $stmtStub->addResultSet($this->getOriginCommentResultSet(0, 10));
        $stmtStub->addResultSet($this->getChildCommentResultSet([9, 10]));

        $this->pdoStub->setPdoStatement($stmtStub);

        $comments = $this->mapper->findComments(10, 10);

        $expected = 'SELECT id, child_count, text, date '
            . 'FROM ori_comment '
            . 'WHERE id <= ? LIMIT ? ORDER BY DESC';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'SELECT id, level, parent_id, origin_id, text, date '
            . 'FROM chi_comment '
            . 'WHERE origin_id IN (?, ?) ORDER BY ASC';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));

        $childData  = self::$childCommentData;
        $originData = self::$originCommentData;
        foreach ($originData as &$data) {
            unset($data['child_count']);
        }
        $expected = array(
            $originData[9],
            $childData[0],
            $childData[1],
            $childData[2],
            $originData[8],
            $childData[3],
            $childData[14],
            $originData[7],
            $originData[6],
            $originData[5],
        );

        $result = array();
        foreach ($comments as $comment) {
            $result = array_merge($result, $comment->toArray());
        }
        $this->assertSame($expected, $result);
    }

    public function testInsertChildComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());

        $comment = new FooComment();
        $comment->markChildFlag();

        $this->assertTrue($this->mapper->insert($comment));

        $expected = 'UPDATE ori_comment '
            . 'SET child_count = child_count + 1 '
            . 'WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'INSERT INTO chi_comment '
            . '(id, level, parent_id, origin_id, text, date) '
            . 'VALUES '
            . '(?, ?, ?, ?, ?, ?)';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));
    }

    public function testInsertOriginComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());
        $comment = new FooComment();

        $this->assertTrue($this->mapper->insert($comment));

        $expected = 'INSERT INTO ori_comment '
            . '(id, child_count, text, date) '
            . 'VALUES '
            . '(?, ?, ?, ?)';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));
    }

    public function testUpdateThrowExceptionIfNotDefineUpdatableColumnMapper()
    {
        $this->setExpectedException(
            'LogicException',
            'updatableColumnMapper must be defined by subclass when update function');

        $mapper = new FooCommentMapper(new PDOStub(), true, false);
        $mapper->update(new FooComment());
    }

    public function testUpdateThrowExceptionIfUpdatableColumnContainsPreservedColumn()
    {
        $columnMapper = array(
            'key'  => 'id',
            'text' => 'text',
        );
        $this->setExpectedException(
            'LogicException',
            'Cannot update preserved columns: ' . var_export($columnMapper, 1));

        $this->mapper->setUpdatableColumnMapper($columnMapper);
        $this->mapper->update(new FooComment());
    }

    public function testUpdateComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());

        $comment = new FooComment();
        $this->mapper->update($comment);

        $expected = 'UPDATE ori_comment SET '
            . 'text = ? '
            . 'WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $comment->markChildFlag();
        $this->mapper->update($comment);

        $expected = 'UPDATE chi_comment SET '
            . 'text = ? '
            . 'WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));
    }

    public function testDeleteChildComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());

        $comment = new FooComment();
        $comment->markChildFlag();

        $this->mapper->delete($comment);

        $expected = 'DELETE FROM chi_comment WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));
    }

    public function testDeleteOriginComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());
        $this->mapper->delete(new FooComment());

        $expected = 'DELETE FROM chi_comment '
            . 'WHERE origin_id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'DELETE FROM ori_comment WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));
    }

    protected function getOriginCommentResultSet($offset, $length)
    {
        $data = self::$originCommentData;
        return array_slice(array_reverse($data), $offset, $length);
    }

    protected function getChildCommentResultSet(array $originKeys)
    {
        $data      = self::$childCommentData;
        $resultSet = array();
        foreach ($originKeys as $originKey) {
            array_walk($data, function($comment) use (&$resultSet, $originKey) {
                if ($comment['origin_id'] === $originKey) {
                    $resultSet[] = $comment;
                }
            });
        }

        return $resultSet;
    }
}
