<?php

namespace NetDesign;

/**
 * Classes which represent a database table may (but are not obliged) to implement this interface.
 */
interface NetDesignSearchable {
    /**
     * @return string A subclass of NetDesignSearchRecord.
     */
    public static function SearchRecordClass();
    /**
     * @return NetDesignSearchConfig
     */
    public static function SearchConfig();
}