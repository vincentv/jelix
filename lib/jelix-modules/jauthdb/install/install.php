<?php
/**
* @package     jelix
* @subpackage  jauthdb module
* @author      Laurent Jouanneau
* @contributor
* @copyright   2009 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/


class jauthdbModuleInstaller extends jInstallerModule {

    function install() {

      $this->execSQLScript('install_jauth.schema');
      $this->execSQLScript('install_jauth.data');
    }
}