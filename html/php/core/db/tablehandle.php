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

final class core_db_tablehandle extends core_db_model{
	private $arrCurrentFields = [];
	private $arrFieldsToAdd = [];
	private $arrIndexesToCreate = [];
	private $arrIndexesToDelete = [];
	private $arrFiledsToChange = [];
	private $arrFieldsToDelete = [];
	private $arrTableInfo = [];

	public function addCreatedField($field){
		$this->arrCurrentFields[] = $field;
	}


	public function setOriginTableName($v){
		if (parent::getOriginTableName()) return false;
		return parent::setOriginTableName($v);
	}
	public function setOriginPrimaryIdx($v){
		if (parent::getOriginPrimaryIdx()) return false;
		return parent::setOriginPrimaryIdx($v);
	}
	public function setTableCreated($v){
		return parent::setTableCreated($v);
	}


	public function addField($name, $type, $unsigned = false, $null = false, $zeroFill = false, $defaultString = ""){
		if ($this->getTableCreated()) {
			$name = mb_strtolower($name);
			$name = trim($name);
			if (in_array($name, $this->arrCurrentFields)) {
				error_log("addField: ".$name." - campo existente");
				return false;
			}
		}

		$this->arrFieldsToAdd[] = [
			"name" => $name,
			"type" => $type,
			"unsigned" => $unsigned,
			"null" => $null,
			"zeroFill" => $zeroFill,
			"defaultString" => $defaultString
		];
	}

	public function createIndex($name, $arrFields, $indexType = false){
		$this->arrIndexesToCreate[] = [
			"name" => $name,
			"arrFields" => $arrFields,
			"indexType" => $indexType
		];
	}

	public function deleteIndex($name){
		$this->arrIndexesToDelete[] = [
			"name" => $name
		];
	}

	public function changeField($originalName, $newName, $type, $unsigned = false, $null = false, $zeroFill = false, $defaultString = ""){
		$this->arrFiledsToChange[] = [
			"originalName" => $originalName,
			"newName" => $newName,
			"type" => $type,
			"unsigned" => $unsigned,
			"null" => $null,
			"zeroFill" => $zeroFill,
			"defaultString" => $defaultString
		];
	}

	public function deleteField($name){
		$this->arrFieldsToDelete[] = [
			"name" => $name
		];
	}

	public function setTableInfo($arrTableInfo){
		$this->arrTableInfo = $arrTableInfo;
	}

	public function save(){
		$queryStr = "";
		if ($this->getTableCreated()) {
			$queryStr = $this->getSaveAlterTable();
		} else {
			$queryStr = $this->getSaveCreateTable();
			$this->setTableCreated(true);
		}


		//error_log($queryStr);


		if (!$this->_query($queryStr)){
			throw new Exception("TABLEHANDLESAVE | ".$this->_getSqlError());
		}

		$this->_updateTableInfo($this->arrTableInfo, $this->getOriginTableName());

		

		$this->arrCurrentFields = [];
		$this->arrFieldsToAdd = [];
		$this->arrIndexesToCreate = [];
		$this->arrIndexesToDelete = [];
		$this->arrFiledsToChange = [];
		$this->arrFieldsToDelete = [];
		$this->arrTableInfo = [];
	}

	public function getSaveCreateTable(){
		$createStr = "CREATE TABLE `".$this->getOriginTableName()."` ( \n";
		$createStr .= "`".$this->getOriginPrimaryIdx()."` INT NOT NULL AUTO_INCREMENT,";
		foreach ($this->arrFieldsToAdd as $k => $currFieldToAdd) {
			$createStr .= $this->createFieldLine(
				$currFieldToAdd["name"], 
				$currFieldToAdd["type"], 
				$currFieldToAdd["unsigned"], 
				$currFieldToAdd["null"], 
				$currFieldToAdd["zeroFill"], 
				$currFieldToAdd["defaultString"]
			);

			$createStr .= ", \n";
		}
		$createStr .= "PRIMARY KEY (`".$this->getOriginPrimaryIdx()."`) USING BTREE, \n";
		foreach ($this->arrIndexesToCreate as $k => $currIndexToCreate) {
			$createStr .= $this->createIndexLine(
				$currIndexToCreate["name"],
				$currIndexToCreate["arrFields"],
				$currIndexToCreate["indexType"],
			);
			$createStr .= ", \n";
		}

		$createStr = substr($createStr, 0, -3);
		$createStr .= ")\n";
		$createStr .= "COLLATE='utf8_general_ci'\n";
		$createStr .= "ENGINE=InnoDB;\n";

		return $createStr;
	}

	public function getSaveAlterTable(){
		$createStr = "ALTER TABLE `".$this->getOriginTableName()."` \n";
		foreach ($this->arrFieldsToAdd as $k => $currFieldToAdd) {
			$createStr .= "ADD COLUMN ".$this->createFieldLine(
				$currFieldToAdd["name"], 
				$currFieldToAdd["type"], 
				$currFieldToAdd["unsigned"], 
				$currFieldToAdd["null"], 
				$currFieldToAdd["zeroFill"], 
				$currFieldToAdd["defaultString"]
			);

			$createStr .= ", \n";
		}

		foreach ($this->arrFiledsToChange as $k => $currFieldToChange) {

			$createStr .= "CHANGE COLUMN `".trim(mb_strtolower($currFieldToChange["originalName"]))."` ".$this->createFieldLine(
				$currFieldToChange["newName"], 
				$currFieldToChange["type"], 
				$currFieldToChange["unsigned"], 
				$currFieldToChange["null"], 
				$currFieldToChange["zeroFill"], 
				$currFieldToChange["defaultString"]
			);

			$createStr .= ", \n";
		}

		foreach ($this->arrFieldsToDelete as $k => $currFieldToDelete) {
			$createStr .= "DROP COLUMN `".trim(mb_strtolower($currFieldToDelete["name"]))."`, \n";
		}

		foreach ($this->arrIndexesToDelete as $k => $currIndexToDelete) {
			$createStr .= "DROP INDEX `".trim(mb_strtolower($currIndexToDelete["name"]))."`, \n";
		}

		foreach ($this->arrIndexesToCreate as $k => $currIndexToCreate) {
			$createStr .= "ADD ".$this->createIndexLine(
				$currIndexToCreate["name"],
				$currIndexToCreate["arrFields"],
				$currIndexToCreate["indexType"],
			);
			$createStr .= ", \n";
		}

		$createStr = substr($createStr, 0, -3);
		$createStr .= ";";

		return $createStr;
	}



	public function createFieldLine($name, $type, $unsigned, $null, $zeroFill, $defaultString){
		if ($name == "" || $type == "") return false;
		$name = mb_strtolower($name);
		$name = trim($name);
		$type = trim($type);
		$defaultString =trim($defaultString, " ");

		$unsignedStr = "";
		if ($unsigned) $unsignedStr = " UNSIGNED";

		$nullStr = " NOT NULL";
		if ($null) $nullStr = " NULL";

		$zeroFillStr = "";
		if ($zeroFill) $zeroFillStr = " ZEROFILL";

		$defaultStringStr = "";
		if ($defaultString && $defaultString != "") $defaultStringStr = " DEFAULT ".$defaultString;


		return "`".$name."` ".$type.$unsignedStr.$zeroFillStr.$nullStr.$defaultStringStr;
	}

	public function createIndexLine($name, $arrFields, $indexType){
		$indexTypeStr = "";
		if (strtolower($indexType) == "unique") $indexTypeStr = "UNIQUE ";
		if (strtolower($indexType) == "spatial") $indexTypeStr = "SPATIAL ";
		if (strtolower($indexType) == "fulltext") $indexTypeStr = "FULLTEXT ";

		$name = mb_strtolower($name);
		$name = trim($name);

		foreach ($arrFields as $k => $currKey) {
			$arrFields[$k] = "`".trim(mb_strtolower($currKey))."`";
		}

		$btreeStr = " USING BTREE";
		if (strtolower($indexType) == "fulltext") $btreeStr = "";


		return $indexTypeStr."INDEX `".$name."` (".implode(", ", $arrFields).")".$btreeStr;
	}


	public function migrateOldTable($oldTablemodelName){
		$oldTablemodelName = trim(mb_strtolower($oldTablemodelName));

		if (empty($oldTablemodelName)) return false;
		

		$modelExists = true;
		try {
			$thisModelShouldNotExists = $this->getCore()->getModel($this->getCore()->getModelDescriptorByClassName($oldTablemodelName));
		} catch (Exception $e) {
			$modelExists = false;
		}

		if ($modelExists) throw new Exception("tablehandle - migrateOldTable : Try to migrate existing model. Delete the model before.");
		




		try {
			$this->_query("RENAME TABLE ".$oldTablemodelName." TO ".$this->getOriginTableName());
		} catch (Exception $e) {
			return false;
		}

		$oldTableIdx = $oldTablemodelName."_id";
		$this->_query("
			ALTER TABLE {$this->getOriginTableName()} 
			CHANGE COLUMN `{$oldTableIdx}` `{$this->getOriginPrimaryIdx()}` INT NOT NULL AUTO_INCREMENT,
			DROP PRIMARY KEY,
			ADD PRIMARY KEY (`{$this->getOriginPrimaryIdx()}`) USING BTREE;
		");
		$this->setTableCreated(true);
		return true;

	}


}