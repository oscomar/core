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

class core_db_model extends core_mainobject{
	private $_currCursor = null;

	private $_describe = null;

	private $_items = [];

	private $_itemIdx = 0;

	private $_select = null;

	private static $_sql = null;

	private static $_sqlDbCredentials = [];

	private static $_arrTablesInfo = [];

	protected function _query($query){
		$protectionModel = $this->_getProtection();
		$protectionModel->callOnlyFrom([
			"whitelist" => ["core_db_*"]
		]);
		
		$return = $this->_getSql()->query($query);

		$realError = false;
		$prevError = "";
		while (!$realError) {
			if ($return) {
				$realError = true;
				break;
			} else {
				if ($this->_getSql()->error == $prevError) {
					throw new Exception($this->_getSql()->error);
				} else {
					$prevError = $this->_getSql()->error;
				}
				if ($this->_getSql()->errno == 1146) {
					$tableNameToCreate = explode("'", $this->_getSql()->error);
					$tableNameToCreate = $tableNameToCreate[1];
					$tableNameToCreate = explode(".", $tableNameToCreate);
					$tableNameToCreate = $tableNameToCreate[1];
					$modelNameToCreate = $this->getCore()->getModelDescriptorByClassName($tableNameToCreate);
					try {
						$modelToCreate = $this->getCore()->getModel($modelNameToCreate);
						if ($modelToCreate) $modelToCreate->load("0");
					} catch (Exception $e2) {
						$realError = true;
						throw $e2;
					}

					$return = $this->_getSql()->query($query);
				} else {
					error_log($this->_getSql()->errno."||".$this->_getSql()->error);
					$realError = true;
				}
			}
		}

		return $return;
	}

	private function _getSql(){
		if (!is_null(self::$_sql)){
			return self::$_sql;
		}

		if (empty(self::$_sqlDbCredentials)) {
			$protectionModel = $this->_getProtection();
			self::$_sqlDbCredentials = $protectionModel->getDbCredentials();
		}


		self::$_sql = new mysqli(
			self::$_sqlDbCredentials["host"], 
			self::$_sqlDbCredentials["user"], 
			self::$_sqlDbCredentials["password"], 
			self::$_sqlDbCredentials["db"]
		);
		
		if (self::$_sql->connect_errno) {
			throw new Exception("Fallo al conectar a MySQL: " . self::$_sql->connect_error);
		}

		if (!self::$_sql){
			throw new Exception("load: sin conexion a DB", 5);
			return false;
		}

		self::$_sql->set_charset("utf8");

		return self::$_sql;
	}
	public function _quote($str){
		if (is_null($str)){
			return "NULL";
		}
		if (is_numeric($str)){
			return (int)$str;
		}
		return "'".$this->_getSql()->real_escape_string($str)."'";
	}

	public function _resetSqlByFork(){
		$this->closeDb();
		self::$_sql = null;
	}

	public function _getSqlError(){
		return $this->_getSql()->error;
	}

	public function closeDb(){
		if ($this->_getSql()) $this->_getSql()->close();
		//error_log("DB closed");
	}

	function __destruct() {

	}

	// load (primaryIdxID || CustomFieldValue || arrData  ,  CustomFieldName)
	public function load($id, $field = ""){
		if ($this->getData()){
			return $this;
		}
		if (is_array($id)){
			$id["id"] = $id[$this->getPrimaryIdx()]??false;
			unset($id[$this->getPrimaryIdx()]);
			$this->setData($id);
			return $this;
		}

		if (!$this->_describe){
			$this->_describe = $this->getDescribe();
		}


		if ($this->executeManageTable()) {
			$this->_describe = $this->getDescribe();
		}


		$sqlLoad = "SELECT * from ".$this->getTableName()." where ";
		if ($field){
			$sqlLoad .= $field;
		} else {
			$sqlLoad .= $this->getPrimaryIdx();
		}

		$sqlLoad .= " = '".$this->_getSql()->real_escape_string($id)."'";
		//die($sqlLoad);
		$curLoad = $this->_query($sqlLoad);
		//error_log(var_export($sqlLoad, true));
		if (!$curLoad){
			error_log($this->_getSqlError());
			throw new Exception("load: sin conexion a DB", 6);
		}

		$arrFinalData = $this->fetchAssocConvertMultidata($curLoad->fetch_assoc());
		if (is_null($arrFinalData)) return false;
		
		$arrFinalData["id"] = $arrFinalData[$this->getPrimaryIdx()];
		unset($arrFinalData[$this->getPrimaryIdx()]);

		$this->setData($arrFinalData);
		$this->_select = $sqlLoad;

		return $this;
	}


	public function fetchAssocConvertMultidata($arrRow){
		if (!is_array($arrRow)){
			return $arrRow;
		}
		if (!$this->_describe){
			$this->_describe = $this->getDescribe();
		}
		$arrOutput = [];
		foreach ($arrRow as $k => $v) {
			if (isset($this->_describe[$k]) && substr($this->_describe[$k], 0, 3) == "set"){
				$arrOutput[$k] = explode(",", $v);
			} else if (isset($this->_describe[$k]) && $this->_describe[$k] == "json") {
				$arrOutput[$k] = json_decode($v, true);
			} else {
				$arrOutput[$k] = $v;
			}
		}

		return $arrOutput;
	}

	protected function _getTableInfo() {
		$tableName = $this->getTableName();

		if (!empty(self::$_arrTablesInfo)) return self::$_arrTablesInfo[$tableName]??false;

		$tableVersionCollection = $this->_query("show table status");
		while ($currTableVersion = $tableVersionCollection->fetch_assoc()) {
			try {
				self::$_arrTablesInfo[ $currTableVersion["Name"] ] = json_decode($currTableVersion["Comment"], true)??[];
			} catch (Exception $e) {
				self::$_arrTablesInfo[ $currTableVersion["Name"] ] = [];
			}
		}

		return self::$_arrTablesInfo[$tableName]??false;
	}

	protected function _updateTableInfo($arrNewTableInfo, $tableName = false){
		if (!$tableName) $tableName = $this->getTableName();
		$alterCommentSql = "ALTER TABLE `".$tableName."` COMMENT=".$this->_quote(json_encode($arrNewTableInfo)).";";

		if (!$this->_query($alterCommentSql)){
			throw new Exception("ALTERCOMMENTSQL | ".$this->_getSqlError());
		}

		$this->reloadTableInfo();
	}

	private function reloadTableInfo(){
		self::$_arrTablesInfo = [];
	}


	private function getDescribe(){
		if (empty($this->getTableName())){
			return [];
		}
		$describe = $this->_getSql()->query("describe ".$this->getTableName());
		$arrOutput = [];
		if (!$describe) return $arrOutput;
		while ($currDsc = $describe->fetch_assoc()) {
			$arrOutput[ $currDsc["Field"] ] = $currDsc["Type"];
		}

		return $arrOutput;
	}

	public function getID(){
		return parent::getId();
	}

	public function setID($value){
		$this->originalData["id"] = $value;
		return parent::setId($value);
	}

	public function getTableName(){
		$str = get_class($this);

		$arrStr = explode("_", $str);
		$finalStr = "";
		$k = 0;
		foreach ($arrStr as $currStr) {

			$finalStr .= $currStr."_";
			$k++;
		}
		$finalStr = trim($finalStr, "_");
		return $finalStr;
	}

	public function getPrimaryIdx(){
		return $this->getTableName()."_id";
	}

	public function _setSelect($query){
		if (!$this->protectSelect($query)) return false;

		if (!$this->_describe){
			$this->_describe = $this->getDescribe();
		}

		if ($this->executeManageTable()) {
			$this->_describe = $this->getDescribe();
		}

		
		$this->_select = $query;
		$this->_currCursor = $this->_query($query);

		return $this;
	}
	public function _getSelect(){
		return $this->_select;
	}

	public function protectSelect($query){
		$query = str_replace("\n", "", $query);
		$query = str_replace("\r", "", $query);
		$query = str_replace(" ", "", $query);
		$query = mb_strtolower($query);

		if (strpos($query, "update") === 0) return false;
		if (strstr($query, ";update")) return false;

		if (strpos($query, "insert") === 0) return false;
		if (strstr($query, ";insert")) return false;

		if (strpos($query, "delete") === 0) return false;
		if (strstr($query, ";delete")) return false;

		if (strpos($query, "create") === 0) return false;
		if (strstr($query, ";create")) return false;

		return true;
	}

	public function _getNumRows(){
		if ($this->_currCursor){
			return $this->_currCursor->num_rows;
		}
		return false;
	}

	public function fetch($cacheResults = false, $preventDataParse = false) {
		if (is_null($this->_select)) throw new Exception("Empty query");
		if (!$this->_currCursor){
			throw new Exception($this->_getSqlError());
		}

		if (!$cacheResults) { // no guardo los resultados en la instancia. Para consultas muy pesadas. Default enabled
			if ($data = $this->_currCursor->fetch_assoc()){
				if (!$preventDataParse) $data = $this->fetchAssocConvertMultidata($data);
				return $this->getCore()->getModel($this)->load($data);
			} else {
				return false;
			}
		}

		if (is_null($this->_currCursor) && empty($this->_items[$this->_itemIdx])){
			$this->resetFetch();
			return false;
		}
		if (!($data = $this->_currCursor->fetch_assoc()) && empty($this->_items[$this->_itemIdx])){
			$this->resetFetch();
			return false;
		}

		if (!isset($this->_items[$this->_itemIdx])){
			if (!$preventDataParse) $data = $this->fetchAssocConvertMultidata($data);
			$this->_items[$this->_itemIdx] = $this->getCore()->getModel($this)->load($data);
		}

		return $this->_items[$this->_itemIdx++];
	}

	public function resetFetch(){
		$this->_itemIdx = 0;
		$this->_currCursor->data_seek(0);
	}

	public function save(){
		$protectionModel = $this->_getProtection();
		$protectionModel->verify();

		if (!$this->_describe){
			$this->_describe = $this->getDescribe();
		}

		if ($this->executeManageTable()) {
			$this->_describe = $this->getDescribe();
		}


		$arrFields = [];
		$arrFieldTypes = [];
		foreach ($this->_describe as $fieldName => $fieldType) {
			$arrFields[] = $fieldName;
			$arrFieldTypes[] = $fieldType;
			
		}
		if (($idIdx = array_search($this->getPrimaryIdx(), $arrFields)) !== false){
			unset($arrFields[$idIdx-1]);
			unset($arrFieldTypes[$idIdx-1]);
			$arrFields = array_values($arrFields);
			$arrFieldTypes = array_values($arrFieldTypes);
		}

		if ($this->getID()){
			$sql = "UPDATE ".$this->getTableName()." set ";
			foreach ($arrFields as $k => $currField) {
				if ($currField == $this->getPrimaryIdx()) {
					$currValue = $this->getID();
				} else {				
					$getter = $this->getGetter($currField);
					$currValue = $this->$getter();
				}
				if ($arrFieldTypes[$k] == "json"){
					$currValue = json_encode($currValue);
				}
				if (substr($arrFieldTypes[$k], 0, 3) == "set"){
					$currValue = implode(",", (array)$currValue);
				}
				if (is_null($currValue)){
					$sql .= "`".$currField."`"."= NULL ,";
				} else {
					$sql .= "`".$currField."`"."='".$this->_getSql()->real_escape_string($currValue)."',";
				}
			}
			$sql = trim($sql, ",");

			$sql .= " where ". $this->getPrimaryIdx()."=".(int)$this->getID().";";

			if (!$this->_query($sql)){
				error_log($sql);
				throw new Exception($this->_getSqlError());
			}
		} else {
			$sql = "INSERT into ".$this->getTableName();

			$arrValues = [];
			$arrFinalFields = [];

			foreach ($arrFields as $k => $currField) {
				$getter = $this->getGetter($currField);
				$currValue = $this->$getter();
				if ($arrFieldTypes[$k] == "json"){
					$currValue = json_encode($currValue);
				}
				if (substr($arrFieldTypes[$k], 0, 3) == "set"){
					$currValue = implode(",", (array)$currValue);
				}
				if (!is_null($currValue)){
					$arrFinalFields[] = "`".$currField."`";
					$arrValues[] = "'".$this->_getSql()->real_escape_string($currValue)."'";
				} 
			}
			$sql .= " (".implode(",", $arrFinalFields).") values (".implode(",", $arrValues).")";
			if (!$this->_query($sql)){
				throw new Exception($this->_getSqlError());
			}
			$this->setID($this->_getSql()->insert_id);
		}
		return $this;

	}

	public function delete(){
		$protectionModel = $this->_getProtection();
		$protectionModel->verify();
		
		$sql = "DELETE from ".$this->getTableName()." where ".$this->getPrimaryIdx()." = ".(int)$this->getID();
		if (!$this->_query($sql)){
			throw new Exception($this->_getSqlError());
		}
		$this->setID(false);

		return $this;
	}

	public function deleteWhere($whereStr){
		$protectionModel = $this->_getProtection();
		$protectionModel->verify();
		
		$sql = "DELETE from ".$this->getTableName()." where ".$whereStr;
		if (!$this->_query($sql)){
			throw new Exception($this->_getSqlError());
		}

		return $this;
	}


	private function executeManageTable(){// create table - modify fields - delete fields
		if (!method_exists($this, "_manageTable")) return false;

		$tableHandle = $this->getCore()->getModel("core.db.tablehandle");

		$tableHandle->setOriginTableName($this->getTableName());
		$tableHandle->setOriginPrimaryIdx($this->getPrimaryIdx());

		if ($this->_describe) {
			$tableHandle->setTableCreated(true);
			foreach ($this->_describe as $field => $type) {
				$tableHandle->addCreatedField($field);
			}

			$tableInfo = $this->_getTableInfo();
		} else {
			$tableInfo = false;
		}


		return $this->_manageTable($tableInfo, $tableHandle);
	}


}