<?php

/** @var NetDesign $this */
if (!isset($gCms)) exit;

function startup_parse_value($value) {
    $module = NetDesign::GetInstance();
    if (fnmatch('<file:*>', $value)) {
        $ret = preg_match('/^<file:(?P<filename>.*)>$/', $value, $matches);
        if ($ret !== 1) return $value;
        $filename = cms_join_path($module->ClientPath(), $matches['filename']);
        if (!file_exists($filename)) return $value;
        return file_get_contents($filename);
    } elseif (fnmatch('<id:*>', $value)) {
        $ret = preg_match('/^<id:(?P<alias>.*)>$/', $value, $matches);
        if ($ret !== 1) return null;
        $content = ContentOperations::get_instance()->LoadContentFromAlias($matches['alias']);
        if (!$content instanceof ContentBase) return null;
        return (int)$content->Id();
    } elseif (fnmatch('<date:*>', $value)) {
        $ret = preg_match('/^<date:(?P<date>.*)>$/', $value, $matches);
        if ($ret !== 1) return null;
        return strtotime($matches['date']);
    }
    return $value;
}

if (defined('SKIP_ACTION')) return;

$filename = cms_join_path($this->ClientPath(), 'startup.json');
if (!file_exists($filename)) return;
$config = json_decode(file_get_contents($filename), true);
if (!is_array($config)) $config = [];
if (!array_key_exists('modules', $config) || !is_array($config['modules'])) $config['modules'] = [];
if (!array_key_exists('content', $config) || !is_array($config['content'])) $config['content'] = [];
if (!array_key_exists('routes', $config) || !is_array($config['routes'])) $config['routes'] = [];

if (array_key_exists('confirm1', $params) && array_key_exists('confirm2', $params)) {
    $log = [];

    foreach($config['modules'] as $module) {
        $ops = ModuleOperations::get_instance();
        $instance = $ops->get_module_instance($module, '', true);
        if (!$instance instanceof CMSModule) {
            $status = 'module does not exist';
        } elseif ($ops->IsModuleActive($module)) {
            $status = 'already installed';
        } else {
            $status = $ops->InstallModule($module);
            $status = $status[1];
        }
        $log[] = ['caption' => sprintf('Installing module <strong>%s</strong>', $module), 'status' => $status];
    }

    $ids = [];
    foreach($config['templates'] as $alias => $template) {
        $type = $template['type'];
        $name = sprintf('%s/%s', $this->ClientId(), $alias);
        $found = false;
        try {
            $instance = CmsLayoutTemplate::load($name);
            $found = true;
        } catch (CmsDataNotFoundException $e) {
            $instance = CmsLayoutTemplate::create_by_type($type);
        }
        $instance->set_name($name);
        $instance->set_content(startup_parse_value($template['template']));
        $instance->set_owner(get_userid(false));
        if (array_key_exists('default', $template) && cms_to_bool($template['default'] === true)) {
            $instance->set_type_dflt(true);
        }
        $instance->save();
        $ids[] = $instance->get_id();
        $log[] = ['caption' => sprintf('Creating %s template <strong>%s</strong>', $type, $name), 'status' => ($found ? 'updated' : 'created')];
    }

    $found = false;
    try {
        $design = CmsLayoutCollection::load($this->ClientId());
        $found = true;
    } catch (CmsDataNotFoundException $e) {
        $design = new CmsLayoutCollection();
        $design->set_name($this->ClientId());
    }
    $design->set_default(true);
    $design->set_templates($ids);
    $design->save();
    $log[] = ['caption' => sprintf('Creating design <strong>%s</strong>', $this->ClientId()), 'status' => ($found ? 'updated' : 'created')];

    $this->MySQL()->query("TRUNCATE TABLE `#__content`");
    $this->MySQL()->query("TRUNCATE TABLE `#__content_props`");
    $this->MySQL()->query("UPDATE `#__content_seq` SET `id` = 0");
    $ops = ContentOperations::get_instance();
    $log[] = ['caption' => 'Removing existing content', 'status' => 'success'];
    foreach($config['content'] as $alias => $content) {
        $type = $content['type'];
        $instance = $ops->CreateNewContent($type);
        $instance->SetParentId(-1);
        $instance->SetOwner(get_userid());
        $instance->SetAlias($alias);
        $instance->SetActive(true);
        if ($type == 'content') {
            $instance->SetShowInMenu(true);
            $instance->SetPropertyValue('design_id', $design->get_id());
            $instance->SetTemplateId(CmsLayoutTemplateType::load('Core::page')->get_dflt_template()->get_id());
        }
        foreach($content as $property => $value) {
            switch ($property) {
                case 'title':
                    $instance->SetName(startup_parse_value($value));
                    break;
                case 'menu':
                    $instance->SetMenuText(startup_parse_value($value));
                    $instance->SetShowInMenu(true);
                    break;
                case 'active':
                    $instance->SetActive(cms_to_bool(startup_parse_value($value)));
                    break;
                case 'parent':
                    $instance->SetParentId(startup_parse_value(sprintf('<id:%s>', $value)));
                    break;
                case 'home':
                    $instance->SetDefaultContent(cms_to_bool(startup_parse_value($value)));
                    break;
                case 'template':
                    $instance->SetTemplateId(CmsLayoutTemplate::load(sprintf('%s/%s', $this->ClientId(), startup_parse_value($value)))->get_id());
                    break;
                default:
                    if ($instance instanceof Entity && $instance->IsImageProperty($property)) {
                        /** @var Entity $instance */
                        if (is_string($value)) $value = array($value);
                        foreach ($value as $index => $src) {
                            $src = cms_join_path($this->ClientPath(), $src);
                            if (!file_exists($src)) continue;
                            $instance->UploadImage($property, $index + 1, basename($src), $src);
                        }
                    } elseif ($instance instanceof EntityImage && $property == 'image') {
                        /** @var EntityImage $instance */
                        $src = cms_join_path($this->ClientPath(), $value);
                        if (!file_exists($src)) break;
                        $instance->Save();
                        $instance->UploadImage(basename($src), $src);
                        $instance->FillParams(array('filename' => basename($src)), true);
                    } else {
                        $instance->SetPropertyValue($property, startup_parse_value($value));
                    }
                    break;
            }
        }
        if (empty($instance->MenuText())) $instance->SetMenuText($instance->Name());
        $instance->Save();
        cms_content_cache::add_content($instance->Id(), $instance->Alias(), $instance);
        $log[] = ['caption' => sprintf('Creating content&lt;%s&gt; <strong>%s</strong>', $type, $alias), 'status' => 'success'];
    }

    $this->ClientDoAction('startup', $id, []);
    $log[] = ['caption' => 'Executing client script', 'status' => 'success'];

    $ops->SetAllHierarchyPositions();
    cmsms()->clear_cached_files(-1);
    cms_route_manager::rebuild_static_routes();
    $log[] = ['caption' => 'Performing system maintenance and registering routes', 'status' => 'success'];

    $this->smarty->assign('log', $log);
    echo $this->smarty->fetch($this->GetTemplateResource('admin.startup.log.tpl'));
} else {
    if (array_key_exists('confirm0', $params)) {
        $theme = cms_utils::get_theme_object();
        $theme->ShowErrors('Operation was not confirmed properly.');
    }
    $this->smarty->assign('config', $config);
    echo $this->smarty->fetch($this->GetTemplateResource('admin.startup.tpl'));
}