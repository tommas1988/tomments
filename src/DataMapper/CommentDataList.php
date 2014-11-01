<?php
namespace Tomments\DataMapper;

use Iterator;
use InvalidArgumentException;

class CommentDataList implements Iterator
{
    /**
     * The head of the comment data list
     * @var CommentDataNode
     */
    protected $head;

    /**
     * The rear of the comment data list
     * @var CommentDataNode
     */
    protected $rear;

    /**
     * The storage of the comment data nodes
     * @var CommentDataNode[]
     */
    protected $nodes;

    /**
     * The number of the comment data
     * @var int
     */
    protected $num;

    /**
     * The key of the comment data to start the iteration
     * @var int
     */
    protected $start;

    /**
     * The length of the comment data that need to be traversed
     * @var int
     */
    protected $length;

    /**
     * The current comment data node
     * @var CommentDataNode
     */
    protected $current;

    /**
     * The number of left comment data that need to be traversed
     * @var int
     */
    protected $count;

    /**
     * Constructor
     * Create an empty list
     */
    public function __construct()
    {
        $this->head = new CommentDataNode();
        $this->num  = 0;
    }

    /**
     * Whether the list is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return 0 == $this->num;
    }

    /**
     * Insert a comment data to the list
     *
     * @param  array data Comment data
     * @return self
     */
    public function insert(array $data)
    {
        $node = new CommentDataNode($data);

        $key       = $node->key;
        $parentKey = $node->parentKey;

        /* Ascent order child comment */
        // if (!$parentKey || !isset($this->nodes[$parentKey])) {
        //     // If is origin comment data or parent comment data doesn`t
        //     // set before, append the comment data to the list

        //     if (!$this->head->next) {
        //         $this->head->next = $this->rear = $node;
        //     } else {
        //         $this->rear->next = $node;
        //         $this->rear       = $node;
        //     }
        // } else {
        //     $originKey = $node->originKey;
        //     $level     = $node->level;

        //     $prevNode = $this->nodes[$parentKey];
        //     while (
        //         $prevNode !== $this->rear
        //         && $originKey === $prevNode->next->originKey
        //         && ($prevNode->next->key === $node->parentKey
        //         || $prevNode->next->key !== $node->parentKey && $level <= $prevNode->next->level)
        //     ) {
        //         $prevNode = $prevNode->next;
        //     }
        //     $node->next     = $prevNode->next;
        //     $prevNode->next = $node;

        //     if ($prevNode === $this->rear) {
        //         $this->rear = $node;
        //     }
        // }

        /* Descent order child comment */
        if (!$parentKey || !isset($this->nodes[$parentKey])) {
            // If is origin comment data or parent comment data doesn`t
            // set before, append the comment data to the list

            if (!isset($this->nodes[$parentKey])) {
                $this->nodes[$parentKey] = $this->head;
            }

            if (!$this->head->next) {
                $this->head->next = $this->rear = $node;
            } else {
                $this->rear->next = $node;
                $this->rear       = $node;
            }
        } else {
            $originKey = $node->originKey;
            $level     = $node->level;

            $prevNode       = $this->nodes[$parentKey];
            $node->next     = $prevNode->next;
            $prevNode->next = $node;

            if ($prevNode === $this->rear) {
                $this->rear = $node;
            }
        }
        $this->nodes[$key] = $node;
        $this->num++;

        return $this;
    }

    /**
     * Get the offset of comment data
     *
     * @param  int key The comment data key
     * @return int
     * @throws InvalidArgumentException If cannot find comment data
     */
    public function getOffset($key)
    {
        if (!isset($this->nodes[$key])) {
            throw new InvalidArgumentException(sprintf(
                'Comment data with key: %d doesn`t exist', $key));
        }

        $offset = 0;
        $node   = $this->head->next;
        while ($node && $node->key != $key) {
            $node = $node->next;
            $offset++;
        }

        return $offset;
    }

    /**
     * Get next comment key info for the given comment key
     *
     * @param  int key The current comment key
     * @return array|null
     * @throws InvalidArgumentException If provided comment key is not set
     */
    public function getNextCommentKey($key)
    {
        if (!isset($this->nodes[$key])) {
            throw new InvalidArgumentException(sprintf(
                'The comment data: %s is not loaded', $key));
        }

        $node = $this->nodes[$key];
        if ($node->next) {
            return array(
                'search_key' => $node->next->key,
                'origin_key' => $node->next->originKey,
            );
        } else {
            return null;
        }
    }

    /**
     * Set iteration context
     *
     * @param  int start The key of comment data to start the iteration
     * @param  int length The length of comment data need to iterate
     * @return self
     * @throws InvalidArgumentException If start comment data dosen`t exist in the list
     * @throws InvalidArgumentException If length is not int or greater than 0
     */
    public function setIterationContext($start, $length)
    {
        if (!isset($this->nodes[$start])) {
            throw new InvalidArgumentException(sprintf(
                'Data node: %d dose not exist', $start));
        }
        if (!is_int($length) || $length <= 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid length: %s', var_export($length, 1)));
        }

        $this->start  = $start;
        $this->length = $length < $this->num ? $length : $this->num;

        return $this;
    }

    /**
     * Return current comment data
     *
     * @return array The comment data
     */
    public function current()
    {
        return $this->current->data;
    }

    /**
     * Return the key of current comment data
     *
     * @return key
     */
    public function key()
    {
        return $this->current->key;
    }

    /**
     * Move forward to the next comment data
     */
    public function next()
    {
        $this->current = $this->current->next;
        $this->count--;
    }

    /**
     * Rewind to the first comment data
     */
    public function rewind()
    {
        if ($this->start) {
            $this->current = $this->nodes[$this->start];
        } else {
            $this->current = $this->head->next;
        }

        $this->count = $this->length ?: $this->num;
    }

    /**
     * Checks if the current comment data is valid
     *
     * @return bool
     */
    public function valid()
    {
        return $this->current && $this->count > 0;
    }
}

class CommentDataNode
{
    /**
     * The comment data
     * @var array
     */
    public $data;

    /**
     * Whether is child comment data
     * @var bool
     */
    public $isChild;

    /**
     * Comment data key
     * @var int
     */
    public $key;

    /**
     * Comment data level
     * @var int
     */
    public $level;

    /**
     * The parent comment data key
     * @var int
     */
    public $parentKey;

    /**
     * The origin comment data key
     * @var int
     */
    public $originKey;

    /**
     * The next comment data node
     * @var CommentDataNode
     */
    public $next;

    /**
     * Constructor
     *
     * @param array|null comment data
     */
    public function __construct(array $data = null)
    {
        if ($data) {
            $this->init($data);
            $this->data = $data;
        }
    }

    /**
     * Initialize instance variables
     *
     * @param array comment data
     */
    protected function init(array $data)
    {
        $this->key     = $data['id'];
        $this->isChild = false;
        if (isset($data['parent_id'])) {
            $this->isChild   = true;
            $this->level     = $data['level'];
            $this->parentKey = $data['parent_id'];
            $this->originKey = $data['origin_id'];
        }
    }
}
