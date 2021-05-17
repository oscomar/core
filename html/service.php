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
$core = new Core();


$postData = $_POST;

if (isset($_GET["raw"])) {
	$postData = json_decode(base64_decode($_GET["data"]), true);
} else {
	if (!isset($postData["data"])){
		$postData["data"] = [];
	} else {
		$postData["data"] = json_decode($postData["data"], true);
	}
}

$prePath = explode("?", $postData["path"]);

$path = clearPath($core, $prePath[0]);

if (isset($prePath[1])){
	parse_str($prePath[1], $postData["getData"]);
}

header("cache-control: private");



$currController = $core->getModel("core.controller")->load($path, "path");
if (is_string($currController) && $currController == "__RELOAD__"){
	echo $currController;
	exit();
}

if (empty($currController->getID())){ // 404
	$currController = $core->getModel("core.controller")->load("[404]", "path");
	if (empty($currController->getID())) {
		http_response_code(404);
		exit();
	}
}


echo json_encode($currController->getResponseData($postData));
$core->end();


function clearPath($core, $path){
	$path = mb_strtolower($path);
	$path = preg_replace('#/+#','/', $path);
	$path = trim($path, "/");
	$arrPath = explode("/", $path);
	foreach ($arrPath as $k => $currPathPart) {
		if ($currPathPart == $core->getMainDir()){
			unset($arrPath[$k]);
		}
	}
	$arrPath = array_values($arrPath);
	$path = "/".implode("/", $arrPath);

	return $path;
}
