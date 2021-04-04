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

class core_db_bulksave extends core_db_model{
	public $arrItems = [];
	public $arrOriginalItems = [];
	private $firstModel = null;
	public function add($modelToSave){
		if (isset($this->firstModel) && get_class($this->firstModel) != get_class($modelToSave)){
			return false;
		}
		if (empty($this->arrItems)){
			$this->firstModel = $this->getCore()->getModel($modelToSave);
		}
		$this->arrItems[] = $modelToSave->getData();
		$this->arrOriginalItems[] = $modelToSave->_getOriginalData();
		return $this;
	}

	public function save(){
		$arrModels = $this->arrItems;
		if (empty($arrModels)){
			return false;
		}

		$firstModel = $this->firstModel;


		if (!$firstModel->_describe){
			$firstModel->_describe = $firstModel->getDescribe();
		}
		if (empty($firstModel->_describe)){
			throw new Exception("Model ".get_class($firstModel)." no tiene nombre de tabla", 7);
			
		}

		$arrFields = [];
		$arrFieldTypes = [];
		foreach ($firstModel->_describe as $fieldName => $fieldType) {
			$arrFields[] = $fieldName;
			$arrFieldTypes[] = $fieldType;
			
		}

		if (($idIdx = array_search($firstModel->getPrimaryIdx(), $arrFields)) !== false){
			$idIdx--;
		} else {
			return false;
		}

		$arrChunksToUpdate = [];
		$arrChunksToInsert = [];
		foreach ($arrModels as $j => $currModel) {
			$firstModel->setData($currModel);
			$currModel = $firstModel; // violent PATCH - prevent keep open multiple conexiones

			$arrValues = [];
			foreach ($arrFields as $k => $currField) {
				if  (!$currModel->getID() && $k == $idIdx) continue;

				$getter = $currModel->getGetter($currField);
				$currValue = $currModel->$getter();
				if ($arrFieldTypes[$k] == "json"){
					$currValue = json_encode($currValue);
				}
				if (substr($arrFieldTypes[$k], 0, 3) == "set"){
					$currValue = implode(",", (array)$currValue);
				}
				if (is_null($currValue)){
					$arrValues[] = "NULL";
				} else {
					$arrValues[] = $this->_quote($currValue);
				} 
			}
			$sql = "(".implode(",", $arrValues).")";

			if ($currModel->getID()){
				$arrChunksToUpdate[] = $sql;
			} else {
				$arrChunksToInsert[] = $sql;
			}
		}


		$arrFieldsToInsert = $arrFields;
		if (!empty($arrChunksToInsert)){
			unset($arrFieldsToInsert[$idIdx]);
			$arrFieldsToInsert = array_values($arrFieldsToInsert);

			$sqlInsert = "INSERT into ".$firstModel->getTableName()." (".implode(",", $arrFieldsToInsert).") values ";
			$sqlInsert .= implode(",", $arrChunksToInsert);
			if (!$firstModel->_query($sqlInsert)){
				throw new Exception($firstModel->_getSqlError());
			}
		}

		if (!empty($arrChunksToUpdate)){
			$arrDuplicateMatchs = [];
			foreach ($arrFieldsToInsert as $currFieldToInsert) {
				$arrDuplicateMatchs[] = $currFieldToInsert."=VALUES(".$currFieldToInsert.")";
			}
			$sqlUpdate = "INSERT into ".$firstModel->getTableName()." (".implode(",", $arrFields).") values ";
			$sqlUpdate .= implode(",", $arrChunksToUpdate);
			$sqlUpdate .= " on duplicate key update ".implode(",", $arrDuplicateMatchs);
			if (!$firstModel->_query($sqlUpdate)){
				throw new Exception($firstModel->_getSqlError());
			}
		}
		return $this;
	}
}