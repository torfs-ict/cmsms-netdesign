<?php

/** @var NetDesign $this */
use NetDesign\NetDesignSearchRecord;

if (!isset($gCms)) exit;

class ContentRecord extends NetDesignSearchRecord {
    /**
     * @var ContentBase
     */
    public $content;
    /**
     * Allows extending classes to process $this->record.
     */
    protected function Process() {
        $this->content = ContentOperations::get_instance()->LoadContentFromId($this->record['content_id']);
    }

    /**
     * Returns the URL corresponding to this record.
     *
     * @return string
     */
    public function Url()
    {
        return $this->content->GetURL();
    }

}

class ModuleRecord extends NetDesignSearchRecord {
    public $name;
    public $status;
    public $adminOnly;
    private $module;
    /**
     * Allows extending classes to process $this->record.
     */
    protected function Process() {
        $this->name = $this->record['module_name'];
        $this->adminOnly = (bool)$this->record['admin_only'];
        $this->status = $this->record['status'];
    }

    /**
     * Returns the URL corresponding to this record.
     *
     * @return string
     */
    public function Url() {
        return ModuleOperations::get_instance()->get_module_instance($this->name, '', true)->GetModuleURLPath();
    }

}

$content = new NetDesignSearchConfig('#__content', 'ContentRecord');
$content->Select('content_id')->Select('content_name')->Select('menu_text')->Index('content_name')->Index('menu_text');
$content->Select('module_name', 'SELECT ?', array('dummy!'));
$content->Select('content',
    'SELECT `#__content_props`.`content` FROM `#__content_props` WHERE `#__content`.`content_id` = `#__content_props`.`content_id` AND `#__content_props`.`prop_name` = ?',
    array('content_en')
)->Index('content');
$modules = new NetDesignSearchConfig('#__modules', 'ModuleRecord');
$modules->Select('module_name')->Select('status')->Select('admin_only')->Index('module_name')->Index('status');

$term = 'Home install';
$search = new NetDesignSearchEngine(array($modules, $content));
$max = 0;
$total = $search->Count($term);
$records = $search->Search($term, $max);
printf('<p>Found %d records, with a maximum score of %d</p><ol>', $total, $max);
foreach($records as $record) {
    if ($record instanceof ContentRecord) {
        printf('<li><a href="%s" target="_blank">%s with alias %s: %d</a></li>', $record->Url(), get_class($record), $record->content->Alias(), $record->Score());
    } elseif ($record instanceof ModuleRecord) {
        printf('<li><a href="%s" target="_blank">%s with name %s: %d</a></li>', $record->Url(), get_class($record), $record->name, $record->Score());
    }
}
echo '</ol>';