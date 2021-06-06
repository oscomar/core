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
require_once "core.php";

$arrParentFileAndPid = explode("|", base64_decode($argv[1]));
$parentFile = $arrParentFileAndPid[0];
$parentPid = $arrParentFileAndPid[1];

$shm = shmop_open(ftok($parentFile, 't')+$parentPid, "a", 0, 0);
$arrCliData = json_decode(shmop_read($shm, 0, 0), true);
shmop_delete($shm);

putenv("config_json=".json_encode($arrCliData["json_config"]));
$core = new Core();

$arrStrMethod = explode("::", $arrCliData["strMethod"]);

$cliBridge = $core->getModel("core.clibridge");
$cliBridge->setIsCli(true);
$_COOKIE = $arrCliData["cookies"];

try {
	$arrOutput = $core->getModel( $arrStrMethod[0] )->{ $arrStrMethod[1] }( $arrCliData["arrArgs"] );
} catch (Exception $e) {
	$arrOutput = [
		"__ERROR" => true,
		"error" => $e->getMessage(),
		"errno" => $e->getCode()
	];
}
$arrOutput = json_encode($arrOutput);


$shm = shmop_open(ftok($parentFile, 't')+$parentPid, "c", 0664, mb_strlen($arrOutput, "8bit"));
if ($shm){
	shmop_write($shm, $arrOutput, 0);
	if (function_exists("shmop_close")) shmop_close($shm);
}

exit();