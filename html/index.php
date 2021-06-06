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
ini_set("display_errors", "0");
ini_set("upload_max_filesize", "10G");
ini_set("post_max_size", "10G");
require_once "core.php";
$core = new Core();




$arrHeaders = [
	"X-Content-Type-Options" => "nosniff",
	"Content-Security-Policy" => "frame-ancestors 'self'",
	"X-Frame-Options" => "SAMEORIGIN",
	"X-XSS-Protection" => "1; mode=block",
	"cache-control" => "private"
];
foreach ($arrHeaders as $k => $h) {
	header($k . ": " . $h);
}


$requestUri = $_SERVER["REQUEST_URI"];
$prePath = explode("?", $requestUri);
$path = $core->clearPath($prePath[0]);



$arrPublicCacheControl = ["ico","jpg","jpeg","png","gif","svg"];
foreach ($arrPublicCacheControl as $currExtension) {
	if (strrpos($path, $currExtension) === 0) {
		header("Cache-Control: max-age=43200, public");
	}
}




if (strpos($path, "/resources") === 0) {
	$originalCasedPath = $core->clearPath($prePath[0], true);
	$file = $core->getRootFolder().$originalCasedPath;
	if (!is_file($file)) {
		http_response_code(404);
		$core->end();
	}
	header("Content-Type: ".mime_content_type($file));
	echo file_get_contents($file);

} elseif (strpos($path, "/robots.txt") === 0) {

	$file = $core->getRootFolder()."/resources/robots.txt";
	header("Content-Type: ".mime_content_type($file));
	echo file_get_contents($file);

} elseif (strpos($path, "/sitemap.xml") === 0) {

	$file = $core->getRootFolder()."/resources/sitemap.xml";
	header("Content-Type: ".mime_content_type($file));
	echo file_get_contents($file);

} elseif (strpos($path, "/servwk.js") === 0) {
	$file = $core->getRootFolder()."/resources/servwk.js";
	header("Content-Type: "."text/javascript");
	echo file_get_contents($file);

} elseif (strpos($path, "/css/core-") === 0) {

	$file = $core->getRootFolder()."/css/core.css";
	header("Content-Type: "."text/css");
	echo file_get_contents($file);

} elseif (strpos($path, "/js/core-") === 0) {

	$file = $core->getRootFolder()."/js/core.js";
	header("Content-Type: "."text/javascript");
	echo file_get_contents($file);

} elseif (strpos($path, "/service") === 0) {

	header("cache-control: private");

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

	$path = $core->clearPath($prePath[0]);

	if (isset($prePath[1])){
		parse_str($prePath[1], $postData["getData"]);
	}

	$currController = $core->getModel("core.controller")->load($path, "path");
	if (is_string($currController) && $currController == "__RELOAD__"){
		echo $currController;
		$core->end();
	}

	if (empty($currController->getID())){ // 404
		$currController = $core->getModel("core.controller")->load("[404]", "path");
		if (empty($currController->getID())) {
			http_response_code(404);
			$core->end();
		}
	}


	echo json_encode($currController->getResponseData($postData));
} else {
	$core->boot();
}



$core->end();