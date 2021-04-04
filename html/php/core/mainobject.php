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


class core_mainobject {
	private $data;
	private $originalData;

	private $core = false;

	private $jsonConfig = false;

	private $protectionModel = false;

	public function __construct($k = []){
		if ($k === false){
			return false;
		}
		$this->data = $k;
	}

	public function __call($fnName, $args){
		$keyName = $this->getKeyname($fnName);

		if (strpos($fnName, "set") === 0){
			if (isset($args[1]) && $args[1] == true){ // append to array (ex: JSON type value)
				if (!$this->data[$keyName]){
					$this->data[$keyName] = [];
				}
				$this->data[$keyName] = array_merge($this->data[$keyName], $args[0]);
			} else {
				$this->data[$keyName] = $args[0];
			}
			return $this;
		} else if (strpos($fnName, "get") === 0) {
			if (isset($this->data[$keyName])){
				return $this->data[$keyName];
			} else {
				return null;
			}
		}
	}

	public function getCore(){
		return $this->core;
	}
	public function setCore($core){
		if ($this->core) return false;
		return $this->core = $core;
	}

	public function getKeyname($str){
		$arrUppercaseSeparator = str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZ");
		$keyname = "";

		$arrStr = str_split($str);
		foreach ($arrStr as $k => $currStr) {
			if ($k < 3) continue;

			if (in_array($currStr, $arrUppercaseSeparator)){
				$keyname .= "_";
				$keyname .= strtolower($currStr);
			} else {
				$keyname .= $currStr;
			}
		}
		$keyname = trim($keyname, "_");

		return $keyname;
	}

	public function getCaller($keyName, $type = "get"){
        $arrName = explode("_", $keyName);
        foreach ($arrName as $k => $currPart) {
            if (is_numeric($currPart[0])){
                $currPart = "_".$currPart;
            }
            $arrName[$k] = ucwords($currPart);
        }
        return $type.implode("", $arrName);
	}
	public function getGetter($keyName){
		return $this->getCaller($keyName);
	}
	public function getSetter($keyName){
		return $this->getCaller($keyName, "set");
	}

	public function getData($key = false){
		if ($key != false){
			return $this->data[$key];
		}
		if ($this->data == []){
			return null;
		} else {
			return $this->data;
		}
	}

	public function setData($arr){
		//$arr = array_change_key_case($arr, CASE_LOWER);
		//$this->originalData = $arr;
		$this->data = $arr;
	}
	/*public function _getOriginalData(){
		return $this->originalData;
	}*/

	/*public function dataHasChanges(){
		if($this->data != $this->originalData){
			//print_r(array_diff_assoc($this->data, $this->originalData));
			return true;
		}
		return false;

	}*/


	public function _putJsonConfig($jsonData){
		if ($this->jsonConfig) return false;
		$this->jsonConfig = $jsonData;
		return $this;
	}

	protected function _getJsonConfig($key = false){
		if ($key) {
			return $this->jsonConfig[$key]??null;
		} else {
			return $this->jsonConfig??null;
		}
	}

	public function _getProtection() {
		if (!$this->protectionModel) {
			$this->protectionModel = $this->getCore()->getModel("core.protection.model");
			$this->protectionModel->setModelToProtect(get_class($this));
		}
		$this->protectionModel->setBacktraceToGetProtection(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
		return $this->protectionModel;
	}

	public function _tmpGetMem($str, $debuba = false){
		$size = memory_get_usage();
		$unit = array('b','kb','mb','gb','tb','pb');
		if (($GLOBALS["__TMPmemusagew"]??0) != $size){
			$GLOBALS["__TMPmemusagew"] = $size;
			error_log($str."|".@round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i]);
			if ($debuba){
				error_log(json_encode(debug_backtrace()));
			}
		}
	}
}