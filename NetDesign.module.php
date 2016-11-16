<?php

use NetDesign\NetDesignModule;

class NetDesign extends NetDesignModule {
    /**
     * Module permission: set active client.
     */
    const PERMISSION_SET_CLIENT = 'set_client';
    #region Standard module methods
    public function GetAdminMenuItems() {
        $ret = [];
        if (!UserOperations::get_instance()->IsSuperuser(get_userid(false))) return $ret;
        // Website startup
        $obj = CmsAdminMenuItem::from_module($this);
        $obj->section = 'siteadmin';
        $obj->title = 'Initialize website';
        $obj->action = 'startup';
        $obj->url = $this->create_url('m1_', $obj->action);
        $ret[] = $obj;
        // Website export
        $obj = CmsAdminMenuItem::from_module($this);
        $obj->section = 'siteadmin';
        $obj->title = 'Export website';
        $obj->action = 'export';
        $obj->url = $this->create_url('m1_', $obj->action);
        $ret[] = $obj;
        // Website export
        $obj = CmsAdminMenuItem::from_module($this);
        $obj->section = 'siteadmin';
        $obj->title = 'Import website';
        $obj->action = 'import';
        $obj->url = $this->create_url('m1_', $obj->action);
        $ret[] = $obj;
        // All done
        return $ret;
    }
    public function GetDependencies() {
        return array();
    }
    public function GetVersion() {
        return '1.0.3';
    }
    public function HasAdmin() {
        return true;
    }
    public function Initialize() {
        // Auto load the client language file
        $this->LangIncludeClient();
    }
    public function InitializeAdmin() {
        // Workaround to load our Smarty plugins for the admin interface instead of just for the frontend
        $this->smarty->registerPlugin('function', 'clientAction', 'NetDesign::PluginClientAction');
        $this->smarty->registerPlugin('function', 'clientId', 'NetDesign::PluginClientId');
        $this->smarty->registerPlugin('function', 'clientPath', 'NetDesign::PluginClientPath');
        $this->smarty->registerPlugin('function', 'clientTemplate', 'NetDesign::PluginClientTemplate');
        $this->smarty->registerPlugin('function', 'clientUrl', 'NetDesign::PluginClientUrl');
        $this->smarty->registerPlugin('function', 'moduleUrl', 'NetDesign::PluginModuleUrl');
        $this->smarty->registerPlugin('block', 'lang', 'NetDesign::PluginLang');
        $this->smarty->registerPlugin('function', 'templateMeta', 'NetDesign::PluginTemplateMeta');
        // We also need to manually add a prefilter so the SmartyPreCompile event gets called from the admin interface
        $this->smarty->registerFilter('pre', array($this, 'AdminPreFilter'));
    }
    #endregion

    #region Smarty plugins
    /**
     * Smarty {clientAction} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @throws SmartyException
     */
    public static function PluginClientAction($params, Smarty_Internal_Template $template) {
        if (!array_key_exists('action', $params)) throw new SmartyException('Required parameter "action" not given for tag "ClientAction".');
        NetDesign::GetInstance()->ClientDoAction($params['action'], $template->getTemplateVars('actionid'), $params, cms_utils::get_current_content());
    }

    /**
     * Smarty {clientId} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @return string
     */
    public static function PluginClientId($params, Smarty_Internal_Template $template) {
        return NetDesign::GetInstance()->ClientId();
    }

    /**
     * Smarty {clientPath} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @return string
     */
    public static function PluginClientPath($params, Smarty_Internal_Template $template) {
        return NetDesign::GetInstance()->ClientPath();
    }

    /**
     * Smarty {clientTemplate} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @return string
     * @throws SmartyException
     */
    public static function PluginClientTemplate($params, Smarty_Internal_Template $template) {
        if (!array_key_exists('template', $params)) throw new SmartyException('Required parameter "template" not given for tag "ClientTemplate".');
        $filename = $params['template'];
        unset($params['template']);
        $template->assign($params);
        return NetDesign::GetInstance()->SmartyClientFetch($filename);
    }

    /**
     * Smarty {clientUrl} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @return string
     */
    public static function PluginClientUrl($params, Smarty_Internal_Template $template) {
        return NetDesign::GetInstance()->ClientUrl();
    }

    /**
     * Smarty {moduleUrl} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @return string
     */
    public static function PluginModuleUrl($params, Smarty_Internal_Template $template) {
        /** @var CMSModule $module */
        $module = $template->getTemplateVars('mod');
        if (!($module instanceof CMSModule)) throw new SmartyException('moduleUrl: unable to determine active module');
        return $module->GetModuleURLPath();
        //var_dump($)
        //return NetDesign::GetInstance()->ClientUrl();
    }

    /**
     * Smarty {lang} block function plugin.
     *
     * @param array $params
     * @param string $content
     * @param Smarty_Internal_Template $template
     * @param bool $repeat
     * @return string
     */
    public static function PluginLang($params, $content, Smarty_Internal_Template $template, &$repeat) {
        if (is_null($content)) return;
        $args = array_values($params);
        array_unshift($args, $content);
        return call_user_func_array(array(NetDesign::GetInstance(), 'Lang'), $args);
    }

    /**
     * Smarty {templateMeta} function plugin.
     *
     * @param array $params
     * @param Smarty_Internal_Template $template
     * @return string
     */
    public static function PluginTemplateMeta($params, Smarty_Internal_Template $template) {
        $nd = NetDesign::GetInstance();
        if (!array_key_exists('css', $params)) $params['css'] = false;
        $params['css'] = cms_to_bool($params['css']);
        $nd->assign('css', $params['css']);
        return $nd->SmartyFetch('meta.tpl');
    }
    #endregion

    #region Admin interface Smarty overrides
    public function AdminPreFilter($source, Smarty_Internal_Template $template) {
        Events::SendEvent('Core', 'SmartyPreCompile', array('content' => &$source));
        return $source;
    }
    #endregion
}