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

final class core_protection_model extends core_mainobject{
	private static $passwordAttempt = false;

	public function verify(){
		$arrCallInfo = $this->getCallInfo();
		$notAllowedException = new Exception($arrCallInfo["originModel"]."::".$arrCallInfo["originMethod"].". Not allowed", 13);

		if (!$this->checkNeedPassword()) return true;

		if (!self::$passwordAttempt) throw $notAllowedException;

		$passMd5 = $this->_getJsonConfig("system_key_md5");
		$passAttemptMd5 = md5(self::$passwordAttempt);

		if ($passMd5 == $passAttemptMd5) {
			return true;
		} else {
			throw $notAllowedException;
		}

		return $passOk;
	}

	public function setModelToProtect($model){
		if (parent::getModelToProtect()) return false; // protección por primero en fila
		return parent::setModelToProtect($model);
	}

	/*public function setDefinedModelToProtect($model){
		if (parent::getDefinedModelToProtect()) return false;
		return parent::setDefinedModelToProtect($model);
	}*/

	public function putPassword($password){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => [
				"a9os_core_install::installSystem",
				"a9os_user::registerUser",
				"a9os_core_requestsyskey::putCorrectPass"
			]
		]);
		self::$passwordAttempt = $password;
	}

	public function removePassword(){
		self::$passwordAttempt = false;
	}

	private function checkNeedPassword(){
		$arrCallInfo = $this->getCallInfo();


		if ($arrCallInfo["originModel"] == $arrCallInfo["destModel"]) return false;

		if (strpos($arrCallInfo["originModel"]."_", "core_") === 0) {
			return false;
		}

		if (strpos($arrCallInfo["destModel"]."_", $arrCallInfo["originModel"]."_") === 0) {
			return false;
		}

		//error_log($arrCallInfo["destModel"]."|".$arrCallInfo["originModel"]);


		//if origin core no need pass
		//if origin !core
		//if dest is child of origin not need pass
		//if dest is parent of origin need pass
		//if dest is not child nor parent of orign need pass

		return true;
	}


	public function getCallInfo(){
		$rootFolder = $this->getCore()->getRootFolder()."/";

		$arrBacktraceToGetProtection = $this->getBacktraceToGetProtection();

		$arrOutput = [];
		$arrOutput["destModel"] = $this->getModelToProtect();
		$arrOutput["destMethod"] = $arrBacktraceToGetProtection[1]["function"];

		$arrOutput["originMethod"] = $arrBacktraceToGetProtection[2]["function"];

		$originFileModel = $arrBacktraceToGetProtection[1]["file"];
		$originFileModel = substr($originFileModel, strlen($rootFolder));

		if (strpos($originFileModel, "php/") === 0) {
			$originFileModel = substr($originFileModel, strlen("php/"));

			$originFileModel = mb_strtolower($originFileModel);
			$originFileModel = str_replace(".php", "", $originFileModel);
			$originFileModel = str_replace("_", "", $originFileModel);
			$originFileModel = str_replace("/", "_", $originFileModel);
			$arrOutput["originModel"] = $originFileModel;
		} else {
			$arrOutput["originModel"] = $originFileModel;
		}


		return $arrOutput;
	}

	//-------

	public function getDbCredentials(){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => [
				"core_db_model::_getSql"
			]
		]);
		$this->verify();
		
		return $this->_getJsonConfig("db");
	}

	public function getPasswordPrefixSuffix(){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => [
				"a9os_user::registerUser",
				"a9os_user_login::main"
			]
		]);

		$this->verify();
		return $this->_getJsonConfig("password");
	}

	public function getSystemKeyMd5(){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => [
				"a9os_user_login::main"
			]
		]);

		$this->verify();
		return $this->_getJsonConfig("system_key_md5");
	}

	public function requestsyskeyTrypass($passwordTry){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => [
				"a9os_core_requestsyskey::tryPassword"
			]
		]);

		$passMd5 = $this->_getJsonConfig("system_key_md5");
		$passAttemptMd5 = md5($passwordTry);

		return ($passMd5 == $passAttemptMd5);
	}


	public function restrictToChildClasses($definedClassName) {
		$arrCallInfo = $this->getCallInfo();
		$arrClassParents = array_values(class_parents($arrCallInfo["destModel"]));
		
		if (in_array($definedClassName, $arrClassParents)) {
			return true;
		}
		return false;
	}

	public function callOnlyFrom($arrWlBl){
		$arrCallInfo = $this->getCallInfo();

		foreach ($arrWlBl as $wbk => $currWlBl) {
			foreach ($currWlBl as $k => $currItem) {
				$arrModelAndMethod = explode("::", $currItem);
				$arrWlBl[$wbk][$k] = $arrModelAndMethod[0];
				$arrWlBl[$wbk][$k] = mb_strtolower($arrWlBl[$wbk][$k]);
				$arrWlBl[$wbk][$k] = str_replace("_", '\_', $arrWlBl[$wbk][$k]);
				$arrWlBl[$wbk][$k] = str_replace(".", '\.', $arrWlBl[$wbk][$k]);
				$arrWlBl[$wbk][$k] = str_replace("*", '[a-zA-Z0-9]+', $arrWlBl[$wbk][$k]);

				if (isset($arrModelAndMethod[1])) $arrWlBl[$wbk][$k] = $arrWlBl[$wbk][$k]."::".$arrModelAndMethod[1];
			}
		}

		$allowedCall = false;

		if (isset($arrWlBl["whitelist"])) {
			foreach ($arrWlBl["whitelist"] as $k => $currItem) {
				$arrModelAndMethod = explode("::", $currItem);
				if (preg_match("/".$arrModelAndMethod[0]."/", $arrCallInfo["originModel"])) {
					if ((isset($arrModelAndMethod[1]) && $arrModelAndMethod[1] == $arrCallInfo["originMethod"]) || !isset($arrModelAndMethod[1]))
						$allowedCall = true;
				}
			}
		}

		if (isset($arrWlBl["blacklist"])) {
			$inBlackList = false;
			foreach ($arrWlBl["blacklist"] as $k => $currItem) {
				$arrModelAndMethod = explode("::", $currItem);
				if (preg_match("/".$arrModelAndMethod[0]."/", $arrCallInfo["originModel"])) {
					if ((isset($arrModelAndMethod[1]) && $arrModelAndMethod[1] == $arrCallInfo["originMethod"]) || !isset($arrModelAndMethod[1])) {
						$allowedCall = false;
						$inBlackList = true;
					}
				}
			}
			if (!$inBlackList) {
				$allowedCall = true;
			}
		}

		if (!$allowedCall) throw new Exception("Core protection - callOnlyFrom: not allowed - ".var_export($arrCallInfo, true));
		
		return true;
	}
}