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

class core_controller extends core_db_model{
	public function load($value, $field = ""){
		parent::load($value, $field);

		if ($value == "/") {
			$installSystemOutput = $this->installSystem();
			$updateSystemOutput = $this->updateSystem();

			//if ($installSystemOutput || $updateSystemOutput) return "__RELOAD__";
		}

		if (empty($this->getID())){
			return $this;
		}


		$componentCollection = $this->getCore()->getModel("core.controller.component");
		$componentCollection = $componentCollection->_setSelect("SELECT * 
			from {$componentCollection->getTableName()} ccc
			where {$this->getPrimaryIdx()} = {$this->getID()}
			order by ccc.order asc
		");

		$this->setComponentCollection($componentCollection);

		return $this;
	}
	public function getResponseData($postData){
		$arrComponents = [];
		while($currControllerComponent = $this->getComponentCollection()->fetch()){
			$arrComponents[] = $currControllerComponent->getCoreComponentId();
		}

		$postData["core_controller_id"] = $this->getID();

		return $this->getCore()->getModel("core.component")->loadComponents($arrComponents, $postData);
	}




	public function installSystem(){
		$tableInfo = $this->_getTableInfo();
		if (isset($tableInfo["systemVersion"])) return false;

		$coreComponent404 = $this->getCore()->getModel("core.component");
		$coreComponent404->setComponentName("error_404");
		$coreComponent404->setDesignPath(".a9os-main .window > .main-content");
		$coreComponent404->setDataModel("core.error.404");
		$coreComponent404->save();

		$coreComponentA9osCoreMain = $this->getCore()->getModel("core.component");
		$coreComponentA9osCoreMain->setComponentName("a9os_core_main");
		$coreComponentA9osCoreMain->setDesignPath("#main-content");
		$coreComponentA9osCoreMain->setOnlyOne("1");
		$coreComponentA9osCoreMain->setDataModel("a9os.core.main");
		$coreComponentA9osCoreMain->save();

		$coreComponentA9osCoreWindow = $this->getCore()->getModel("core.component");
		$coreComponentA9osCoreWindow->setComponentName("a9os_core_window");
		$coreComponentA9osCoreWindow->setDesignPath("#main-content .a9os-main");
		$coreComponentA9osCoreWindow->setOnlyOne("0");
		$coreComponentA9osCoreWindow->setDataModel("a9os.core.window");
		$coreComponentA9osCoreWindow->save();

		$coreControllerRoot = $this->getCore()->getModel($this);
		$coreControllerRoot->setPath("/");
		$coreControllerRoot->save();

		$coreController404 = $this->getCore()->getModel($this);
		$coreController404->setPath("[404]");
		$coreController404->save();

		$coreControllerComponent = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponent->setCoreControllerId($coreControllerRoot->getID());
		$coreControllerComponent->setCoreComponentId($coreComponentA9osCoreMain->getID());
		$coreControllerComponent->setOrder("0");
		$coreControllerComponent->save();

		$coreControllerComponent = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponent->setCoreControllerId($coreControllerRoot->getID());
		$coreControllerComponent->setCoreComponentId($coreComponent404->getID());
		$coreControllerComponent->setOrder("1");
		$coreControllerComponent->save();

		$coreControllerComponent = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponent->setCoreControllerId($coreController404->getID());
		$coreControllerComponent->setCoreComponentId($coreComponentA9osCoreMain->getID());
		$coreControllerComponent->setOrder("0");
		$coreControllerComponent->save();

		$coreControllerComponent = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponent->setCoreControllerId($coreController404->getID());
		$coreControllerComponent->setCoreComponentId($coreComponent404->getID());
		$coreControllerComponent->setOrder("1");
		$coreControllerComponent->save();

		$coreComponentDepend = $this->getCore()->getModel("core.component.depend");
		$coreComponentDepend->setCoreComponentId($coreComponentA9osCoreMain->getID());
		$coreComponentDepend->setChildId($coreComponentA9osCoreWindow->getID());
		$coreComponentDepend->setOrder("5");
		$coreComponentDepend->save();



		$coreControllerInstall = $this->getCore()->getModel($this);
		$coreControllerInstall->setPath("/install_a9os");
		$coreControllerInstall->save();

		$coreComponentInstall = $this->getCore()->getModel("core.component");
		$coreComponentInstall->setComponentName("a9os_core_install");
		$coreComponentInstall->setDesignPath(".a9os-main .window > .main-content");
		$coreComponentInstall->setDataModel("a9os.core.install");
		$coreComponentInstall->save();

		$coreControllerComponentInstall = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponentInstall->setCoreControllerId($coreControllerInstall->getID());
		$coreControllerComponentInstall->setCoreComponentId($coreComponentA9osCoreMain->getID());
		$coreControllerComponentInstall->setOrder("0");
		$coreControllerComponentInstall->save();

		$coreControllerComponentInstall = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponentInstall->setCoreControllerId($coreControllerInstall->getID());
		$coreControllerComponentInstall->setCoreComponentId($coreComponentInstall->getID());
		$coreControllerComponentInstall->setOrder("1");
		$coreControllerComponentInstall->save();


		$coreControllerInstallSubmit = $this->getCore()->getModel($this);
		$coreControllerInstallSubmit->setPath("/install_a9os/submit");
		$coreControllerInstallSubmit->save();

		$coreComponentInstallSubmit = $this->getCore()->getModel("core.component");
		$coreComponentInstallSubmit->setDataModel("a9os.core.install::submit");
		$coreComponentInstallSubmit->save();

		$coreControllerComponentInstallSubmit = $this->getCore()->getModel("core.controller.component");
		$coreControllerComponentInstallSubmit->setCoreControllerId($coreControllerInstallSubmit->getID());
		$coreControllerComponentInstallSubmit->setCoreComponentId($coreComponentInstallSubmit->getID());
		$coreControllerComponentInstallSubmit->setOrder("0");
		$coreControllerComponentInstallSubmit->save();



		$tableInfo["systemVersion"] = $this->getCore()::arrBaseVersion;
		$this->_updateTableInfo($tableInfo);
		return true;
	}

	private function updateSystem(){
		$tableInfo = $this->_getTableInfo();
		$tableInfoVersion = $tableInfo["systemVersion"];

		$codeVersion = $this->getCore()::arrVersion;

		if ($tableInfoVersion == $codeVersion) return false;

		$tableInfoVersion = $this->getCore()->arrVersionToInt($tableInfoVersion);


		if ($tableInfoVersion < 10001) {
			$coreComponentRequestsyskeyJsservice = $this->getCore()->getModel("core.component");
			$coreComponentRequestsyskeyJsservice->setComponentName("a9os_core_requestsyskey_jsservice");
			$coreComponentRequestsyskeyJsservice->setOnlyOne(1);
			$coreComponentRequestsyskeyJsservice->save();

			$coreComponentA9osCoreMain = $this->getCore()->getModel("core.component")->load("a9os_core_main", "component_name");

			$coreComponentDependRequestsyskeyJsservice = $this->getCore()->getModel("core.component.depend");
			$coreComponentDependRequestsyskeyJsservice->setCoreComponentId($coreComponentA9osCoreMain->getID());
			$coreComponentDependRequestsyskeyJsservice->setChildId($coreComponentRequestsyskeyJsservice->getID());
			$coreComponentDependRequestsyskeyJsservice->setOrder(20);
			$coreComponentDependRequestsyskeyJsservice->save();


			$coreComponentRequestsyskey = $this->getCore()->getModel("core.component");
			$coreComponentRequestsyskey->setComponentName("a9os_core_requestsyskey");
			$coreComponentRequestsyskey->setDesignPath(".a9os-main .window > .main-content");
			$coreComponentRequestsyskey->setDataModel("a9os.core.requestsyskey");
			$coreComponentRequestsyskey->save();

			$coreControllerRequestsyskey = $this->getCore()->getModel("core.controller");
			$coreControllerRequestsyskey->setPath("/requestsyskey");
			$coreControllerRequestsyskey->save();

			$coreControllerComponentRequestsyskey = $this->getCore()->getModel("core.controller.component");
			$coreControllerComponentRequestsyskey->setCoreControllerId($coreControllerRequestsyskey->getID());
			$coreControllerComponentRequestsyskey->setCoreComponentId($coreComponentA9osCoreMain->getID());
			$coreControllerComponentRequestsyskey->setOrder("0");
			$coreControllerComponentRequestsyskey->save();

			$coreControllerComponentRequestsyskey = $this->getCore()->getModel("core.controller.component");
			$coreControllerComponentRequestsyskey->setCoreControllerId($coreControllerRequestsyskey->getID());
			$coreControllerComponentRequestsyskey->setCoreComponentId($coreComponentRequestsyskey->getID());
			$coreControllerComponentRequestsyskey->setOrder("1");
			$coreControllerComponentRequestsyskey->save();



			$coreComponentRequestsyskeyTry = $this->getCore()->getModel("core.component");
			$coreComponentRequestsyskeyTry->setDataModel("a9os.core.requestsyskey::tryPass");
			$coreComponentRequestsyskeyTry->save();

			$coreControllerRequestsyskeyTry = $this->getCore()->getModel("core.controller");
			$coreControllerRequestsyskeyTry->setPath("/requestsyskey/try");
			$coreControllerRequestsyskeyTry->save();

			$coreControllerComponentRequestsyskeyTry = $this->getCore()->getModel("core.controller.component");
			$coreControllerComponentRequestsyskeyTry->setCoreControllerId($coreControllerRequestsyskeyTry->getID());
			$coreControllerComponentRequestsyskeyTry->setCoreComponentId($coreComponentRequestsyskeyTry->getID());
			$coreControllerComponentRequestsyskeyTry->setOrder("0");
			$coreControllerComponentRequestsyskeyTry->save();

		}


		$tableInfo["systemVersion"] = $codeVersion;
		$this->_updateTableInfo($tableInfo);

		return true;
	}




	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;

		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("path", "varchar(500)", false, true, false, "NULL");
			$tableHandle->createIndex("path", ["path"]);
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}

		return true;
	}
}