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

$this->assign('success', false);
if (array_key_exists('confirm1', $params) && array_key_exists('confirm2', $params)) {
    $client = $this->ClientId();
    $directory = cms_join_path($this->ClientPath(), 'export');
    // Import design XML
    ModuleOperations::get_instance()->get_module_instance('DesignManager');
    $import = dm_reader_factory::get_reader(cms_join_path($directory, 'design.xml'));
    $import->set_suggested_name($client);
    $import->import();
    $design = CmsLayoutCollection::load($client);
    $design->set_default(true);
    $design->save();
    // Copy uploaded files
    $src = cms_join_path($directory, 'uploads');
    $dst = cms_join_path(cmsms()->GetConfig()->offsetGet('uploads_path'), '.entities');
    rcopy($src, $dst);
    // Import content
    $db = $this->MySQL();
    $truncated = [];
    $fp = fopen(cms_join_path($directory, 'sql.json'), 'r');
    while ($line = fgets($fp)) {
        $record = json_decode($line);
        $table = array_shift($record);
        if (!in_array($table, $truncated, true)) {
            $truncated[] = $table;
            $db->query("TRUNCATE TABLE `#__$table`");
        }
        $query = sprintf('INSERT INTO `#__%s` VALUES(%s)', $table, implode(', ', array_fill(0, count($record), '?')));
        if ($table == 'content') {
            try {
                $template = CmsLayoutTemplate::load($record[5]);
                $record[5] = $template->get_id();
            } catch (Exception $e) {}
        }
        if ($table == 'content_props' && $record[2] == 'design_id') {
            try {
                $template = CmsLayoutCollection::load($record[6]);
                $record[6] = $template->get_id();
            } catch (Exception $e) {}
        }
        $db->query($query, $record);
    }
    fclose($fp);
    // Clear CMSMS cache
    cmsms()->clear_cached_files(-1);
    cms_route_manager::rebuild_static_routes();
    $this->assign('success', true);
}

echo $this->smarty->fetch($this->GetTemplateResource('admin.import.tpl'));
