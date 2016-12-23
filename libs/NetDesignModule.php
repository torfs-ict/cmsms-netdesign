<?php

namespace NetDesign;

use ModuleOperations;

/**
 * Class NetDesignModule
 * 
 * @property \Smarty_CMS $smarty
 * @property \CmsApp $cms
 * @property \cms_config $config
 */
abstract class NetDesignModule extends \CMSModule {
    protected static $routeParams = null;
    #region Module skeleton
    public function CreateStaticRoutes() {
    }
    /**
     * @return array
     */
    public function GetAdminMenuItems() {
        return parent::GetAdminMenuItems();
    }

    /**
     * @return string
     */
    public function GetAdminSection() {
        return parent::GetAdminSection();
    }

    /**
     * @return string
     */
    public function GetAuthor() {
        return 'Kristof Torfs';
    }

    /**
     * @return string
     */
    public function GetAuthorEmail() {
        return 'kristof@torfs.org';
    }

    /**
     * @return array
     */
    function GetDependencies() {
        return array('NetDesign' => '2.0.0');
    }

    /**
     * @return string
     */
    public function GetFriendlyName() {
        return parent::GetFriendlyName();
    }

    /**
     * @return string
     */
    final public function GetVersion() {
        $composer = json_decode(file_get_contents(cms_join_path($this->GetModulePath(), 'composer.json')));
        return $composer->version;
    }


    /**
     * @return bool
     */
    public function HasAdmin() {
        return true;
    }

    /**
     * @return bool
     */
    public function IsPluginModule() {
        return true;
    }

    /**
     * @param array $request
     * @return bool
     */
    public function SuppressAdminOutput(&$request) {
        if (array_key_exists('suppress', $request) || array_key_exists(sprintf('%ssuppress', $this->ActionId()), $request)) return true;
    }
    #endregion

    #region Convenience methods
    /**
     * Get an instance of a module, with code insight.
     *
     * @return static
     */
    public static function GetInstance() {
        return \cms_utils::get_module(get_called_class());
    }

    /**
     * Returns the module action id.
     *
     * @return string
     */
    public function ActionId() {
        if (isset($_REQUEST['mact'])) {
            $tmp = explode(',', cms_htmlentities($_REQUEST['mact']), 4);
            $id = isset($tmp[1]) ? $tmp[1] : '';
            if (!empty($id)) return $id;
        }
        $id = $this->smarty->getTemplateVars('actionid');
        if (empty($id)) {
            if ($this->cms->test_state(\CmsApp::STATE_ADMIN_PAGE)) $id = 'm1_';
            elseif ($this->cms->is_frontend_request()) $id = 'cntnt01';
        }
        return $id;
    }
    #endregion

    #region Constructors
    /**
     * Constructor.
     */
    final public function __construct() {
        parent::__construct();
        $this->Initialize();
        $this->LangIncludeModule($this);
        $this->SmartyRegisterResource();
    }

    /**
     * Since we set our constructor as final we define this method in addition to InitializeAdmin() and
     * InitializeFrontend().
     */
    public function Initialize() {
    }
    #endregion

    #region Client methods
    /**
     * Clone of CMSModule::DoAction, but this executes an action in the site directory (netdesign/<side_id>/actions/action.<action>.php).
     *
     * @param string $name Name of the action to perform
     * @param string $id The ID of the module
     * @param string $params The parameters targeted for this module
     * @param int|string $returnid The current page id that is being displayed.
     * @return string output XHTML.
     */
    final function ClientDoAction($name, $id, $params, $returnid = '') {
        $filename = cms_join_path($this->ClientPath(), 'actions', sprintf('action.%s.php', $name));
        if (@is_file($filename)) {
            $gCms = cmsms();
            include($filename);
        }
    }
    /**
     * Returns the active client id.
     *
     * @return string
     */
    final public function ClientId() {
        // 1: check if it is set through the configuration file
        $ret = cmsms()->GetConfig()->offsetGet('netdesign_client');
        if (!empty($ret)) return $ret;
        // 2: check if we only have a single client directory
        $glob = glob(cms_join_path($this->config->offsetGet('root_path'), 'netdesign', '*'), GLOB_ONLYDIR);
        if (count($glob) == 1) {
            // Only one client directory, automatically set this as active
            return basename($glob[0]);
        } else {
            // 3: get it from the site preferences
            $ret = \cms_siteprefs::get('netdesign_client', 'netdesign.be');
            $path = cms_join_path($this->config->offsetGet('root_path'), 'netdesign', $ret);
            if (!is_dir($path)) return '';
            else return $ret;
        }
    }

    /**
     * Returns the filesystem path to the directory for this module within client directory.
     * @return string
     */
    final public function ClientModulePath() {
        return cms_join_path($this->ClientPath(), 'modules', $this->GetName());
    }

    /**
     * Returns the URL to the directory for this module within client directory.
     * @return string
     */
    final public function ClientModuleUrl() {
        return cms_join_path($this->ClientUrl(), 'modules', $this->GetName());
    }

    /**
     * Returns the filesystem path to the client directory.
     * @return string
     */
    final public function ClientPath() {
        return cms_join_path($this->config->offsetGet('root_path'), 'netdesign', $this->ClientId());
    }

    /**
     * Returns the filesystem path to the client uploads directory (for this module).
     * @return string
     */
    final public function ClientUploadsPath() {
        $path = cms_join_path($this->config['uploads_path'], '.netdesign', $this->ClientId(), $this->GetName());
        if (!is_dir($path)) @mkdir($path, 0775, true);
        return $path;
    }
    /**
     * Returns the URL to the client uploads directory (for this module).
     * @return string
     */
    final public function ClientUploadsUrl() {
        return cms_join_path($this->config['uploads_url'], '.netdesign', $this->ClientId(), $this->GetName());
    }

    /**
     * Returns the URL to the client directory.
     * @return string
     */
    final public function ClientUrl() {
        return cms_join_path($this->config->offsetGet('root_url'), 'netdesign', $this->ClientId());
    }
    #endregion

    #region Language methods
    /**
     * @var string
     */
    private static $langCurrent;
    /**
     * @var array
     */
    private static $langHash = array();

    /**
     * Our custom translation implementation.
     *
     * As we want to be able to include language file from other modules/custom locations we have our custom
     * translation implementation.
     *
     * The differences between our implementation and the standard CMSMS implementation are:
     *
     * - Instead of using PHP files with language hashes we use .pot files.
     * - The string is passed to sprintf so we can use arguments to format it.
     * - If a string is not found in our message collection we simply return the untranslated string (but formatted with sprintf).
     *
     * @param string $msgid
     * @param array ...$args
     * @return mixed|string
     */
    final public function Lang() {
        $args = func_get_args();
        $msgid = array_shift($args);
        if (!array_key_exists($msgid, NetDesignModule::$langHash)) {
            $notify = $this->config->offsetGet('lang_notify');
            if (is_array($notify) && in_array($this->LangCurrentGet(), $notify)) $msgstr = sprintf('--[%s:%s]--', $this->LangCurrentGet(), $msgid);
            elseif (is_string($notify) && $this->LangCurrentGet() == $notify) $msgstr = sprintf('--[%s:%s]--', $this->LangCurrentGet(), $msgid);
            elseif (is_bool($notify) && $notify === true) $msgstr = sprintf('--[%s:%s]--', $this->LangCurrentGet(), $msgid);
            else $msgstr = $msgid;
        }
        else $msgstr = NetDesignModule::$langHash[$msgid];
        if (empty($args)) return $msgstr;
        return vsprintf($msgstr, $args);
    }

    /**
     * Gets the currently set language. If will first check if a custom language has been activated by one of our
     * modules. If not, it will return the language set by CMSMS.
     *
     * @return string
     */
    final public function LangCurrentGet() {
        if (empty(NetDesignModule::$langCurrent)) return \CmsNlsOperations::get_current_language();
        else return NetDesignModule::$langCurrent;
    }

    /**
     * Overrides the CMSMS active language.
     *
     * @param string $lang
     */
    final public function LangCurrentSet($lang = 'en_US') {
        if (NetDesignModule::$langCurrent == $lang) return;
        NetDesignModule::$langCurrent = $lang;
        NetDesignModule::$langHash = array();
    }

    /**
     * Includes the .pot file from the client directory for the current language.
     */
    final public function LangIncludeClient() {
        if (!ModuleOperations::get_instance()->IsModuleActive('NetDesign')) return;
        $lang = $this->LangCurrentGet();
        $filenames = array(
            // Include en_US by default
            cms_join_path($this->ClientPath(), 'lang', 'en_US.pot'),
            // Current language can override en_US
            cms_join_path($this->ClientPath(), 'lang', "$lang.pot"),
        );
        foreach($filenames as $filename) {
            $this->LangIncludeFile($filename);
        }
    }

    /**
     * Includes a .pot file from a custom location.
     *
     * @param string $filename
     */
    final public function LangIncludeFile($filename) {
        if (!file_exists($filename)) return;
        $parser = new PortableObjectTemplateParser();
        NetDesignModule::$langHash = array_merge(NetDesignModule::$langHash, $parser->parseFile($filename));
    }

    /**
     * Includes a .pot file from another module for the current language.
     *
     * @param NetDesignModule $module
     */
    final public function LangIncludeModule(NetDesignModule $module) {
        if (!ModuleOperations::get_instance()->IsModuleActive('NetDesign')) return;
        $lang = $this->LangCurrentGet();
        $filenames = array(
            // Include en_US by default
            cms_join_path($module->GetModulePath(), 'lang', 'en_US.pot'),
            cms_join_path($module->ClientModulePath(), 'lang', 'en_US.pot'),
            // Current language can override en_US
            cms_join_path($module->GetModulePath(), 'lang', "$lang.pot"),
            cms_join_path($module->ClientModulePath(), 'lang', "$lang.pot"),
        );
        foreach($filenames as $filename) {
            $this->LangIncludeFile($filename);
        }
    }
    #endregion

    #region MySQL methods
    /**
     * @var NetDesignConnection
     */
    private $mysqlConn;

    /**
     * Returns the MySQL connection.
     *
     * @return NetDesignConnection
     */
    final public function MySQL() {
        if (!($this->mysqlConn instanceof NetDesignConnection)) {
            // Get database configuration values
            $config = \CMSApp::get_instance()->GetConfig();
            $host = $config->offsetGet('db_hostname');
            $port = $config->offsetGet('db_port') ? (int)$config->offsetGet('db_port') : 3306;
            $name = $config->offsetGet('db_name');
            $user = $config->offsetGet('db_username');
            $pass = $config->offsetGet('db_password');
            // Generate the DSN
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8', $host, $port, $name);
            // Create connection
            $this->mysqlConn = new NetDesignConnection($dsn, $user, $pass);
            // Set the prefix
            $this->mysqlConn->prefix = cms_db_prefix();
        }
        return $this->mysqlConn;
    }
    #endregion

    #region Smarty methods
    final public function assign($varname, $value = null, $nocache = false) {
        $this->smarty->get_template_parent()->assign($varname, $value, $nocache);
    }
    /**
     * Fetches a client template. This method just saves us the hassle of always having to call
     * the combination of $this->smarty->fetch and $this->SmartyResource.
     *
     * @param string $template The template filename.
     * @param string $fallback The template filename to fall back to in case $template does not exist.
     * @return string
     */
    final public function SmartyClientFetch($template, $fallback = null) {
        $this->SmartyHeaders();
        $tpl = $this->SmartyResource($template);
        $fb = $this->SmartyResource($fallback);
        if (!$this->smarty->templateExists($tpl) && $this->smarty->templateExists($fb)) $tpl = $fb;
        return $this->smarty->fetch($tpl);
    }

    /**
     * Fetches a module template. This method just saves us the hassle of always having to call
     * the combination of $this->smarty->fetch and $this->GetFileResource.
     *
     * @param string $template The template filename.
     * @return string
     */
    final public function SmartyFetch($template) {
        return $this->smarty->fetch($this->GetFileResource($template));
    }

    /**
     * Sends out HTTP headers. This is done automatically when calling SmartyClientFetch(). The headers it sents out
     * are:
     *
     * - X-UA-Compatible: IE=edge,chrome=1
     *
     */
    final public function SmartyHeaders() {
        header('X-UA-Compatible: IE=edge,chrome=1');
    }

    /**
     * Returns a Smarty resource for a client module template.
     *
     * @param string $template
     * @return string
     */
    final public function SmartyModuleResource($template) {
        return sprintf('%s:%s', get_called_class(), $template);
    }

    /**
     * Returns a Smarty resource for a client template.
     *
     * @param string $template
     * @return string
     */
    final public function SmartyResource($template) {
        return sprintf('netdesign_client_tpl:%s', $template);
    }

    /**
     * Registers the Smarty resource for client templates.
     */
    final public function SmartyRegisterResource() {
        if (!ModuleOperations::get_instance()->IsModuleActive('NetDesign')) return;
        $this->smarty->registerResource('netdesign_client_tpl', new NetDesignResource());
        $this->smarty->registerResource(get_called_class(), new NetDesignModuleResource(get_called_class()));
    }
    #endregion

    #region SQLite methods
    /*
    private $sqliteConn;
    final public function SQLite() {
        if (!($this->sqliteConn instanceof NetDesignConnection)) {
            $path = cms_join_path($this->ClientUploadsPath(), sprintf('database.sq3'));
            $dsn = sprintf('sqlite:%s', $path);
            $this->sqliteConn = new NetDesignConnection($dsn, null, null);
        }
        return $this->sqliteConn;
    }
    */
    #endregion

    #region Permission methods
    /**
     * Custom version of CreatePermission(). The permission text will be altered to "NetDesign CMS/<Module>: <text>".
     *
     * @param string $name
     * @param string $text
     */
    final public function PermissionCreate($name, $text) {
        if (get_class($this) == 'NetDesign') $this->CreatePermission($name, sprintf('NetDesign CMS: %s', $text));
        else $this->CreatePermission($name, sprintf('NetDesign CMS/%s: %s', $this->GetName(), $text));
    }
    #endregion

    #region DoAction override
    /**
     * Override of the default DoAction method. All we do here is make sure that the
     * Smarty error console is shown upon an uncaught exception in the admin interface.
     *
     * @param string $name
     * @param string $id
     * @param string $params
     * @param string $returnid
     * @return string
     */
    public function DoAction($name, $id, $params, $returnid = '') {
        try {
            return parent::DoAction($name, $id, $params, $returnid);
        } catch (\Exception $e) {
            echo $this->smarty->errorConsole($e);
            exit;
        }
    }
    #endregion

    #region Session functions
    /**
     * Removes every session namespace/variable for this module.
     */
    final public function SessionClear() {
        if (!array_key_exists('NetDesignCMS', $_SESSION)) return;
        if (!array_key_exists($this->GetName(), $_SESSION['NetDesignCMS'])) return;
        unset($_SESSION['NetDesignCMS'][$this->GetName()]);
    }

    /**
     * Gets a session variable for this module.
     *
     * @param string $name Variable name.
     * @param mixed $default This value will be returned if the variable is not set.
     * @param string $namespace The (optional) namespace in which the variable resides.
     * @return mixed
     */
    final public function SessionGet($name, $default = null, $namespace = 'default') {
        if (!$this->SessionHas($name, $namespace)) return $default;
        return $_SESSION['NetDesignCMS'][$this->GetName()][$namespace][$name];
    }

    /**
     * Checks if a session variable is set for this module.
     *
     * @param string $name Variable name.
     * @param string $namespace The (optional) namespace in which the variable resides.
     * @return bool
     */
    final public function SessionHas($name, $namespace = 'default') {
        if (!array_key_exists('NetDesignCMS', $_SESSION)) return false;
        if (!array_key_exists($this->GetName(), $_SESSION['NetDesignCMS'])) return false;
        if (!array_key_exists($namespace, $_SESSION['NetDesignCMS'][$this->GetName()])) return false;
        if (!array_key_exists($name, $_SESSION['NetDesignCMS'][$this->GetName()][$namespace])) return false;
        return true;
    }

    /**
     * Sets a session variable for this module.
     *
     * @param string $name Variable name.
     * @param mixed $value The variable value to set.
     * @param string $namespace The (optional) namespace in which the variable resides.
     */
    final public function SessionSet($name, $value = null, $namespace = 'default') {
        if (!array_key_exists('NetDesignCMS', $_SESSION)) $_SESSION['NetDesignCMS'] = array();
        if (!array_key_exists($this->GetName(), $_SESSION['NetDesignCMS'])) $_SESSION['NetDesignCMS'][$this->GetName()] = array();
        if (!array_key_exists($namespace, $_SESSION['NetDesignCMS'][$this->GetName()])) $_SESSION['NetDesignCMS'][$this->GetName()][$namespace] = array();
        $_SESSION['NetDesignCMS'][$this->GetName()][$namespace][$name] = $value;
    }

    /**
     * Sets a session variable for this module, but only if it hasn't been set yet.
     *
     * @param string $name Variable name.
     * @param mixed $value The variable value to set.
     * @param string $namespace The (optional) namespace in which the variable resides.
     * @return mixed
     */
    final public function SessionSetIf($name, $value = null, $namespace = 'default') {
        if ($this->SessionHas($name, $namespace)) return;
        $this->SessionSet($name, $value, $namespace);
    }

    /**
     * Removes a session variable for this module.
     *
     * @param string $name Variable name.
     * @param string $namespace The (optional) namespace in which the variable resides.
     */
    final public function SessionUnset($name, $namespace = 'default') {
        if (!$this->SessionHas($name, $namespace)) return;
        unset($_SESSION['NetDesignCMS'][$this->GetName()][$namespace][$name]);
    }

    /**
     * Removes an entire session namespace for this module.
     *
     * @param string $namespace The (optional) namespace in which the variable resides.
     */
    final public function SessionUnsetNamespace($namespace = 'default') {
        if (!array_key_exists('NetDesignCMS', $_SESSION)) return;
        if (!array_key_exists($this->GetName(), $_SESSION['NetDesignCMS'])) return;
        if (!array_key_exists($namespace, $_SESSION['NetDesignCMS'][$this->GetName()])) return;
        unset($_SESSION['NetDesignCMS'][$this->GetName()][$namespace]);
    }
    #endregion

    #region Preference methods
    final protected function PreferenceName($name) {
        return sprintf('%s.%s', $this->ClientId(), $name);
    }
    final public function PreferenceSet($name, $value) {
        $this->SetPreference($this->PreferenceName($name), $value);
    }
    final public function PreferenceGet($name, $default = null) {
        return $this->GetPreference($this->PreferenceName($name), $default);
    }
    final public function PreferenceRemove($name) {
        return $this->RemovePreference($this->PreferenceName($name));
    }
    #endregion

    #region Route methods
    final public function ParseRouteParams() {
        NetDesignModule::$routeParams = [];
        $id = $this->smarty->id;
        foreach($_REQUEST as $key => $value) {
            if (!fnmatch("$id*", $key)) continue;
            $key = substr($key, strlen($id));
            NetDesignModule::$routeParams[$key] = $value;
        }
    }
    final public function GetRouteParam($name, $default = null) {
        if (!array_key_exists($name, NetDesignModule::$routeParams)) return $default;
        return NetDesignModule::$routeParams[$name];
    }
    #endregion
}