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

class cron_task extends core_db_model{
	public function execute(){
		$this->setSemStatus("r");
		$this->save();
		$this->getCore()->fork(function($parent){
			try {
				$model = $parent->getCore()->getModel($parent->getDataModel());
				$returnArray = $model->loadByCron($parent);
				$parent->setLastExecDate(date("Y-m-d H:i:s"));
				$parent->setLastExecInfo($returnArray);
				$parent->setSemStatus("s");
				$parent->setLastExecTime($parent->getCore()->getExecTime());
				$parent->save();
			} catch (Exception $e) {
				$parent->setSemStatus("e");
				$parent->setLastExecInfo(array("error" => "Exception: ".$e->getMessage()));
				$parent->setLastExecTime($parent->getCore()->getExecTime());
				$parent->save();
			}
		}, $this);

		return true;
	}


	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;
		
		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("name", "varchar(50)", false, true, false, "NULL");
			$tableHandle->addField("enabled", "int", false, false, false, "'0'");
			$tableHandle->addField("frequency", "varchar(50)", false, true, false, "NULL");
			$tableHandle->addField("data_model", "varchar(255)", false, true, false, "NULL");
			$tableHandle->addField("sem_status", "ENUM('s','r','e')", false, false, false, "'s'");
			$tableHandle->addField("last_exec_date", "DATETIME", false, true, false, "NULL");
			$tableHandle->addField("last_exec_info", "LONGTEXT", false, true, false, "NULL");


			$tableHandle->createIndex("name", ["name", "frequency"], "unique");
			$tableInfo = ["version" => 1];

			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}
		return true;
	}
}