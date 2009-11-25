<?php
/**
* @package     jelix
* @subpackage  installer
* @author      Laurent Jouanneau
* @contributor 
* @copyright   2008-2009 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

require_once(JELIX_LIB_PATH.'installer/jIInstallReporter.iface.php');
require_once(JELIX_LIB_PATH.'installer/jIInstallerComponent.iface.php');
require_once(JELIX_LIB_PATH.'installer/jInstallerException.class.php');
require_once(JELIX_LIB_PATH.'installer/jInstallerBase.class.php');
require_once(JELIX_LIB_PATH.'installer/jInstallerEntryPoint.class.php');
require_once(JELIX_LIB_PATH.'core/jConfigCompiler.class.php');
require(JELIX_LIB_PATH.'installer/jInstallerMessageProvider.class.php');


/**
 * simple text reporter
 */
class textInstallReporter implements jIInstallReporter {
    
    function start() {
        echo "Installation start..\n";
    }

    /**
     * displays a message
     * @param string $message the message to display
     * @param string $type the type of the message : 'error', 'notice', 'warning', ''
     */
    function message($message, $type='') {
        echo ($type != ''?'['.$type.'] ':'').$message."\n";
    }

    /**
     * called when the installation is finished
     * @param array $results an array which contains, for each type of message,
     * the number of messages
     */
    function end($results) {
        echo "Installation ended.\n";
    }
}



/**
 * main class for the installation
 *
 * It load all entry points configurations. Each configurations has its own
 * activated modules. jInstaller then construct a tree dependencies for these
 * activated modules, and launch their installation and the installation
 * of their dependencies.
 * An installation can be an initial installation, or just an upgrade
 * if the module is already installed.
 * @internal The object which drives the installation of a component
 * (module, plugin...) is an object which inherits from jInstallerComponentBase.
 * This object calls load a file from the directory of the component. this
 * file should contain a class which should inherits from jInstallerModule
 * or jInstallerPlugin. this class should implements processes to install
 * the component.
 */
class jInstaller {

    /** value for the installation status of a component: "uninstalled" status */
    const STATUS_UNINSTALLED = 0;
    /** value for the installation status of a component: "installed" status */
    const STATUS_INSTALLED = 1;

    /**
     * value for the access level of a component: "forbidden" level.
     * a module which have this level won't be installed
     */
    const ACCESS_FORBIDDEN = 0;
    
    /**
     * value for the access level of a component: "private" level.
     * a module which have this level won't be accessible directly
     * from the web, but only from other modules
     */
    const ACCESS_PRIVATE = 1;
    
    /**
     * value for the access level of a component: "public" level.
     * the module is accessible from the web
     */
    const ACCESS_PUBLIC = 2;
    
    /**
     * error code stored in a component: impossible to install
     * the module because dependencies are missing
     */
    const INSTALL_ERROR_MISSING_DEPENDENCIES = 1;
    /**
     * error code stored in a component: impossible to install
     * the module because of circular dependencies
     */
    const INSTALL_ERROR_CIRCULAR_DEPENDENCY = 2;
    
    /**
     *  @var jIniFileModifier it represents the installer.ini.php file.
     */
    public $installerIni = null;
    
    /**
     * parameters for each entry point.
     * @var array of jInstallerEntryPoint
     */
    protected $epProperties = array();

    /**
     * list of entry point identifiant (provided by the configuration compiler).
     * identifiant of the entry point is the path+filename of the entry point
     * without the php extension
     * @var array   key=entry point name, value=url id
     */
    protected $epId = array();

    /**
     * list of modules for each entry point
     * @var array first key: entry point id, second key: module name, value = jInstallerComponentModule
     */
    protected $modules = array();
    
    /**
     * list of all modules of the application
     * @var array key=path of the module, value = jInstallerComponentModule
     */
    protected $allModules = array();

    /**
     * the object responsible of the results output
     * @var jIInstallReporter
     */
    public $reporter;

    /**
     * @var JInstallerMessageProvider
     */
    public $messages;

    /** @var integer the number of errors appeared during the installation */
    public $nbError = 0;

    /** @var integer the number of ok messages appeared during the installation */
    public $nbOk = 0;

    /** @var integer the number of warnings appeared during the installation */
    public $nbWarning = 0;

    /** @var integer the number of notices appeared during the installation */
    public $nbNotice = 0;

    /**
     * initialize the installation
     *
     * it reads configurations files of all entry points, and prepare object for
     * each module, needed to install/upgrade modules.
     * @param jIInstallReporter $reporter  object which is responsible to process messages (display, storage or other..)
     * @param string $lang  the language code for messages
     */
    function __construct ($reporter, $lang='') {
        $this->reporter = $reporter;
        $this->messages = new jInstallerMessageProvider($lang);
        $this->installerIni = $this->getInstallerIni();
        $this->readEntryPointData(simplexml_load_file(JELIX_APP_PATH.'project.xml'));
        $this->installerIni->save();
    }

    /**
     * @internal mainly for tests
     * @return jIniFileModifier the modifier for the installer.ini.php file
     */
    protected function getInstallerIni() {
        if (!file_exists(JELIX_APP_CONFIG_PATH.'installer.ini.php'))
            file_put_contents(JELIX_APP_CONFIG_PATH.'installer.ini.php', ";<?php die(''); ?>
; for security reasons , don't remove or modify the first line
; don't modify this file if you don't know what you do. it is generated automatically by jInstaller

");
        return new jIniFileModifier(JELIX_APP_CONFIG_PATH.'installer.ini.php');
    }

    /**
     * read the list of entrypoint from the project.xml file
     * and read all modules data used by each entry point
     * @param SimpleXmlElement $xml
     */
    protected function readEntryPointData($xml) {

        $configFileList = array();

        // read all entry points data
        foreach ($xml->entrypoints->entry as $entrypoint) {

            $file = (string)$entrypoint['file'];
            $configFile = (string)$entrypoint['config'];
            $isCliScript = (isset($entrypoint['cli'])?(string)$entrypoint['cli'] == 'true':false);

            // ignore entry point which have the same config file of an other one
            if (isset($configFileList[$configFile]))
                continue;

            $configFileList[$configFile] = true;

            // we create an object corresponding to the entry point
            $c = $this->getEntryPointObject($configFile, $file, $isCliScript);
            $epId = $c->getEpId();
            
            $this->epId[$file] = $epId;
            $this->epProperties[$epId] = $c;
            $this->modules[$epId] = array();

            // now let's read all modules properties
            foreach ($c->getModulesList() as $name=>$path) {
                $module = $c->getModule($name);

                $this->installerIni->setValue($name.'.installed', $module->isInstalled, $epId);
                $this->installerIni->setValue($name.'.version', $module->version, $epId);

                if (!isset($this->allModules[$path])) {
                    $this->allModules[$path] = $this->getComponentModule($name, $path, $this);
                }

                $m = $this->allModules[$path];
                $m->setEntryPointData($epId, $module);
                $this->modules[$epId][$name] = $m;
            }
        }
    }
    
    /**
     * @internal for tests
     */
    protected function getEntryPointObject($configFile, $file, $isCliScript) {
        return new jInstallerEntryPoint($configFile, $file, $isCliScript);
    }

    /**
     * @internal for tests
     */
    protected function getComponentModule($name, $path, $installer) {
        return new jInstallerComponentModule($name, $path, $installer);
    }

    /**
     * install and upgrade if needed, all modules for each
     * entry point. Only modules which have an access property > 0
     * are installed. Errors appeared during the installation are passed
     * to the reporter.
     * @return boolean true if succeed, false if there are some errors
     */
    public function installApplication() {

        $this->startMessage();
        $result = true;

        foreach(array_keys($this->epProperties) as $epId) {
            $modules = array();
            foreach($this->modules[$epId] as $name => $module) {
                if ($module->getAccessLevel($epId) == 0)
                    continue;
                $modules[$name] = $module;
            }
            $result = $result & $this->_installModules($modules, $epId);
            if (!$result)
                break;
        }

        $this->installerIni->save();
        $this->endMessage();
        return $result;
    }

    /**
     * install given modules even if they don't have an access property > 0
     * @param array $list array of module names
     * @param string $entrypoint  the entrypoint name as it appears in project.xml
     * @return boolean true if the installation is ok
     */
    public function installModules($list, $entrypoint = 'index.php') {
        
        $this->startMessage();
        
        if (!isset($this->epId[$entrypoint])) {
            throw new Exception("unknow entry point");
        }
        
        
        $epId = $this->epId[$entrypoint];
        $allModules = &$this->modules[$epId];
        
        $modules = array();
        // always install jelix
        array_unshift($list, 'jelix');
        foreach($list as $name) {
            if (!isset($allModules[$name])) {
                $this->error('module.unknow', $name);
            }
            else
                $modules[] = $allModules[$name];
        }

        $result = $this->_installModules($modules, $epId);
        $this->installerIni->save();
        $this->endMessage();
        return $result;
    }

    /**
     * core of the installation
     * @param array $modules list of jInstallerComponentModule
     * @param string $epId  the entrypoint id
     * @return boolean true if the installation is ok
     */
    protected function _installModules(&$modules, $epId) {

        $this->ok('install.entrypoint.start', $epId);
        
        $ep = $this->epProperties[$epId];
        $GLOBALS['gJConfig'] = $ep->config;
        
        // load the main configuration
        $epConfig = new jIniMultiFilesModifier(JELIX_APP_CONFIG_PATH.'defaultconfig.ini.php',
                                               JELIX_APP_CONFIG_PATH.$ep->configFile);

        // first, check dependencies of the component, to have the list of component
        // we should really install. It fills $this->_componentsToInstall, in the right
        // order
        $result = $this->checkDependencies($modules, $epId);

        if (!$result) {
            $this->error('install.bad.dependencies');
            $this->ok('install.entrypoint.bad.end', $epId);
            return false;
        }
        
        $this->ok('install.dependencies.ok');

        // ----------- pre install
        // put also available installers into $componentsToInstall for
        // the next step
        $componentsToInstall = array();

        foreach($this->_componentsToInstall as $item) {
            list($component, $toInstall) = $item;
            try {
                if ($toInstall) {
                    $installer = $component->getInstaller($epConfig, $epId);
                    if ($installer === null || $installer === false) {
                        $this->installerIni->setValue($component->getName().'.installed',
                                                       1, $epId);
                        $this->installerIni->setValue($component->getName().'.version',
                                                       $component->getSourceVersion(), $epId);
                        $this->ok('install.module.installed', $component->getName());
                        continue;
                    }
                    $componentsToInstall[] = array($installer, $component, $toInstall);
                    $installer->preInstall();
                }
                else {
                    $upgraders = $component->getUpgraders($epConfig, $epId);

                    if (count($upgraders) == 0) {
                        $this->installerIni->setValue($component->getName().'.version',
                                                      $component->getSourceVersion(), $epId);
                        $this->ok('install.module.upgraded',
                                  array($component->getName(), $component->getSourceVersion()));
                        continue;
                    }

                    foreach($upgraders as $upgrader) {
                        $upgrader->preInstall();
                    }
                    $componentsToInstall[] = array($upgraders, $component, $toInstall);
                }
            } catch (jInstallerException $e) {
                $result = false;
                $this->error ($e->getLocaleKey(), $e->getLocaleParameters());
            } catch (Exception $e) {
                $result = false;
                $this->error ('install.module.error', $e->getMessage());
            }
        }
        
        if (!$result) {
            $this->ok('install.entrypoint.bad.end', $epId);
            return false;
        }
        
        
        $installedModules = array();
        
        // -----  installation process
        try {
            foreach($componentsToInstall as $item) {
                list($installer, $component, $toInstall) = $item;
                if ($toInstall) {
                    if ($installer)
                        $installer->install();
                    $this->installerIni->setValue($component->getName().'.installed',
                                                   1, $epId);
                    $this->installerIni->setValue($component->getName().'.version',
                                                   $component->getSourceVersion(), $epId);
                    $this->ok('install.module.installed', $component->getName());
                    $installedModules[] = array($installer, $component, true);
                }
                else {
                    $lastversion = '';
                    foreach($installer as $upgrader) {
                        $upgrader->install();
                        // we set the version of the upgrade, so if an error occurs in
                        // the next upgrader, we won't have to re-run this current upgrader
                        // during a future update
                        $this->installerIni->setValue($component->getName().'.version',
                                                      $upgrader->version, $epId);
                        $this->ok('install.module.upgraded',
                                  array($component->getName(), $upgrader->version));
                        $lastversion = $upgrader->version;
                    }
                    // we set the version to the component version, because the version
                    // of the last upgrader could not correspond to the component version.
                    if ($lastversion != $component->getSourceVersion()) {
                        $this->installerIni->setValue($component->getName().'.version',
                                                      $component->getSourceVersion(), $epId);
                        $this->ok('install.module.upgraded',
                                  array($component->getName(), $component->getSourceVersion()));
                    }
                    $installedModules[] = array($installer, $component, false);
                }
                if ($epConfig->isModified()) {
                    $epConfig->save();
                    // we re-load configuration file for each module because
                    // previous module installer could have modify it.
                    $GLOBALS['gJConfig'] = $ep->config =
                        jConfigCompiler::read($ep->configFile, true,
                                              $ep->isCliScript,
                                              $ep->scriptName);
                }
            }
        } catch (jInstallerException $e) {
            $result = false;
            $this->error ($e->getLocaleKey(), $e->getLocaleParameters());
        } catch (Exception $e) {
            $result = false;
            $this->error ('install.module.error', array($component->getName(), $e->getMessage()));
        }

        if (!$result) {
            $this->ok('install.entrypoint.bad.end', $epId);
            return false;
        }

        // post install
        foreach($installedModules as $item) {
            try {
                list($installer, $component, $toInstall) = $item;
                if ($toInstall) {
                    if ($installer)
                        $installer->postInstall();
                }
                else {
                    foreach($installer as $upgrader) {
                        $upgrader->postInstall();
                    }
                }
                if ($epConfig->isModified()) {
                    $epConfig->save();
                    // we re-load configuration file for each module because
                    // previous module installer could have modify it.
                    $GLOBALS['gJConfig'] = $ep->config =
                        jConfigCompiler::read($ep->configFile, true,
                                              $ep->isCliScript,
                                              $ep->scriptName);
                }
            } catch (jInstallerException $e) {
                $result = false;
                $this->error ($e->getLocaleKey(), $e->getLocaleParameters());
            } catch (Exception $e) {
                $result = false;
                $this->error ('install.module.error', $e->getMessage());
            }
        }

        $this->ok('install.entrypoint.end', $epId);

        return $result;
    }


    protected $_componentsToInstall = array();
    protected $_checkedComponents = array();
    protected $_checkedCircularDependency = array();

    /**
     * check dependencies of given modules and plugins
     *
     * @param array $list  list of jInstallerComponentModule/jInstallerComponentPlugin objects
     * @throw jException if the install has failed
     */
    protected function checkDependencies ($list, $epId) {
        
        $this->_checkedComponents = array();
        $this->_componentsToInstall = array();
        $result = true;
        foreach($list as $component) {
            $this->_checkedCircularDependency = array();
            if (!isset($this->_checkedComponents[$component->getName()])) {
                try {
                    $component->init();

                    $this->_checkDependencies($component, $epId);

                    if (!$component->isInstalled($epId)) {
                        $this->_componentsToInstall[] = array($component, true);
                    }
                    else if (!$component->isUpgraded($epId)) {
                        $this->_componentsToInstall[] = array($component, false);
                    }
                } catch (jInstallerException $e) {
                    $result = false;
                    $this->error ($e->getLocaleKey(), $e->getLocaleParameters());
                } catch (Exception $e) {
                    $result = false;
                    $this->error ($e->getMessage(), null, true);
                }
            }
        }
        return $result;
    }

    /**
     * check dependencies of a module
     * @param jInstallerComponentBase $component
     * @param string $epId
     */
    protected function _checkDependencies($component, $epId) {

        if (isset($this->_checkedCircularDependency[$component->getName()])) {
            $component->inError = self::INSTALL_ERROR_CIRCULAR_DEPENDENCY;
            throw new jInstallerException ('module.circular.dependency',$component->getName());
        }

        //$this->ok('install.module.check.dependency', $component->getName());

        $this->_checkedCircularDependency[$component->getName()] = true;

        $compNeeded = '';
        foreach ($component->dependencies as $compInfo) {
            // TODO : supports others type of components
            if ($compInfo['type'] != 'module')
                continue;
            $name = $compInfo['name'];
            $comp = $this->modules[$epId][$name];
            if (!$comp)
                $compNeeded .= $name.', ';
            else {
                if (!isset($this->_checkedComponents[$comp->getName()])) {
                    $comp->init();
                }

                if (!$comp->checkVersion($compInfo['minversion'], $compInfo['maxversion'])) {
                    if ($name == 'jelix') {
                        $args = $component->getJelixVersion();
                        array_unshift($args, $component->getName());
                        throw new jInstallerException ('module.bad.jelix.version', $args);
                    }
                    else
                        throw new jInstallerException ('module.bad.dependency.version',array($component->getName(), $comp->getName(), $compInfo['minversion'], $compInfo['maxversion']));
                }

                if (!isset($this->_checkedComponents[$comp->getName()])) {
                    $this->_checkDependencies($comp, $epId);
                    if (!$comp->isInstalled($epId)) {
                        $this->_componentsToInstall[] = array($comp, true);
                    }
                    else if(!$comp->isUpgraded($epId)) {
                        $this->_componentsToInstall[] = array($comp, false);
                    }
                }
            }
        }

        $this->_checkedComponents[$component->getName()] = true;
        unset($this->_checkedCircularDependency[$component->getName()]);

        if ($compNeeded) {
            $component->inError = self::INSTALL_ERROR_MISSING_DEPENDENCIES;
            throw new jInstallerException ('module.needed', array($component->getName(), $compNeeded));
        }
    }
    
    protected function startMessage () {
        $this->nbError = 0;
        $this->nbOk = 0;
        $this->nbWarning = 0;
        $this->nbNotice = 0;
        $this->reporter->start();
    }
    
    protected function endMessage() {
        $this->reporter->end(array('error'=>$this->nbError, 'warning'=>$this->nbWarning, 'ok'=>$this->nbOk,'notice'=>$this->nbNotice));
    }

    protected function error($msg, $params=null, $fullString=false){
        if($this->reporter) {
            if (!$fullString)
                $msg = $this->messages->get($msg,$params);
            $this->reporter->message($msg, 'error');
        }
        $this->nbError ++;
    }

    protected function ok($msg, $params=null, $fullString=false){
        if($this->reporter) {
            if (!$fullString)
                $msg = $this->messages->get($msg,$params);
            $this->reporter->message($msg, '');
        }
        $this->nbOk ++;
    }

    protected function warning($msg, $params=null, $fullString=false){
        if($this->reporter) {
            if (!$fullString)
                $msg = $this->messages->get($msg,$params);
            $this->reporter->message($msg, 'warning');
        }
        $this->nbWarning ++;
    }

    protected function notice($msg, $params=null, $fullString=false){
        if($this->reporter) {
            if (!$fullString)
                $msg = $this->messages->get($msg,$params);
            $this->reporter->message($msg, 'notice');
        }
        $this->nbNotice ++;
    }

}

