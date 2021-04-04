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

class core_clibridge extends core_db_model {
	private static $isInCli;

	public function isInCli(){
		return self::$isInCli??false;	
	}

	public function setIsCli($val){
		self::$isInCli = $val;
	}

	public function execute($strMethod, $arrArgs){
		$phpPath = shell_exec("command -v php");
		$phpPath = trim($phpPath);

		$rootFolder = $this->getCore()->getRootFolder();

		$_core = $this->getCore();
		$jsonInternalDataToSend = json_encode([ "strMethod" => $strMethod, "arrArgs" => $arrArgs, "cookies" => $_COOKIE, "json_config" => $_core->getAllJsonConfigForClibridge() ]);

		$shm = shmop_open(ftok(__FILE__, 't')+getmypid(), "c", 0600, mb_strlen($jsonInternalDataToSend, "8bit"));
		if ($shm) {
			shmop_write($shm, $jsonInternalDataToSend, 0);
			shmop_close($shm);
		}

		shell_exec($phpPath." ".$rootFolder."/cli.php ".base64_encode(__FILE__."|".getmypid())." > /dev/null");
		$shm = shmop_open(ftok(__FILE__, 't')+getmypid(), "a", 0, 0);

		$cliOutput = json_decode(shmop_read($shm, 0, 0), true);
		shmop_delete($shm);

		if (isset($cliOutput["__ERROR"])) {
			throw new Exception($cliOutput["error"], $cliOutput["errno"]);
		}

		return $cliOutput;
	}
}