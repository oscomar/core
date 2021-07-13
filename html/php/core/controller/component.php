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

class core_controller_component extends core_db_model {


	public function filterComponentsByActuatorController($actuatorControllerPath, $arrAggregativeControllerComponents, $arrComponentIds){
		$actuatorControllerPath = explode("?", $actuatorControllerPath)[0];

		$actuatorController = $this->getCore()->getModel("core.controller")->load($actuatorControllerPath, "path");

		if (!$actuatorController) return $arrComponentIds;


		$actuatorControllerComponentCollection = $this->getCore()->getModel($this);
		$actuatorControllerComponentCollection->_setSelect("
			SELECT * from {$actuatorControllerComponentCollection->getTableName()}
			where {$actuatorController->getPrimaryIdx()} = {$this->_quote($actuatorController->getID())}
		");

		$arrFilteredAggregativeComponentIds = [];

		$arrActuatorComponentIds = [];
		while ($currActuatorControllerComponent = $actuatorControllerComponentCollection->fetch()) {
			$arrActuatorComponentIds[] = $currActuatorControllerComponent->getCoreComponentId();
		}

		foreach ($arrAggregativeControllerComponents as $currAggregativeControllerComponent) {
			if (in_array($currAggregativeControllerComponent->getCoreComponentId(), $arrActuatorComponentIds)) continue;
			$arrFilteredAggregativeComponentIds[] = $currAggregativeControllerComponent->getCoreComponentId();
		}

		return $arrFilteredAggregativeComponentIds;

	}



	public function getArrComponentUrls(){
		$ccc = $this->getCore()->getModel($this);
		$cc = $this->getCore()->getModel("core.component");
		$cct = $this->getCore()->getModel("core.controller");

		$ccc->_setSelect("
			SELECT cct.path, ccc.core_component_id
			FROM {$ccc->getTableName()} ccc
			LEFT JOIN {$cc->getTableName()} cc ON (cc.{$cc->getPrimaryIdx()} = ccc.{$cc->getPrimaryIdx()})
			LEFT JOIN {$cct->getTableName()} cct ON (cct.{$cct->getPrimaryIdx()} = ccc.{$cct->getPrimaryIdx()})

			where cc.component_name is not null

			ORDER BY ccc.{$cct->getPrimaryIdx()}, ccc.`order` asc
		");

		$arrOutput = [];

		while ($currCcc = $ccc->fetch()) {
			$currPath = $currCcc->getPath();
			$currComponentId = $currCcc->getCoreComponentId();

			if (!$currPath) continue;

			if (!isset($arrOutput[$currComponentId])) {
				$arrOutput[$currComponentId] = $currPath;
			}
		}

		return $arrOutput;
	}


	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;
		
		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 2) return false;

		if (!$tableInfo) {
			$tableHandle->addField("core_controller_id", "int", false, true, false, "NULL");
			$tableHandle->addField("core_component_id", "int", false, true, false, "NULL");
			$tableHandle->addField("order", "int", false, false, false, "'0'");
			$tableHandle->createIndex("core_controller_id", ["core_controller_id"]);
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}
		if ($tableInfo["version"] < 2) {
			$tableHandle->addField("aggregative_actuator_controller_id", "int", false, true, false, "NULL");

			$tableInfo = ["version" => 2];

			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}
		return true;
	}
}