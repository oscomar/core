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

class core_component_depend extends core_db_model{ 

	public function getChilds($coreComponent){
		$componentDepend = $this->getCore()->getModel($this);
		$componentDepend->_setSelect("
			SELECT *, ccd.order as pos
			from {$componentDepend->getTableName()} ccd
			where {$coreComponent->getPrimaryIdx()} = {$coreComponent->getID()}

			order by pos asc
		");

		return $componentDepend;
	}

	public function getLastOrderByComponent($parentComponent){
		if (!$parentComponent->getID()) return false;
		$componentDepend = $this->getCore()->getModel($this);
		$componentDepend->_setSelect("
			SELECT MAX(ccd.order) AS max_order FROM {$this->getTableName()} ccd
			WHERE {$parentComponent->getPrimaryIdx()} = {$parentComponent->getID()}
		");
		$componentDependMaxOrder = $componentDepend->fetch();
		if (!$componentDependMaxOrder) return 0;
		return $componentDependMaxOrder->getMaxOrder();
	}


	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;
		
		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("core_component_id", "int", false, false, false);
			$tableHandle->addField("child_id", "int", false, false, false);
			$tableHandle->addField("order", "int", false, false, false, "'0'");

			$tableHandle->createIndex("core_component_id", ["core_component_id"]);
			$tableHandle->createIndex("child_id", ["child_id"]);
			$tableHandle->createIndex("core_component_id_unq", ["core_component_id",  "child_id"], "unique");
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}

		return true;
	}
}