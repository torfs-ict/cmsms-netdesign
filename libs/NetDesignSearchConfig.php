<?php

namespace NetDesign;

use Exception;

/**
 * Configuration to have a database table included by our search engine.
 */
class NetDesignSearchConfig {
    private $recordClass;
    private $index = array();
    private $select = array();
    private $table;

    /**
     * @param string $table The name of the database table.
     * @param string $recordClass A subclass of NetDesignSearchRecord which will parse the raw database record.
     * @throws Exception When an invalid record class is given.
     */
    public function __construct($table, $recordClass) {
        if (!is_string($recordClass)) throw new Exception('Invalid record class given.');
        if (!is_a($recordClass, 'NetDesignSearchRecord', true)) throw new Exception(sprintf('%s is not a subclass of NetDesignSearchRecord.', (string)$recordClass));
        $this->recordClass = $recordClass;
        $this->table = $table;
    }

    /**
     * Tells our search engine that $fieldName (added by our Select method) needs to be searched.
     *
     * @param string $fieldName
     * @param int $exactWeight Weight when a field matches the search term exactly.
     * @param int $fullWeight Weight when the exact search term was found within a field.
     * @param int $wordWeight Weight when a word of the search term was found within a field.
     * @return $this
     * @throws Exception When the field was not yet included by Select().
     */
    public function Index($fieldName, $exactWeight = 3, $fullWeight = 2, $wordWeight = 1) {
        if (!array_key_exists($fieldName, $this->select)) throw new Exception(sprintf('Field %s is not available in our SELECT clause.', $fieldName));
        $this->index[$fieldName] = array('exactWeight' => $exactWeight, 'fullWeight' => $fullWeight, 'wordWeight' => $wordWeight);
        return $this;
    }

    /**
     * Includes a field in the SELECT clause. Optionally this can be a subquery (set in $subquery) which will be
     * aliased to $fieldName. If you need to bind parameters for your subquery this can be done by using $params.
     *
     * @param string $fieldName
     * @param null|string $subQuery
     * @param array $params
     * @return $this
     */
    public function Select($fieldName, $subQuery = null, $params = array()) {
        $this->select[$fieldName] = array('subquery' => $subQuery, 'params' => $params);
        return $this;
    }

    /**
     * Returns this configuration object as an array consumable by our search engine.
     *
     * @internal
     * @return array
     */
    public function ToArray() {
        return array(
            'index' => $this->index,
            'recordClass' => $this->recordClass,
            'select' => $this->select,
            'table' => $this->table
        );
    }
}