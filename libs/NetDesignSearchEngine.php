<?php

namespace NetDesign;

use Exception;
use NetDesign;
use PDO;

class NetDesignSearchEngine {
    private $config;
    private $limit = null;
    private $minWordLength;
    private $offset = null;
    private $orderBy = null;
    private $sql = array();
    private $params = array();
    /**
     * @param NetDesignSearchConfig|NetDesignSearchConfig[] $config
     * @param int $minWordLength The minimum length of the words in our search term. Words with less characters will be ignored.
     * @throws Exception If one or more passed items are not a NetDesignSearchConfig instance.
     */
    public function __construct($config, $minWordLength = 3) {
        $this->minWordLength = (int)$minWordLength;
        if ($config instanceof NetDesignSearchConfig) $config = array($config);
        $this->config = array();
        /** @var NetDesignSearchConfig[] $config */
        foreach($config as $index => $object) {
            if (!($object instanceof NetDesignSearchConfig)) throw new Exception(sprintf('Search configuration #%d is not a valid NetDesignSearchConfig object.', $index + 1));
            $array = $object->ToArray();
            $table = $array['table'];
            unset($array['table']);
            $this->config[$table] = $array;
        }
    }

    /**
     * Returns the total amount of results for a search term.
     *
     * @param string $term The word or phrase to search for.
     * @return int
     */
    public function Count($term) {
        if (!array_key_exists($term, $this->sql)) $this->Sql($term);
        $db = NetDesign::GetInstance()->MySQL();
        return (int)$db->query(sprintf('SELECT COUNT(*) FROM (%s) AS `query` WHERE `__score` > 0', $this->sql[$term]), $this->params)->fetchColumn();
    }

    /**
     * Returns the records found when searching for a term.
     *
     * @param string $term The word or phrase to search for.
     * @param int $maxScore Variable which will be set to the highest score.
     * @return NetDesignSearchRecord[]
     */
    public function Search($term, &$maxScore) {
        if (!array_key_exists($term, $this->sql)) $this->Sql($term);
        $db = NetDesign::GetInstance()->MySQL();
        // Max score
        $maxScore = (int)$db->query(sprintf('SELECT MAX(`__score`) FROM (%s) AS `query` WHERE `__score` > 0', $this->sql[$term]), $this->params)->fetchColumn();
        // Perform query
        $query = array($this->sql[$term], 'HAVING `__score` > 0');
        if (!empty($this->orderBy)) $query[] = sprintf('ORDER BY %s', $this->orderBy);
        else $query[] = 'ORDER BY `__score` DESC';
        if (!empty($this->limit)) $query[] = sprintf('LIMIT %d, %d', $this->offset, $this->limit);
        $query = implode(' ', $query);
        $stmt = $db->query($query, $this->params);
        $ret = array();
        // Create NetDesignSearchRecord
        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Unset all fields which aren't in this table
            $config = $this->config[$record['__table']];
            foreach($record as $key => $value) {
                if (in_array($key, array('__table', '__score'))) continue;
                if (array_key_exists($key, $config['select'])) continue;
                unset($record[$key]);
            }
            $cls = $config['recordClass'];
            $ret[] = new $cls($record);
        }
        return $ret;
    }

    private function Sql($term) {
        // Split our search term into keywords
        $query = trim($term);
        $query = trim(preg_replace("/(\\s+)+/", " ", $query));
        $keywords = array_unique(explode(' ', $query));

        // Array containing a list of all field names
        $all = array();
        // Get a full list of field names
        foreach($this->config as $item) {
            $all = array_merge($all, array_keys($item['select']));
        }
        $all = array_unique($all);
        // Generate the SQL
        $unions = array();
        $params = array();
        foreach($this->config as $table => $item) {
            $selects = array('? AS `__table`');
            $params[] = $table;
            $ifs = array();
            // Select clause
            foreach($all as $fieldName) {
                if (!array_key_exists($fieldName, $item['select'])) {
                    $selects[] = sprintf('NULL AS `%s`', $fieldName);
                    continue;
                } elseif (empty($item['select'][$fieldName]['subquery'])) {
                    $selects[] = sprintf('`%s`', $fieldName);
                } else {
                    $selects[] = sprintf('(%s) AS `%s`', $item['select'][$fieldName]['subquery'], $fieldName);
                    $params = array_merge($params, $item['select'][$fieldName]['params']);
                }
            }
            foreach($all as $fieldName) {
                // Search score
                if (!array_key_exists($fieldName, $item['index'])) continue;
                if (empty($item['select'][$fieldName]['subquery'])) {
                    // Exact match
                    $ifs[] = "IF(LOWER(`{$fieldName}`) = LOWER(?), ?, 0)";
                    $params[] = $query;
                    $params[] = $item['index'][$fieldName]['exactWeight'];
                    // Matching full occurrences
                    if (count($keywords) > 1) {
                        $ifs[] = "IF(LOWER(`{$fieldName}`) LIKE LOWER(?), ROUND((LENGTH(`{$fieldName}`) - LENGTH(REPLACE(LOWER(`{$fieldName}`), LOWER(?), ''))) / LENGTH(?)) * ?, 0)";
                        $params[] = '%' . $query . '%';
                        $params[] = $query;
                        $params[] = $query;
                        $params[] = $item['index'][$fieldName]['fullWeight'];
                    }
                    // Matching keywords
                    foreach($keywords as $kw) {
                        if (strlen($kw) < $this->minWordLength) continue;
                        $ifs[] = "IF(LOWER(`{$fieldName}`) LIKE LOWER(?), ROUND((LENGTH(`{$fieldName}`) - LENGTH(REPLACE(LOWER(`{$fieldName}`), LOWER(?), ''))) / LENGTH(?)) * ?, 0)";
                        $params[] = '%' . $kw . '%';
                        $params[] = $kw;
                        $params[] = $kw;
                        $params[] = $item['index'][$fieldName]['wordWeight'];
                    }
                } else {
                    // Exact match
                    $subquery = sprintf('(%s)', $item['select'][$fieldName]['subquery']);
                    $subparams = $item['select'][$fieldName]['params'];
                    $ifs[] = "IF(LOWER({$subquery}) = LOWER(?), ?, 0)";
                    $params = array_merge($params, $subparams);
                    $params[] = $query;
                    $params[] = $item['index'][$fieldName]['exactWeight'];
                    // Matching full occurrences
                    if (count($keywords) > 1) {
                        $ifs[] = "IF(LOWER({$subquery}) LIKE LOWER(?), ROUND((LENGTH({$subquery}) - LENGTH(REPLACE(LOWER({$subquery}), LOWER(?), ''))) / LENGTH(?)) * ?, 0)";
                        $params = array_merge($params, $subparams);
                        $params[] = '%' . $query . '%';
                        $params = array_merge($params, $subparams);
                        $params = array_merge($params, $subparams);
                        $params[] = $query;
                        $params[] = $query;
                        $params[] = $item['index'][$fieldName]['fullWeight'];
                    }
                    // Matching keywords
                    foreach($keywords as $kw) {
                        if (strlen($kw) < $this->minWordLength) continue;
                        $ifs[] = "IF(LOWER({$subquery}) LIKE LOWER(?), ROUND((LENGTH({$subquery}) - LENGTH(REPLACE(LOWER({$subquery}), LOWER(?), ''))) / LENGTH(?)) * ?, 0)";
                        $params = array_merge($params, $subparams);
                        $params[] = '%' . $kw . '%';
                        $params = array_merge($params, $subparams);
                        $params = array_merge($params, $subparams);
                        $params[] = $kw;
                        $params[] = $kw;
                        $params[] = $item['index'][$fieldName]['wordWeight'];
                    }
                }
            }
            $unions[] = sprintf('SELECT %s, %s AS `__score` FROM `%s`', implode(', ', $selects), (empty($ifs) ? '0' : implode(' + ', $ifs)), $table);
        }
        $this->sql[$term] = implode(' UNION ', $unions);
        $this->params = $params;
    }

    /**
     * Constrains the number of records returned.
     *
     * @param int $limit Maximum number of records.
     * @param int $offset Offset of the the first record to return.
     * @return $this
     */
    public function Limit($limit, $offset = 0) {
        $this->limit = (int)$limit;
        $this->offset = (int)$offset;
    }

    /**
     * The ORDER BY clause of our query (without the "ORDER BY"). Defaults to the search score in descending order. If
     * you want include the search score you can do so by referencing the field __score. Another field which we add is
     * the field __table which contains the database table name for each record.
     *
     * @param null $by
     * @return $this
     */
    public function OrderBy($by = null) {
        $this->orderBy = $by;
    }
}