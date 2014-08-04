<?php
namespace Tomments\Test\TestAsset;

use Tomments\Comment\AbstractComment;

class FooComment extends AbstractComment
{
    public $text = 'comment text';
    public $date = '00:00:00';

    protected $params;

    protected function doLoad(array $params)
    {
        $this->params = $params;
    }

    public function toArray()
    {
        $result = array(
            'id' => $this->getKey(),
        );

        if ($this->isChild()) {
            $result['level']     = $this->getLevel();
            $result['parent_id'] = $this->getParentKey();
            $result['origin_id'] = $this->getOriginKey();
        }

        $result = array($result);
        if ($this->hasChildren()) {
            foreach ($this->getChildren() as $child) {
                $result = array_merge($result, $child->toArray());
            }
        }

        return $result;
    }
}
