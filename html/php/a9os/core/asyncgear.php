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

class a9os_core_asyncgear extends a9os_core_main {

	public function asyncProcess($childCall, ...$extraData){

		
		$newGearId = $this->getNewGearId();
		$this->setGearId($newGearId);

		$this->getCore()->fork(function($parent, $childCall, $extraData, $newGearId) {
			try {
				$processReturn = call_user_func_array($childCall, array_merge([$parent], $extraData));
				if (is_object($processReturn)) {
					$processReturn = $processReturn->getData();
				}
			} catch (Exception $e) {
				error_log($e);

				$processReturn = "error";
			}

			$parent->setIsFinalMessage(true);
			$parent->pushMessage($processReturn);

			$reappendId = $this->getCore()->getModel("a9os.core.asyncgear.reappendid");
			$reappendId->load($newGearId, "gear_id");
			if ($reappendId) $reappendId->delete();

		}, $this, $childCall, $extraData, $newGearId);

		return $newGearId;
	}

	public function pushMessage($msg){
		$persistent = $this->getIsPersistent()??"0";
		$finalMsg = $this->getIsFinalMessage()??"0";


		$stackRegistry = $this->getCore()->getModel($this);
		$stackRegistry->setA9osUserId($this->getCore()->getModel("a9os.user")->getSessionUser()->getID());
		$stackRegistry->setDateAdd(date('Y-m-d H:i:s'));
		$stackRegistry->setGearId($this->getGearId());
		$stackRegistry->setMessage(json_encode($msg));
		$stackRegistry->setIsFinalMessage($finalMsg);
		$stackRegistry->setIsPersistent($persistent);
		$stackRegistry->save();
	}


	public function getNewGearId(){
		$distinctGearIdCll = $this->getCore()->getModel($this);
		$distinctGearIdCll->_setSelect("SELECT distinct gear_id
			from {$distinctGearIdCll->getTableName()}");

		$arrCompareGearIds = [];
		while($currDistinctGearId = $distinctGearIdCll->fetch()) {
			$arrCompareGearIds[] = $currDistinctGearId->getGearId();
		}

		$newGearId = $this->getCore()->getRandomId($arrCompareGearIds);

		return $newGearId;
	}


	public function getMessages($data){
		$arrGearsToSend = $data["data"]["arrGearsToSend"];

		$arrReturn = [];

		$msgCollection = $this->getCore()->getModel($this);
		$user = $this->getCore()->getModel("a9os.user")->getSessionUser();
		$msgCollection->_setSelect("
			SELECT * from {$msgCollection->getTableName()} 
			where {$user->getPrimaryIdx()} = {$user->getID()}
			order by gear_id, {$msgCollection->getPrimaryIdx()} asc
		");

		while ($currMsg = $msgCollection->fetch()) {
			if (time() - strtotime($currMsg->getDateAdd()) > 60*1) {
				$currMsg->delete();
				continue;
			}

			if (!in_array($currMsg->getGearId(), array_keys($arrGearsToSend))) {
				continue;
			}

			if (!isset($arrReturn[$currMsg->getGearId()])) {
				$arrReturn[$currMsg->getGearId()] = [];
			}

			if ($arrGearsToSend[$currMsg->getGearId()] == 0 || $arrGearsToSend[$currMsg->getGearId()] < $currMsg->getID()) {
				$arrReturn[$currMsg->getGearId()][] = [
					"gear_id" => $currMsg->getGearId(),
					"message_id" => $currMsg->getId(),
					"message" => json_decode($currMsg->getMessage()),
					"date_add" => $currMsg->getDateAdd(),
					"is_final_message" => $currMsg->getIsFinalMessage(),
					"is_persistent" => $currMsg->getIsPersistent()
				];

				/*if ($currMsg->getIsPersistent() == 0) {
					$currMsg->delete();
				}*/
			}
		}

		return $arrReturn;
	}




	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;

		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("a9os_user_id", "int", false, false, false);
			$tableHandle->addField("date_add", "DATETIME", false, true, false, "NULL");
			$tableHandle->addField("gear_id", "varchar(10)", false, true, false, "NULL");
			$tableHandle->addField("message", "TEXT", false, true, false, "NULL");
			$tableHandle->addField("is_final_message", "int", false, false, false, "'0'");
			$tableHandle->addField("is_persistent", "int", false, false, false, "'0'");

			$tableHandle->createIndex("gear_id", ["gear_id"]);
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}


		return true;
	}
}