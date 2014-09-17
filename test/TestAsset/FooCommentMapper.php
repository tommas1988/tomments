<?php
namespace Tomments\Test\TestAsset;

use PDO;
use Tomments\DataMapper\AbstractCommentMapper;

class FooCommentMapper extends AbstractCommentMapper
{
    public function __construct(
        PDO $db, $setColumnMapper = true,
        $setUpdatableColumnMapper = true
    ) {
        if ($setColumnMapper) {
            $this->columnMapper = array(
                'text' => 'text',
                'date' => 'date',
            );
        }

        if ($setUpdatableColumnMapper) {
            $this->updatableColumnMapper = array(
                'text' => 'text',
            );
        }

        parent::__construct($db, 'target_id');
    }

    public function setUpdatableColumnMapper(array $columnMapper)
    {
        $this->updatableColumnMapper = $columnMapper;
    }
}
