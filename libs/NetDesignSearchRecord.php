<?php

namespace NetDesign;

use Exception;

/**
 * Base class for a NetDesign search engine result.
 */
abstract class NetDesignSearchRecord {
    /**
     * @var int
     */
    private $score;
    /**
     * @var string
     */
    private $table;
    /**
     * @var array
     */
    protected $record = array();

    /**
     * Constructor.
     *
     * @param array $record
     */
    final public function __construct($record) {
        foreach($record as $field => $value) {
            switch($field) {
                case '__score':
                    $this->score = (int)$value;
                    break;
                case '__table':
                    $this->table = $value;
                    break;
                default:
                    $this->record[$field] = $value;
            }
        }
        $this->Process();
        return;
    }

    final public function __get($property) {
        if (!array_key_exists($property, $this->record)) throw new Exception(sprintf('Search result of type %s has no field %s.', get_class($this), $property));
    }

    final public function __set($property, $value) {
        throw new Exception('Search results are read-only.');
    }

    /**
     * Returns the search score for this record.
     *
     * @return int
     */
    final public function Score() {
        return $this->score;
    }

    /**
     * Returns the database table in which this record was found.
     *
     * @return string
     */
    final public function Table() {
        return $this->table;
    }

    /**
     * Allows extending classes to process $this->record.
     */
    abstract protected function Process();

    /**
     * Returns the URL corresponding to this record.
     *
     * @return string
     */
    abstract public function Url();
}