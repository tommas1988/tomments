<?php
namespace Tomments;

interface InjectCommentManagerInterface
{
    /**
     * Set comment manager
     *
     * @param  CommentManager commentManager
     * @return self
     */
    public function setCommentManager(CommentManager $commentManager);

    /**
     * Get CommentManager instance
     *
     * @return CommentManager
     */
    public function getCommentManager();
}
