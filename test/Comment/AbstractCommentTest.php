<?php
namespace Tomments\Test\Comment;

use Tomments\Test\TestAsset\FooComment;

class AbstractCommentTest extends \PHPUnit_Framework_TestCase
{
    protected $comment;

    public function setUp()
    {
        $this->comment = new FooComment();
    }

    public function testGetKeyReturnFalseIfNotSet()
    {
        $this->assertFalse($this->comment->getKey());
    }

    public function testGetLevelReturnZeroIfNotSet()
    {
        $this->assertEquals(0, $this->comment->getLevel());
    }

    public function testGetParentKeyReturnFalseIfNotSet()
    {
        $this->assertFalse($this->comment->getParentKey());
    }

    public function testGetOriginKeyReturnFalseIfNotSet()
    {
        $this->assertFalse($this->comment->getOriginKey());
    }

    public function testIsNotChildByDefault()
    {
        $this->assertFalse($this->comment->isChild());
    }
}
