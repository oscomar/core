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

class core_component extends core_db_model{
	private static $ifDesktopInstalled;
	private static $a9osUserControl;


	public function loadComponents($arrComponentIds, $postData = []){
		self::$ifDesktopInstalled = $this->getCore()->getModel("a9os.core.install")->isInstalled();
		self::$a9osUserControl = $this->getCore()->getModel("a9os.user.control");

		if (empty($arrComponentIds)) return false;

		$arrFinalComponents = [];

		$arrComponentsFirstLevel = [];
		$componentCollection = $this->getCore()->getModel($this);
		$componentCollection->_setSelect("
			SELECT * 
			from {$componentCollection->getTableName()}

			where {$componentCollection->getPrimaryIdx()} in ('".implode("','", $arrComponentIds)."')
			order by field({$componentCollection->getPrimaryIdx()}, '".implode("','", $arrComponentIds)."')
		");

		while ($currComponent = $componentCollection->fetch()) {
			$arrComponentsFirstLevel[] = $currComponent;
		}

		function getComponentsAndChilds($arrComponents){
			$arrOutput = [];
			foreach ($arrComponents as $currComponent) {
				$arrOutput[] = $currComponent;
				$arrChilds = $currComponent->getComponentChilds();
				if($arrChilds) $arrOutput = array_merge($arrOutput, getComponentsAndChilds($arrChilds));
			}
			return $arrOutput;
		}
		$arrFinalComponents = getComponentsAndChilds($arrComponentsFirstLevel);

		$arrPreventComponents = json_decode($postData["preventComponents"]??"[]");
		$arrOutput = [];
		foreach ($arrFinalComponents as $currComponent) {
			if (in_array($currComponent->getComponentName(), $arrPreventComponents)) continue;
			$arrOutput[$currComponent->getComponentName()] = $currComponent->buildComponentOutput($postData);
			if ($arrOutput[$currComponent->getComponentName()] == false) unset($arrOutput[$currComponent->getComponentName()]);
		}


		return $arrOutput;
	}

	public function buildComponentOutput($postData){
		if (!$this->getID()) return false;

		try {
			if (self::$ifDesktopInstalled) {
				if (self::$a9osUserControl && !self::$a9osUserControl->componentAllowed($this)) {
					return false;
				}
			}
		} catch (Exception $e) {
			error_log($e);
		}

		$componentItem = [
			"html" => "",
			"js" => "",
			"css" => "",
			"designPath" => "",
			"clearPath" => false,
			"onlyOne" => false,

			"data" => [],
		];

		$newComponentItem = $componentItem;

		if (!empty($this->getComponentName())){
			$newComponentItem["html"] = $this->getComponentHTML();
			$newComponentItem["js"] = $this->getComponentJS();
			$newComponentItem["css"] = $this->getComponentCSS();
			$newComponentItem["designPath"] = $this->getDesignPath();
			$newComponentItem["clearPath"] = $this->getClearPath();
			$newComponentItem["onlyOne"] = (bool)$this->getOnlyOne();
		}


		if (!empty($this->getDataModel())){


			$arrDataModel = explode("::", trim($this->getDataModel()));
			$model = $arrDataModel[0];
			$function = "main";
			
			if (isset($arrDataModel[1])){
				$function = $arrDataModel[1];
			}

			try {
				if ($this->getIsAsyncOutput()) {
					$cliBridge = $this->getCore()->getModel("core.clibridge");
					if (!$cliBridge->isInCli()) {
						$newComponentItem["data"] = $cliBridge->execute($this->getDataModel(), $postData);
					}
				} else {
					$newComponentItem["data"] = $this->getCore()->getModel( $model )->$function($postData);
				}
			} catch (Exception $e) {
				if ($e->getMessage() == "__DEMO__") $newComponentItem["data"] = "__DEMO__";
				else if ($e->getCode() != 13) throw $e;
				else {
					error_log($e);
					$newComponentItem["data"] = $this->getCore()->getModel("a9os.core.requestsyskey")->getRequestLoadData($model, $function, $postData);
				}
			}
		}

		return $newComponentItem;
	}


	public function getComponentHTML(){
		$html = preg_replace('/<!--(.*)-->/Uis', "", $this->getComponentFile("html"));
		$html = trim($html);
		return $html;
	}

	public function getComponentJS(){
		$componentName = $this->getComponentName();

		$jsString = $this->getComponentFile("js");

		$jsString = "var ".$componentName." = {};\n".$jsString;

		$jsString .= "\n\n".$componentName.".__setContext = function(cd) {
			if (window.a9os_core_main) {
				core.sec.callOnlyFrom(".$componentName.".__setContext, {
					whitelist : [core.rxExecute, a9os_core_main.changeWindowScope]
				});
			} else {
				core.sec.callOnlyFrom(".$componentName.".__setContext, {
					whitelist : [core.rxExecute]
				});
			}

			self.component = cd.component||self.component; 
			self.event = cd.event||self.event;
		}";

		$jsString = preg_replace(["/\)\s*\=\>\s*\{/", "/function\s*\(.+\)\s*\{/"], "$0 var self = ".$componentName."; if (!self) return;", $jsString);

		return $jsString;
	}

	public function getComponentCSS(){
		return $this->getComponentFile("css");
	}

	public function getComponentFile($fileExtension){
		$componentName = $this->getComponentName();

		$arrModel = explode("_", $componentName);
		$strOut = "";
		
		$currBasePath = $fileExtension;

		$currPath = $this->getComponentPath($arrModel, $currBasePath);
		if (is_file($currPath.".".$fileExtension)){
			$strOut = file_get_contents($currPath.".".$fileExtension);
		} /*else if (is_dir($currPath)){
			$arrFiles = scandir($currPath);
			foreach ($arrFiles as $currFile) {
				if ($currFile[0] == ".") continue;
				$currFile = $currPath."/".$currFile;
				if (is_dir($currFile)) continue;
				if (!$this->validateExtension($currFile, $fileExtension)) continue;
				$strOut .= file_get_contents($currFile);
			}
		}*/ else {
			//error_log("El componente ".$componentName." (".$fileExtension.") no se pudo encontrar");
		}

		return $strOut;
	}


	public function getComponentPath($arrModel, $baseDir){
		$strPath = __DIR__."/../../".$baseDir;
		foreach ($arrModel as $currModelPart) {
			$strPath .= "/".$currModelPart;
		}
		return $strPath;
	}

	/*public function validateExtension($filePath, $extension){
		$arrPath = explode("/", $filePath);
		$currFile = $arrPath[count($arrPath)-1];

		$arrExtension = explode(".", $currFile);
		unset($arrExtension[0]);

		$currExtension = implode(".", array_values($arrExtension));
		$currExtension = strtolower($currExtension);
		
		if ($currExtension == $extension) return true;

		return false;
	}*/

	public function getComponentChilds(){
		if (!$this->getID()) return false;

		$componentDepend = $this->getCore()->getModel("core.component.depend");
		$componentDependsCollection = $componentDepend->getChilds($this);

		$arrComponentChilds = [];
		while($currComponentDepend = $componentDependsCollection->fetch()) {
			$coreComponentChild = $this->getCore()->getModel($this)->load($currComponentDepend->getChildId());
			$arrComponentChilds[] = $coreComponentChild;
		}

		if (empty($arrComponentChilds)) return false;
		return $arrComponentChilds;
	}

	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;
		
		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("component_name", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("design_path", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("clear_path", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("only_one", "tinyint(3)", false, false, false, "'0'");
			$tableHandle->addField("data_model", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("is_async_output", "tinyint(3)", false, false, false, "'0'");

			$tableHandle->createIndex("component_name", ["component_name"], "unique");
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}

		return true;
	}
}