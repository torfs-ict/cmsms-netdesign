<?php

namespace NetDesign;

/**
 * Singleton base class
 */
abstract class Singleton {
    /**
     * Object instance
     * @var static
     */
    private static $instance;

    /**
     * Class constructor, can be overridden.
     */
    abstract protected function __construct();

    /**
     * Object cloning is disabled.
     */
    final private function __clone() {}

    /**
     * Returns the singleton instance of this class
     *
     * @return static
     */
    final public static function Instance() {
        if (!Singleton::$instance instanceof static) {
            Singleton::$instance = new static();
        }
        return Singleton::$instance;
    }
}