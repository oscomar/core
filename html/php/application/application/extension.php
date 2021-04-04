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

class application_application_extension extends application_application{
	public function getApplicationByExtension($currFileExtension) {
		$arrAppByExtension = $this->getAppExtensionList();

		if (isset($arrAppByExtension[$currFileExtension])){
			return $arrAppByExtension[$currFileExtension];
		} else {
			return [
				"name" => "open-with",
				"icon_url" => "/resources/a9os/app/vf/icons/files/folder-icon.svg",
				"path" => "/vf/openwith"
			];
		}
	}


	public function getAppExtensionList(){
		if (parent::getAppExtensionList())
			return parent::getAppExtensionList();

		$aaed = $this->getCore()->getModel("application.application.extension.default");
		$aa = $this->getCore()->getModel("application.application");
		$cc = $this->getCore()->getModel("core.controller");
		$cca = $this->getCore()->getModel("core.controller.application");
		$user = $this->getCore()->getModel("a9os.user")->getSessionUser();
		$aaed = $aaed->_setSelect("
			SELECT aaed.{$aaed->getPrimaryIdx()},
			aaed.file_extension,
			aa.name,
			aa.icon_url,
			cc.path 

			from {$aaed->getTableName()} aaed

			left join {$aa->getTableName()} aa 
				on (aa.{$aa->getPrimaryIdx()} = aaed.{$aa->getPrimaryIdx()})

			left join {$cca->getTableName()} cca
				on (cca.{$aa->getPrimaryIdx()} = aa.{$aa->getPrimaryIdx()})

			left join {$cc->getTableName()} cc 
				on (cc.{$cc->getPrimaryIdx()} = cca.{$cc->getPrimaryIdx()})

			where aaed.{$user->getPrimaryIdx()} = {$user->getID()}
			and cca.is_main_window = 1
		");

		$arrAll = [];
		while ($currExtension = $aaed->fetch()){
			$arrAll[$currExtension->getFileExtension()] = $currExtension->getData();
		}

		$this->setAppExtensionList($arrAll);
		return $arrAll;
	}



	public function addApplicationExtensions($appAppObj, $arrExtensions){
		$appAppId = $appAppObj->getID();
		foreach ($arrExtensions as $currExtension => $arrExtensionInfo) {
			$newAppAppExtension = $this->getCore()->getModel("application.application.extension");
			$newAppAppExtension->setApplicationApplicationId($appAppId);
			$newAppAppExtension->setFileExtension($currExtension);
			if (isset($arrExtensionInfo["icon_file"])) {
				$newAppAppExtension->setIconFile($arrExtensionInfo["icon_file"]);
			}
			if (isset($arrExtensionInfo["make_preview_model"])) {
				$newAppAppExtension->setMakePreviewModel($arrExtensionInfo["make_preview_model"]);
			}
			$newAppAppExtension->save();
		}
	}


	public function getFileExtensionByPath($path){
		$extension = explode("/", $path);
		$extension = $extension[ count($extension)-1 ];
		$extension = explode(".", $extension);
		array_shift($extension);
		$extension = $extension[ count($extension)-1 ];
		$extension = trim($extension);
		$extension = mb_strtoupper($extension);

		return $extension;
	}

	public function getFileExtensionList(){
		if (parent::getFileExtensionList())
			return parent::getFileExtensionList();

		$a9osUser = $this->getCore()->getModel("a9os.user")->getSessionUser();

		$fileExtensionCollection = $this->getCore()->getModel("application.application.extension");
		$fileExtensionCollection->_setSelect("SELECT aae.*
			FROM {$fileExtensionCollection->getTableName()} aae
			LEFT JOIN application_application_extension_default aaed ON (aae.file_extension = aaed.file_extension AND aae.application_application_id = aaed.application_application_id AND aaed.a9os_user_id = {$a9osUser->getID()})

			ORDER BY aaed.application_application_id ASC, aae.application_application_id ASC
		");


		$arrAll = [];
		while ($currFileExtension = $fileExtensionCollection->fetch()){
			$arrAll[$currFileExtension->getFileExtension()] = $currFileExtension;
		}

		$this->setFileExtensionList($arrAll);

		return $arrAll;
	}

	public function getFileIconByPath($path){
		$fileExtension = $this->getFileExtensionByPath($path);
		$arrExtensionList = $this->getFileExtensionList();

		if (!in_array($fileExtension, array_keys($arrExtensionList))) return false;

		return $arrExtensionList[ $fileExtension ]->getIconFile();
	}




	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;

		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("application_application_id", "int", false, false, false);
			$tableHandle->addField("file_extension", "varchar(10)", false, true, false, "NULL");
			$tableHandle->addField("icon_file", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("make_preview_model", "varchar(255)", false, true, false, "NULL");

			$tableHandle->createIndex("application_application_id", ["application_application_id"]);
			$tableHandle->createIndex("file_extension", ["file_extension"]);
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}


		return true;
	}
}