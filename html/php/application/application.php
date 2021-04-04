<?php 
/*_gtlsc_
 * os.com.ar (a9os) - Open web LAMP framework and desktop environment
 * Copyright (C) 2019-2021  Santiago Pereyra (asp95)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.*/

class application_application extends a9os_core_main{
	public function getFileExtensions(){
		$appAppExtCollection = $this->getCore()->getModel("application.application.extension");
		$appAppExtCollection = $appAppExtCollection->_setSelect("
			SELECT * 
			from {$appAppExtCollection->getTableName()}
			where {$this->getPrimaryIdx()} = {$this->getID()}
		");

		$arrExtensions = [];
		while ($currExtension = $appAppExtCollection->fetch()) {
			$arrExtensions[] = $currExtension->getFileExtension();
		}

		return $arrExtensions;
	}

	
	public function validateUserScope(){ // true or false if the app can be used by the logged in user
		if ($this->getAppScope() == "public") return true;
		$user = $this->getCore()->getModel("a9os.user")->getSessionUser();
		if ($user->getIsAnonUser()) return false;

		$userEnabledApps = $this->getCore()->getModel("application.application.user")->getEnabledApps();
		
		if (empty($userEnabledApps)) return false;

		if (!in_array($this->getID(), $userEnabledApps)) return false;

		return true;
	}


	public function addNew($appName, $iconUrl, $appScope, $currAppFolderToImport, $appVersion){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => ["a9os_core_app_installer::importApp"]
		]);


		$newAppApp = $this->getCore()->getModel("application.application");
		$newAppApp->setName($appName);
		$newAppApp->setIconUrl($iconUrl);
		$newAppApp->setAppScope($appScope);
		$newAppApp->setAppCode($this->getCore()->getRandomId());
		$newAppApp->setIsInstalled(0);
		$newAppApp->setAppFolder($currAppFolderToImport);
		$newAppApp->setAppVersion(json_encode($appVersion));
		$newAppApp->save();
	}

	public function syncSetVersion($arrVersion){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => ["a9os_core_app_installer::syncExistingApps"]
		]);

		$this->setAppVersion(json_encode($arrVersion));
		$this->save();
	}

	public function getAppinstallerName(){
		if (!$this->getID()) return false;

		$currAppFolderToImportToCN = str_replace("/", "_", $this->getAppFolder());
		$currAppFolderToImportToCN = rtrim($currAppFolderToImportToCN, "_");
		$installerClassName = "a9os_app_{$currAppFolderToImportToCN}_appinstaller";

		return $installerClassName;
	}



	public function install(){
		if (!$this->getID()) return false;

		$installerClassName = $this->getAppinstallerName();
		$installerClassName = $this->getCore()->getModelDescriptorByClassName($installerClassName);
		
		$classAppInstaller = $this->getCore()->getModel($installerClassName);
		if ($classAppInstaller->install($this)) {
			$this->setIsInstalled(1);
			$this->save();
		}
	}

	public function update(){
		if (!$this->getID()) return false;

		$installerClassName = $this->getAppinstallerName();
		$installerClassName = $this->getCore()->getModelDescriptorByClassName($installerClassName);
		
		$classAppInstaller = $this->getCore()->getModel($installerClassName);
		if ($classAppInstaller->update($this)) {
			$this->setAppVersion(json_encode($classAppInstaller::arrVersion));
			$this->save();
		}
	}

	public function uninstall(){
		error_log("UNINSTALL TO DO");  // no usar appinstaller - usar core.controller.application y sacar con eso
	}



	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;

		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 4) return false;


		if (!$tableInfo) {
			$tableHandle->addField("name", "varchar(250)", false, false, false, "''");
			$tableHandle->addField("favicon_url", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("app_scope", "enum('public', 'private')", false, true, false, "NULL");
			$tableHandle->addField("private_code", "varchar(50)", false, true, false, "NULL");

			$tableInfo = ["version" => 1];

			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}

		if ($tableInfo["version"] < 2) {
			$tableHandle->changeField("private_code", "app_code", "varchar(10)", false, true, false, "NULL");
			$tableHandle->changeField("favicon_url", "icon_url", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("is_installed", "int", false, false, false, "'0'");
			$tableHandle->addField("app_folder", "varchar(255)", false, true, false, "NULL");
			
			$tableInfo = ["version" => 2];

			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}

		if ($tableInfo["version"] < 3) {
			$tableHandle->addField("appinstaller_file_md5", "varchar(100)", false, true, false, "NULL");
			
			$tableInfo = ["version" => 3];

			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}

		if ($tableInfo["version"] < 4) {
			$tableHandle->deleteField("appinstaller_file_md5");
			$tableHandle->addField("app_version", "varchar(30)", false, false, false, "''");
			$tableInfo = ["version" => 4];

			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}


		return true;
	}
}