<?php

/** @var NetDesign $this */
$this->RegisterSmartyPlugin('clientAction', 'function', 'PluginClientAction', false);
$this->RegisterSmartyPlugin('clientId', 'function', 'PluginClientId', false, cms_module_smarty_plugin_manager::AVAIL_ADMIN);
$this->RegisterSmartyPlugin('clientPath', 'function', 'PluginClientPath', false, cms_module_smarty_plugin_manager::AVAIL_ADMIN);
$this->RegisterSmartyPlugin('clientTemplate', 'function', 'PluginClientTemplate', false);
$this->RegisterSmartyPlugin('clientUrl', 'function', 'PluginClientUrl', false, cms_module_smarty_plugin_manager::AVAIL_ADMIN);
$this->RegisterSmartyPlugin('moduleUrl', 'function', 'PluginModuleUrl', false, cms_module_smarty_plugin_manager::AVAIL_ADMIN);
$this->RegisterSmartyPlugin('lang', 'block', 'PluginLang', false, cms_module_smarty_plugin_manager::AVAIL_ADMIN);
$this->RegisterSmartyPlugin('templateMeta', 'function', 'PluginTemplateMeta');
$this->AddEventHandler('Core', 'SmartyPreCompile');
$this->PermissionCreate(NetDesign::PERMISSION_SET_CLIENT, 'Set active client');
return false;