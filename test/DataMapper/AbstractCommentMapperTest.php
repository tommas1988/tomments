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
    protected static $childCommentDataset;
    protected static $originCommentDataset;
    protected static $commentDataset;

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
        $dataset = include __DIR__ . '/../data/comments.php';
        self::$commentDataset = $dataset;

        self::$childCommentDataset  = array();
        self::$originCommentDataset = array();
        foreach ($dataset as $data) {
            if (is_null($data['origin_id'])) {
                self::$originCommentDataset[] = $data;
            } else {
                self::$childCommentDataset[] = $data;
            }
        }
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
            'Invalid search key: \'search-key\'');

        $this->mapper->findComments(1, 'search-key', 1);
    }

    public function testFindCommentsWithInvalidLength()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Length must be greater than 0');
        $this->mapper->findComments(1, 1, 0);

        $this->setExpectedException(
            'InvalidArgumentException',
            'Length must be greater than 0');
        $this->mapper->findComments(1, 1, 'invalid-length');
    }

    public function testFindCommentsWithInvalidOriginKey()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Invalid origin key: \'origin-key\'');

        $this->mapper->findComments(1, 1, 1, 'origin-key');
    }

    public function testFindCommentsWithChildStartKey()
    {
        $stmtStub = new PDOStatementStub();
        $stmtStub->addResultSet($this->getChildCommentResultSet([19]));
        $stmtStub->addResultSet($this->getOriginCommentResultSet(1, 6));
        $stmtStub->addResultSet($this->getChildCommentResultSet([17]));

        $this->pdoStub->setPdoStatement($stmtStub);

        $comments = $this->mapper->findComments(1, 21, 10, 19);

        $expected = 'SELECT id, level, parent_id, origin_id, text, date '
            . 'FROM comment '
            . 'WHERE origin_id IN (?) ORDER BY ASC';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'SELECT id, child_count, text, date '
            . 'FROM comment '
            . 'WHERE id <= ? AND target_id = ? LIMIT ? ORDER BY DESC';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));

        $expected = 'SELECT id, level, parent_id, origin_id, text, date '
            . 'FROM comment '
            . 'WHERE origin_id IN (?, ?) ORDER BY ASC';
        $this->assertSame($expected, $this->pdoStub->getStatement(2));

        $dataset = self::$commentDataset;
        $expected = array(
            $this->getCommentResultArray($dataset[20]),
            $this->getCommentResultArray($dataset[22]),
            $this->getCommentResultArray($dataset[16]),
            $this->getCommentResultArray($dataset[17]),
            $this->getCommentResultArray($dataset[21]),
            $this->getCommentResultArray($dataset[15]),
            $this->getCommentResultArray($dataset[14]),
            $this->getCommentResultArray($dataset[11]),
            $this->getCommentResultArray($dataset[8]),
            $this->getCommentResultArray($dataset[4]),
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
        $stmtStub->addResultSet($this->getChildCommentResultSet([17, 19]));

        $this->pdoStub->setPdoStatement($stmtStub);

        $comments = $this->mapper->findComments(1, 19, 10);

        $expected = 'SELECT id, child_count, text, date '
            . 'FROM comment '
            . 'WHERE id <= ? AND target_id = ? LIMIT ? ORDER BY DESC';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'SELECT id, level, parent_id, origin_id, text, date '
            . 'FROM comment '
            . 'WHERE origin_id IN (?, ?) ORDER BY ASC';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));

        $dataset = self::$commentDataset;
        $expected = array(
            $this->getCommentResultArray($dataset[18]),
            $this->getCommentResultArray($dataset[19]),
            $this->getCommentResultArray($dataset[20]),
            $this->getCommentResultArray($dataset[22]),
            $this->getCommentResultArray($dataset[16]),
            $this->getCommentResultArray($dataset[17]),
            $this->getCommentResultArray($dataset[21]),
            $this->getCommentResultArray($dataset[15]),
            $this->getCommentResultArray($dataset[14]),
            $this->getCommentResultArray($dataset[11]),
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

        $expected = 'UPDATE comment '
            . 'SET child_count = child_count + 1 '
            . 'WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $expected = 'INSERT INTO comment '
            . '(level, parent_id, origin_id, target_id, text, date) '
            . 'VALUES '
            . '(?, ?, ?, ?, ?, ?)';
        $this->assertSame($expected, $this->pdoStub->getStatement(1));
    }

    public function testInsertOriginComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());
        $comment = new FooComment();

        $this->assertTrue($this->mapper->insert($comment));

        $expected = 'INSERT INTO comment '
            . '(level, child_count, target_id, text, date) '
            . 'VALUES '
            . '(?, ?, ?, ?, ?)';
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

        $expected = 'UPDATE comment SET '
            . 'text = ? '
            . 'WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));

        $comment->markChildFlag();
        $this->mapper->update($comment);

        $expected = 'UPDATE comment SET '
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

        $expected = 'DELETE FROM comment WHERE id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));
    }

    public function testDeleteOriginComment()
    {
        $this->pdoStub->setPdoStatement(new PDOStatementStub());
        $this->mapper->delete(new FooComment());

        $expected = 'DELETE FROM comment '
            . 'WHERE id = ? OR origin_id = ?';
        $this->assertSame($expected, $this->pdoStub->getStatement(0));
    }

    public function testCannotGetNextSearchKeyIfCommentsAreNotloaded()
    {
        $this->setExpectedException('LogicException', 'Comments have not load yet');
        $this->mapper->getNextSearchKey();
    }

    public function testGetNextSearchKey()
    {
        $stmtStub = new PDOStatementStub();
        $stmtStub->addResultSet($this->getOriginCommentResultSet(0, 10));
        $stmtStub->addResultSet($this->getChildCommentResultSet([17, 19]));

        $this->pdoStub->setPdoStatement($stmtStub);
        $comments = $this->mapper->findComments(1, 19, 10);

        $expected = array(
            'key'      => 9,
            'is_child' => false,
        );
        $this->assertSame($expected, $this->mapper->getNextSearchKey());
    }

    public function testGetNextSearchKeyWithNoMoreComments()
    {
        $stmtStub = new PDOStatementStub();
        $stmtStub->addResultSet($this->getChildCommentResultSet([19]));

        $this->pdoStub->setPdoStatement($stmtStub);
        $comments = $this->mapper->findComments(1, 20, 10, 19);

        $this->assertSame(null, $this->mapper->getNextSearchKey());
    }

    protected function getCommentResultArray($data)
    {
        if (is_null($data['origin_id'])) {
            return array('id' => $data['id']);
        } else {
            unset($data['child_count']);
            return $data;
        }
    }

    protected function getOriginCommentResultSet($offset, $length)
    {
        $dataset = self::$originCommentDataset;
        return array_slice(array_reverse($dataset), $offset, $length);
    }

    protected function getChildCommentResultSet(array $originKeys)
    {
        $dataset   = self::$childCommentDataset;
        $resultSet = array();
        foreach ($originKeys as $originKey) {
            array_walk($dataset, function($data) use (&$resultSet, $originKey) {
                if ($data['origin_id'] === $originKey) {
                    $resultSet[] = $data;
                }
            });
        }

        return $resultSet;
    }
}
