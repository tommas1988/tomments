<?php
use Tomments\Comment\AbstractComment;

class Comment extends AbstractComment
{
    public $content;
    public $time;

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray()
    {
        $return = array(
            'key'     => $this->getKey(),
            'content' => $this->content,
            'time'    => $this->time,
        );

        $level = $this->getLevel();
        $return['level'] = $level;
        if (0 !== $level) {
            $return['parentKey'] = $this->getParentKey();
            $return['originKey'] = $this->getOriginKey();
        }

        if (!empty($this->children)) {
            $return['children'] = array();
            foreach ($this->children as $child) {
                $return['children'][] = $child->toArray();
            }
        }

        return $return;
    }

    /**
     * Implementation of AbstractComment::doLoad
     * @see AbstractComment::doLoad
     */
    protected function doLoad(array $params)
    {
        if (isset($params['content'], $params['time'])) {
            $this->content = $params['content'];
            $this->time    = $params['time'];
        }
    }
}
