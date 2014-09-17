<?php
namespace Tomments\Test;

use Tomments\CommentManager;
use Tomments\Test\TestAsset\FooCommentMapper;
use Tomments\Test\TestAsset\FooComment;

class CommentManagerTest extends \PHPUnit_Framework_TestCase
{
    protected $mapperStub;
    protected $manager;

    public function setUp()
    {
        $this->mapperStub = $this->getMockBuilder('Tomments\Test\TestAsset\FooCommentMapper')
            ->disableOriginalConstructor()
            ->getMock();

        $this->manager = new CommentManager(array(
            'comment' => new FooComment(),
            'mapper'  => $this->mapperStub,
        ));
    }

    public function testGetComments()
    {
        $this->mapperStub->expects($this->any())
            ->method('findComments')
            ->will($this->returnValue(array(
                new FooComment(),
                new FooComment(),
            )));

        $result = $this->manager->getComments(1, 2);
        $this->assertContainsOnlyInstancesOf(
            'Tomments\Comment\CommentInterface', $result);
    }

    public function testAddComment()
    {
        $this->mapperStub->expects($this->any())
            ->method('insert')
            ->will($this->returnValue(true));

        $result = $this->manager->addComment(array());
        $this->assertTrue($result);
    }

    public function testUpdateComment()
    {
        $this->mapperStub->expects($this->any())
            ->method('update')
            ->will($this->returnValue(true));

        $result = $this->manager->updateComment(1, array());
        $this->assertTrue($result);
    }

    public function testDeleteComment()
    {
        $this->mapperStub->expects($this->any())
            ->method('delete')
            ->will($this->returnValue(true));

        $result = $this->manager->deleteComment(1);
        $this->assertTrue($result);
    }

    public function testGetCommentPrototype()
    {
        $this->assertInstanceOf(
            'Tomments\Comment\CommentInterface',
            $this->manager->getCommentPrototype());
    }
}
