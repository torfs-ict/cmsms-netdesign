<?php

/** @var NetDesign $this */
/** @var array $params */
if (!isset($gCms)) exit;

/**
 * Recursively copy files from one directory to another
 *
 * @param String $src - Source of files being moved
 * @param String $dest - Destination of files being moved
 * @return bool
 */
function rcopy($src, $dest){

    // If source is not a directory stop processing
    if(!is_dir($src)) return false;

    // If the destination directory does not exist create it
    if(!is_dir($dest)) {
        if(!mkdir($dest, 0775, true)) {
            // If the destination directory could not be created stop processing
            return false;
        }
    }

    // Open the source directory to read in files
    $i = new DirectoryIterator($src);
    foreach($i as $f) {
        if($f->isFile()) {
            copy($f->getRealPath(), "$dest/" . $f->getFilename());
        } else if(!$f->isDot() && $f->isDir()) {
            rcopy($f->getRealPath(), "$dest/$f");
        }
    }
}

/**
 * Recursively remove a directory
 *
 * @param String $dir
 * @return bool
 */
function runlink($dir){

    // If source is not a directory stop processing
    if(!is_dir($dir)) return false;

    // Open the source directory to read in files
    $i = new DirectoryIterator($dir);
    foreach($i as $f) {
        if($f->isFile()) {
            unlink($f->getRealPath());
        } else if(!$f->isDot() && $f->isDir()) {
            runlink($f->getRealPath());
        }
    }
    rmdir($dir);
}

$this->assign('success', false);
$client = $this->ClientId();
$modules = ModuleOperations::get_instance()->GetAllModuleNames();
array_walk($modules, function(&$item) {
    if (in_array($item, [
        'AdminSearch', 'CMSContentManager', 'DesignManager', 'FileManager', 'MenuManager', 'MicroTiny', 'ModuleManager',
        'Navigator', 'News', 'Search'
    ])) $item = null;
});
$this->assign('modules', $modules);

if (array_key_exists('confirm', $params)) {
    $directory = cms_join_path($this->ClientPath(), 'export');
    // Remove existing export directory
    runlink($directory);
    // Create export directory
    @mkdir($directory, 0775, true);
    // Export design XML
    ModuleOperations::get_instance()->get_module_instance('DesignManager');
    $design = CmsLayoutCollection::load($client);
    $export = new dm_design_exporter($design);
    file_put_contents(cms_join_path($directory, 'design.xml'), $export->get_xml());
    // Export content SQL
    $tables = ['content', 'content_props', 'content_props_seq', 'content_seq'];
    $db = $this->MySQL();
    $fp = fopen(cms_join_path($directory, 'sql.json'), 'w');
    foreach($tables as $table) {
        $stmt = $db->query("SELECT * FROM `#__$table` WHERE 1");
        while ($record = $stmt->fetch(PDO::FETCH_NUM)) {
            if ($table == 'content') {
                try {
                    // owner_id
                    $record[3] = 1;
                    // template_id
                    $template = CmsLayoutTemplate::load($record[5]);
                    $record[5] = $template->get_name();
                } catch (Exception $e) {}
            }
            if ($table == 'content_props' && $record[2] == 'design_id') {
                try {
                    $design = CmsLayoutCollection::load($record[6]);
                    $record[6] = $design->get_name();
                } catch (Exception $e) {}
            }
            array_unshift($record, $table);
            fwrite($fp, json_encode($record) . "\n");
        }
    }
    fclose($fp);
    // Export uploaded files
    $uploads = cms_join_path(cmsms()->GetConfig()->offsetGet('uploads_path'), '.entities');
    $dir = new RecursiveDirectoryIterator($uploads);
    $ite = new RecursiveIteratorIterator($dir);
    $regex = '/\.entities\/(?P<id>\d+)\/(?P<property>.+)\/(?P<index>\d+)\/(?P<type>original|thumbnail)\/(?P<filename>.+)$/';
    $files = new RegexIterator($ite, $regex);
    foreach($files as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        $base = $file->getBasename();
        $src = $file->getRealPath();
        preg_match($regex, $src, $matches);
        $dst = cms_join_path($directory, 'uploads', $matches['id'], $matches['property'], $matches['index'], $matches['type'], $matches['filename']);
        // Check if the file is actually used
        $count = (int)$db->query("SELECT COUNT(*) FROM `#__content_props` WHERE `content_id` = ? AND `prop_name` = ? AND `content` LIKE ?", $matches['id'], $matches['property'], sprintf('%%%s%%', serialize($matches['filename'])))->fetchColumn();
        if ($count == 0) continue;
        @mkdir(dirname($dst), 0775, true);
        copy($src, $dst);
    }
    $this->assign('success', true);
}

echo $this->smarty->fetch($this->GetTemplateResource('admin.export.tpl'));