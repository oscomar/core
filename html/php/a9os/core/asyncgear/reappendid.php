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

class a9os_core_asyncgear_reappendid extends a9os_core_asyncgear { 
	public function addNew($data){
		$gearId = $data["data"]["gearId"];
		$reappendId = $data["data"]["reappendId"];
		$userId = $this->getCore()->getModel("a9os.user")->getSessionUser()->getID();

		$newReappendId = $this->getCore()->getModel($this);
		$newReappendId->setA9osUserId($userId);
		$newReappendId->setGearId($gearId);
		$newReappendId->setReappendId($reappendId);
		$newReappendId->save();

		return true;
	}

	public function getById($data){
		$reappendId = $data["data"]["reappendId"];

		$user = $this->getCore()->getModel("a9os.user")->getSessionUser();

		$reappendidCollection = $this->getCore()->getModel($this);
		$reappendidCollection->_setSelect("
			SELECT * 
			from {$reappendidCollection->getTableName()}
			where reappend_id = '{$reappendId}'
			and {$user->getPrimaryIdx()} = {$user->getID()}");


		$arrOutput = [];
		while($currReapendidCollection = $reappendidCollection->fetch()) {
			$currGearId = $currReapendidCollection->getGearId();

			$asyncgearIfFinished = $this->getCore()->getModel("a9os.core.asyncgear");
			$asyncgearIfFinished->_setSelect("
				SELECT * from {$asyncgearIfFinished->getTableName()}
				where gear_id = {$this->_quote($currGearId)}
				and is_final_message = 1
			");

			while ($currAsyncgearFinished = $asyncgearIfFinished->fetch()) {
				$currReapendidCollection->delete();
				continue;
			}


			$arrOutput[] = $currGearId;
		}
		
		return $arrOutput;
	}




	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;

		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("a9os_user_id", "int", false, false, false);
			$tableHandle->addField("gear_id", "varchar(10)", false, false, false);
			$tableHandle->addField("reappend_id", "varchar(255)", false, false, false);
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}


		return true;
	}
}