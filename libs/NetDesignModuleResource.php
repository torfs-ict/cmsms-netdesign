<?php

namespace NetDesign;

/**
 * Smarty template resource.
 */
class NetDesignModuleResource extends \CMS_Fixed_Resource_Custom {
    private $module;
    public function __construct($module) {
        $this->module = $module;
    }
    protected function fetch($name, &$source, &$mtime) {
        $source = null;
        $mtime = null;

        $file = cms_join_path(\NetDesign::GetInstance()->ClientPath(), 'modules', $this->module, $name);
        if (!file_exists($file)) return;
        $source = @file_get_contents($file);
        $mtime = @filemtime($file);
    }
}