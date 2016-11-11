<?php

use PDO;

/**
 * NetDesignConnection class file.
 */

namespace NetDesign;
use PDO;

/**
 * PDO extension class.
 *
 * @author Kristof Torfs <kristof@torfs.org>
 */
class NetDesignConnection extends PDO {
    /**
     * Optional NetDesign client id. Developers can this way simply use ##__MyTableName in a query.
     *
     * @var string
     */
    public $client = null;
    /**
     * Optional table prefix. Developers can this way simply use #__MyTableName in a query.
     *
     * @var string
     */
    public $prefix = null;

    /**
     * Constructor.
     *
     * @param string $dsn The Data Source Name, or DSN, containing the information required to connect to the database.
     * @param string $username The user name for the DSN string.
     * @param string $password The password for the DSN string.
     * @param array $options An associative array of driver-specific connection options.
     */
    public function __construct($dsn, $username, $password, $options = null) {
        if (fnmatch('mysql*', $dsn)) {
            // Default options for MySQL
            $default = array(
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
            );
        } else {
            $default = array();
        }
        // Generate true options array
        if (!is_array($options)) $options = $default;
        $options = array_merge($default, $options);
        parent::__construct($dsn, $username, $password, $options);
        // Set some mandatory properties
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('NetDesignStatement', array($this)));
    }

    /**
     * Interpolate a statement.
     *
     * If only a single array is given for the parameters, every value will be used as a
     * separate parameter.
     *
     * To insert the CMSMS database prefix just use #__ in your query.
     * To insert a combination of the CMSMS database prefix and the NetDesign client id use ##__ in your query.
     *
     * @param string $query A valid MySQL statement.
     * @param mixed|array ...$param unlimited OPTIONAL number of parameters
     * @return string
     */
    public function interpolate($statement) {
        $query = str_replace(['##__', '#__'], [(string)$this->client, (string)$this->prefix], $statement);
        $params = func_get_args();
        array_shift($params);
        reset($params);
        if ( (count($params) == 1) && (is_array(current($params))) ) {
            $params = current($params);
        }
        $stmt = $this->prepare($query);
        if (!$stmt) return false;
        return $stmt->interpolateQuery($params);
    }

    /**
     * Prepares a statement for execution and returns a statement object. This will also make sure that
     * the database prefix gets inserted.
     *
     * @param string $statement A valid MySQL statement.
     * @param array $driver_options This array holds one or more key=>value pairs to set attribute values for the PDOStatement object that this method returns. You would most commonly use this to set the PDO::ATTR_CURSOR value to PDO::CURSOR_SCROLL to request a scrollable cursor. Some drivers have driver specific options that may be set at prepare-time.
     * @return NetDesignStatement
     */
    public function prepare($statement, $driver_options = array()) {
        $statement = str_replace(['##__', '#__'], [(string)$this->client, (string)$this->prefix], $statement);
        return parent::prepare($statement, $driver_options);
    }

    /**
     * Function to quickly prepare and execute a statement.
     *
     * If only a single array is given for the parameters, every value will be used as a
     * separate parameter.
     *
     * To insert the CMSMS database prefix just use #__ in your query.
     * To insert a combination of the CMSMS database prefix and the NetDesign client id use ##__ in your query.
     *
     * @param string $query A valid MySQL statement.
     * @param mixed|array ...$param unlimited OPTIONAL number of parameters
     * @return NetDesignStatement
     */
    public function query($query) {
        $query = str_replace(['##__', '#__'], [(string)$this->client, (string)$this->prefix], $query);
        $params = func_get_args();
        array_shift($params);
        reset($params);
        if ( (count($params) == 1) && (is_array(current($params))) ) {
            $params = current($params);
        }
        $stmt = $this->prepare($query);
        if (!$stmt) return false;
        $stmt->execute($params);
        return $stmt;
    }
}
