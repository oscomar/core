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
final class Core {
	const arrBaseVersion = [0, 1, 0];
	const arrVersion = [0, 1, 1];

	private $mainObject;
	private $pageTitle;
	private $startTime;
	private $lockSite = false;
	private static $DIR = __DIR__;

	private static $arrJsonConfig = false;

	private static $started = false;

	//private static $checkTime = 0;

	function __construct(){
		spl_autoload_register(function($className){
			$arrClassName = explode("_", $className);
			$modelFile = $this->getModelFile($arrClassName, true);
			//$checkTime = microtime(true);
			$this->checkOnlyCode($modelFile);
			//self::$checkTime += microtime(true)-$checkTime;
			//error_log(self::$checkTime);
			require_once $modelFile;
		});

		$this->start();
	}

	public function loadMainobject(){
		$this->mainObject = $this->getModel("core.mainobject");
	}


	public function start(){
		if (self::$started) return $this;
		self::$started = true;
		$this->loadMainobject();
		$this->checkEnvSecurity();
		$this->readJsonConfig();

		$this->startTime = microtime(true);
		if ($this->getLockSite()){
			die("Sitio en mantenimiento");
		}

		return $this;
	}

	public function checkEnvSecurity(){
		if ($_ENV || $_SERVER) {
			$phpIniFile = php_ini_loaded_file();
			error_log("Please disable \$_ENV and \$_SERVER env vars! in ".$phpIniFile." modify 'variables_order' to 'GPC'");
			exit();
		}
		if (is_readable(self::$DIR.'/../config.json')) {
			error_log("Unsafe config.json! please set unreadable by apache user");
			exit();
		}

		if (is_readable(self::$DIR.'/../apache-config/')) {
			$arrScandirApacheConfigs = scandir(self::$DIR.'/../apache-config/');
			$arrApacheConfigs = [];
			foreach ($arrScandirApacheConfigs as $k => $currDirFile) {
				if (strpos(strtolower($currDirFile), ".conf") !== false) $arrApacheConfigs[] = self::$DIR.'/../apache-config/'.$currDirFile;
			}

			foreach ($arrApacheConfigs as $k => $currApacheConfig) {
				$currApacheConfig = realpath($currApacheConfig);
				if (is_readable($currApacheConfig)) {
					error_log("Unsafe Apache config: ".$currApacheConfig." please set unreadable by apache user");
					exit();
				}
			}
		}

		if (is_dir(self::$DIR.'/../.git/')) {
			error_log("Please start gitter!");
			exit();
		}

		return;
	}

	public function readJsonConfig(){
		$strConfigJson = getenv("config_json");
		if (empty($strConfigJson)) {
			error_log("Empty CONFIG_JSON in Apache config. Please compile config and restart Apache.");
			exit();
		}

		self::$arrJsonConfig = json_decode($strConfigJson, true);

		if (empty(self::$arrJsonConfig)) {
			error_log("Invalid CONFIG_JSON (json format) in Apache config. Please compile config and restart Apache.");
			exit();
		}
		
		if (function_exists("apache_setenv")) apache_setenv("config_json", "");
		putenv("config_json=");

		return;
	}

	public function getExecTime(){
		return microtime(true) - $this->startTime;
	}

	public function getModel($modelStr){
		if (is_object($modelStr)){
			$modelStr = get_class($modelStr);
			$modelStr = $this->getModelDescriptorByClassName($modelStr);
		}
		$arrModel = explode(".", $modelStr);
		
		if (!is_file($this->getModelFile($arrModel))){
			$replaceBackslashMsg = " (replace underscores with dots)";
			throw new Exception("Model ".$modelStr." not found".((strstr($modelStr, "_"))?$replaceBackslashMsg:""), 1);
		}

		$modelName = $this->getModelStr($arrModel);
		$model = new $modelName();

		$newCore = new $this;
		$newCore->cloneConfig(self::$arrJsonConfig);
		$model->setCore($newCore);

		if (isset(self::$arrJsonConfig[$modelStr])) $model->_putJsonConfig(self::$arrJsonConfig[$modelStr]);

		if (method_exists($model, "_construct")) 
			$model->_construct();


		return $model;
	}

	public function cloneConfig($arrJsonConfig){
		if (self::$arrJsonConfig) return false;
		self::$arrJsonConfig = $arrJsonConfig;
	}


	public function getModelDescriptorByClassName($className){
		$arrModel = explode("_", $className);
		$arrModel = array_values($arrModel);
		return implode(".", $arrModel);
	}

	public function getModelFile($arrModel){
		$strFileName = self::$DIR."/php";

		$k = 0;
		foreach ($arrModel as $currModelPart) {
			$strFileName .= "/".$currModelPart;
			$k++;
		}
		$strFileName .= ".php";

		return $strFileName;
	}

	public function getModelStr($arrModel){
		$strFileName = "";

		foreach ($arrModel as $k => $currModelPart) {
			$strFileName .= "_".$currModelPart;
		}
		$strFileName = trim($strFileName, "_");
		return $strFileName;
	}


	public function includeExternal($path){
		require_once self::$DIR."/php/".$path;
	}

	public function getResource($path, $validateFile = true){
		$arrModel = explode("/", $path);
		if ($validateFile && !is_file($this->getResourceFile($arrModel))){
			throw new Exception("Resource ".$path." not found", 2);
		}
		return $this->getResourceFile($arrModel);
	}

	public function getResourceFile($arrModel){
		$strFileName = self::$DIR."/resources";

		$k = 0;
		foreach ($arrModel as $currModelPart) {
			$strFileName .= "/".$currModelPart;
			$k++;
		}

		return $strFileName;
	}

	public function getMainDir(){
		return "";
	}

	public function getRootFolder(){
		return self::$DIR;
	}

	public function getUrl($path, $getData = []){
		if ($path[0] != "/"){
			$path = "/".$path;
		}

		$arrJsonConfig = $this->getJsonConfig("url");
		$url = $arrJsonConfig["protocol"]."://".$arrJsonConfig["hostname"].$path;

		if (!empty($getData)){
			$url .= "?".http_build_query($getData);
		}
		return $url;
	}

	public function getPageTitle(){
		return $this->pageTitle;
	}
	public function setPageTitle($p){
		return $this->pageTitle = $p;
	}


	public function getAllJsonConfigForClibridge(){
		$protection = call_user_func(function(){
			$p = $this->getModel("core.protection.model");
			$p->setModelToProtect(__CLASS__);
			$p->setBacktraceToGetProtection(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
			return $p;
		});

		$protection->callOnlyFrom([
			"whitelist" => ["core_clibridge::execute"]
		]);

		return self::$arrJsonConfig;

	}


	public $arrPids = [];
	public $arrChildResponse = [];
	public function fork($childCall, $thisObject, ...$extraData){
		$p = pcntl_fork();
		if ($p == -1){
			throw new Exception("Fork error", 8);
		} else if ($p === 0){
			cli_set_process_title("PHP pwafw-fork: ".$this->getModelDescriptorByClassName(get_class($thisObject)));
			if (is_object($thisObject) && $thisObject instanceof core_db_model){
				$thisObject->_resetSqlByFork();
			}
			
			$returnData = call_user_func_array($childCall, array_merge([$thisObject], $extraData));

			$this->end();
		} else {
			return $p;
		}
		return false;
	}

	public function forkWait($threads, $currPid){
		if (count($this->arrPids) < $threads){
			$this->arrPids[] = $currPid;
			return false;
		} else {
			foreach ($this->arrPids as $currStoredPid) {
				pcntl_waitpid($currStoredPid, $returnStatus);
				if (pcntl_wifexited($returnStatus)){
					$shm = shmop_open(ftok(__FILE__, 't')+(int)$currStoredPid, "a", 0, 0);

					if ($shm){
						$this->arrChildResponse[$currStoredPid] = json_decode(shmop_read($shm, 0, 0), true);
						shmop_delete($shm);
					} else {
						$this->arrChildResponse[$currStoredPid] = NULL;
					}

				} else {
					$this->arrChildResponse[$currStoredPid] = NULL;
				}
			}
			$this->arrPids = [];
			return $this->arrChildResponse;
		}
	}

	public function setLockSite($a){
		$this->lockSite = $a;
	}
	public function getLockSite(){
		return $this->lockSite;
	}

	public function getRandomId($arrToCompareUnique = [], $fromIndexes = false, $length = 5){
		$arrRnd = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		if ($fromIndexes) $arrToCompareUnique = array_keys($arrToCompareUnique);
		
		$newRnd = "";
		do {
			for ($i = 0 ; $i <= $length ; $i++) $newRnd .= $arrRnd[random_int(0, strlen($arrRnd) -1)];
		} while (in_array($newRnd, $arrToCompareUnique));

		return $newRnd;
	}

	public function arrVersionToInt($arrVersion){ //[0,15,188] -> 000100150188
		return doubleval(sprintf("%04d", $arrVersion[0]).sprintf("%04d", $arrVersion[1]).sprintf("%04d", $arrVersion[2]));
	}


	public function printOgHeadData(){
		$ogHeadFile = self::$DIR."/html/core/oghead.html";
		if (!file_exists($ogHeadFile)) return false;
		echo file_get_contents($ogHeadFile)."\n";
	}



	public function checkOnlyCode($modelFile){
		//md5_file($modelFile);
		//error_log($modelFile);
		return true;
		$strValidatorStruct = [
			"str" => "",
			"strlen" => 0,
			"passBytes" => 0,
			"passed" => false,
			"cmpLine" => 0
		];

		$_getCmpStruct = function ($str) use ($strValidatorStruct) {
			//if ($str === "") return false;

			$ret = $strValidatorStruct;
			$ret["str"] = $str;
			$ret["strlen"] = strlen($str);
			return $ret;
		};

		$_initStructs = function ($arrStrToValidate) use ($strValidatorStruct, $_getCmpStruct) {
			$arrStructs = [];
			foreach ($arrStrToValidate as $currStrToValidate) {
				$arrStructs[$currStrToValidate] = $_getCmpStruct($currStrToValidate);
			}

			return $arrStructs;
		};

		$arrStrToValidate = ["<?php", "<?PHP", "<?=", "?>"];
		$arrStrStructsToValidate = $_initStructs($arrStrToValidate);


		$_resetStr = function ($str) use (&$arrStrStructsToValidate) {
			//if (!isset($arrStrStructsToValidate[$str])) return false;
			$arrStrStructsToValidate[$str]["passBytes"] = 0;
			$arrStrStructsToValidate[$str]["passed"] = false;
			return true;
		};
		$_resetAll = function () use (&$arrStrStructsToValidate, $_resetStr) {
			foreach ($arrStrStructsToValidate as $kStruct => $currStruct) {
				$_resetStr($kStruct);
			}
		};


		$_validateStr = function ($str) use (&$arrStrStructsToValidate, $_resetStr) {
			//if (!isset($arrStrStructsToValidate[$str])) return false;
			$currStruct = $arrStrStructsToValidate[$str];

			$passed = $currStruct["passed"];
			if ($passed) $_resetStr($str);
			return $passed;
		};

		$_compareNewChar = function ($char, $kStruct = false, $line = 0) use (&$arrStrStructsToValidate) {
			if ($kStruct !== false) {
				if ($arrStrStructsToValidate[$kStruct]["passBytes"] < $arrStrStructsToValidate[$kStruct]["strlen"]
				&&  $arrStrStructsToValidate[$kStruct]["str"][ $arrStrStructsToValidate[$kStruct]["passBytes"] ] === $char) {
					$arrStrStructsToValidate[$kStruct]["passBytes"]++;
					if ($line !== 0) $arrStrStructsToValidate[$kStruct]["cmpLine"] = $line;
				}
				else 
					$arrStrStructsToValidate[$kStruct]["passBytes"] = 0;

				if ($arrStrStructsToValidate[$kStruct]["passBytes"] === $arrStrStructsToValidate[$kStruct]["strlen"]) $arrStrStructsToValidate[$kStruct]["passed"] = true;
			} else {
				$arrStructKeys = array_keys($arrStrStructsToValidate);
				for ($i = 0 ; $i < count($arrStructKeys) ; $i++) {
					$kStruct = $arrStructKeys[$i];
					if ($arrStrStructsToValidate[$kStruct]["passBytes"] < $arrStrStructsToValidate[$kStruct]["strlen"]
					&&  $arrStrStructsToValidate[$kStruct]["str"][ $arrStrStructsToValidate[$kStruct]["passBytes"] ] === $char) {
						$arrStrStructsToValidate[$kStruct]["passBytes"]++;
						if ($line !== 0) $arrStrStructsToValidate[$kStruct]["cmpLine"] = $line;
					}
					else 
						$arrStrStructsToValidate[$kStruct]["passBytes"] = 0;

					if ($arrStrStructsToValidate[$kStruct]["passBytes"] === $arrStrStructsToValidate[$kStruct]["strlen"]) $arrStrStructsToValidate[$kStruct]["passed"] = true;
				}
			}
		};

		$lineCnt = 1;

		$_THROW = function ($line, $code, ...$args) use ($modelFile, &$lineCnt, &$file) {
			fclose($file);
			if (!empty($args)) error_log(var_export($args, true));
			$arrCodes = [
				"",
				"The code can only start with php open tag",
				"The PHP open tag must be '<?php' , '<?PHP' , '<?='",
				"Remove the close tag of PHP code"
			];

			throw new Exception("checkOnlyCode ERROR - ".$arrCodes[$code]." - in ".$modelFile.":".$lineCnt);
		};

		$file = fopen($modelFile, "r");
		$charCnt = 0;

		$nextEscaped = false;
		$currEscaped = false;

		$inString = 0;
		while (!feof($file)) {
			$char = fread($file, 1);
			$charCnt++;

			if ($char === "\n") $lineCnt++;


			if ($charCnt === 1 && $char !== "<") $_THROW(__LINE__, 1);

			if ($charCnt <= 5) $_compareNewChar($char);

			if ($charCnt === 5 && $_validateStr("<?php") === false && $_validateStr("<?=") === false && $_validateStr("<?PHP") === false) $_THROW(__LINE__, 2);

			if ($inString === 0 && $char === '"') {
				$inString = 2;
				continue;
			}
			if ($inString === 0 && $char === "'") {
				$inString = 1;
				continue;
			}

			if ($inString !== 0) {
				if ($char === "\\") {
					$currEscaped = false;
					$nextEscaped = !$nextEscaped;
				} else {
					if ($nextEscaped) {
						$currEscaped = true;
						$nextEscaped = false;
					}
				}
			} else {
				$currEscaped = false;
				$nextEscaped = false;
			}


			if ($inString === 1 && $char === "'" && $currEscaped === false) {
				$inString = 0;
			}

			if ($inString === 2 && $char === '"' && $currEscaped === false) {
				$inString = 0;
			}


			if ($charCnt > 5 && $inString === 0) {
				$_compareNewChar($char, "?>", __LINE__);

			}
			//if ($charCnt > 5 && $inString !== 0) $_resetAll();
		}
		if ($_validateStr("?>")) $_THROW($arrStrStructsToValidate["?>"]["extraArgs"][0], 3);

		fclose($file);
		return true;
	}

	public function end(){
		//echo $this->getExecTime();
		$this->getModel("core.db.model")->closeDb();
		exit;
		die();
		return;
	}
}