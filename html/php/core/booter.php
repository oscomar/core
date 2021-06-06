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


class core_booter extends core_mainobject{
	
	public function bootRenderPlatform(){
		$protection = $this->_getProtection();
		$protection->callOnlyFrom([
			"whitelist" => ["core::boot"]
		]);

		$requestUri = $_SERVER["REQUEST_URI"];


		$postData = [];
		$postData["inBootTime"] = true;
		$postData["fullPath"] = $requestUri;

		if (isset($_GET["raw"])) {
			$postData = json_decode(base64_decode($_GET["data"]), true);
		} else {
			if (!isset($postData["data"])){
				$postData["data"] = [];
			} else {
				$postData["data"] = json_decode($postData["data"], true);
			}
		}

		$prePath = explode("?", $requestUri);

		$path = $this->getCore()->clearPath($prePath[0]);
		$postData["path"] = $path;

		if (isset($prePath[1])){
			parse_str($prePath[1], $postData["getData"]);
		}


		$currController = $this->getCore()->getModel("core.controller")->load($path, "path");
		if (is_string($currController) && $currController == "__RELOAD__"){
			header("Refresh:0");
			$this->getCore()->end();
		}

		if (empty($currController->getID())){ // 404
			http_response_code(404);

			$currController = $this->getCore()->getModel("core.controller")->load("[404]", "path");
			if (empty($currController->getID())) $this->getCore()->end();
		}


		echo $this->processResponseData($currController->getResponseData($postData));
		return true;


	}


	public function processResponseData($arrResponseData){
		$mainobjectAsCmp = $this->getCore()->getModel("core.component");
		$mainobjectAsCmp->setComponentName("core_mainobject");

		$finalHtmlDocument = $mainobjectAsCmp->getComponentFile("html");
		$finalHtmlDocument = str_replace("{{MAINDIR}}", $this->getCore()->getMainDir(), $finalHtmlDocument);
		$finalHtmlDocument = str_replace("{{MD5CSS}}", md5_file($this->getCore()->getRootFolder()."/css/core.css"), $finalHtmlDocument);
		$finalHtmlDocument = str_replace("{{MD5JS}}", md5_file($this->getCore()->getRootFolder()."/js/core.js"), $finalHtmlDocument);

		$finalDomDocument = new DOMDocument('1.0', 'UTF-8');
		@$finalDomDocument->loadHTML("<?xml encoding='utf-8' ?>".$finalHtmlDocument);

		$domQuerySelector = $this->getCore()->getModel("core.booter.domselector");
		$domQuerySelector->setDomDocument($finalDomDocument);

		$arrBootData = [];
		foreach ($arrResponseData as $componentName => $componentData) {
			if ($componentName == "") {
				http_response_code(404);
				$this->getCore()->end();
			}
			$alreadyOne = $this->setComponentData($finalDomDocument, $domQuerySelector, $componentName, $componentData);

			$arrBootData[$componentName] = [
				"onlyOne" => $componentData["onlyOne"],
				"alreadyOne" => $alreadyOne,
				"designPath" => $componentData["designPath"],
				"data" => $componentData["data"]
			];
		}

		$headNode = $domQuerySelector->select("head");

		$bootDataTag = $finalDomDocument->createElement("script");
		$bootDataTag->appendChild($finalDomDocument->createCDATASection(json_encode($arrBootData)));
		$bootDataTag->setAttribute("type", "application/json");
		$bootDataTag->setAttribute("class", "boot-data-tag");
		$headNode->appendChild($bootDataTag);

		$finalHtmlDocument = html_entity_decode($finalDomDocument->saveHTML());

		$seoTagsHtml = $this->getCore()->getModel("core.seotags")->getProcessedTags();
		$finalHtmlDocument = str_replace("<ogheaddata></ogheaddata>", $seoTagsHtml, $finalHtmlDocument);
		$finalHtmlDocument = str_replace("<?xml encoding='utf-8' ?>", "", $finalHtmlDocument);


		return $finalHtmlDocument;
	}

	private function setComponentData($DOMDocument, $domQuerySelector, $componentName, $componentData){
		/*html, js, css, designPath, clearPath, onlyOne, data*/


		$dataOnlyOneAdd = false;
		if ($componentData["js"] != "") {
			//$componentData["js"] .= "\n\nObject.freeze(".$componentName.");";

			$newScript = $DOMDocument->createElement("script");


			$newScript->appendChild($DOMDocument->createCDATASection($componentData["js"]));

			$newScript->setAttribute("data-component-name", $componentName);
			if ($componentData["html"] != "") $newScript->setAttribute("data-has-html", "true");
			$ifPrevInclude = $domQuerySelector->select("head script[data-component-name='".$componentName."']");
			if ($ifPrevInclude && !$componentData["onlyOne"]) $ifPrevInclude->parentNode->removeChild($ifPrevInclude);

			if (!$componentData["onlyOne"] || !$ifPrevInclude) {
				$domQuerySelector->select("head")->appendChild($newScript);
			}

			if ($componentData["onlyOne"] && !$dataOnlyOneAdd) {
				$newScript->setAttribute("data-only-one", "true");
				$dataOnlyOneAdd = true;
			}

		}


		if ($componentData["css"] != "") {
			$newStyle = $DOMDocument->createElement("style");
			$newStyle->setAttribute("data-component-name", $componentName);

			$newStyle->appendChild($DOMDocument->createTextNode($componentData["css"]));


			$fPrevInclude = $domQuerySelector->select("head style[data-component-name='".$componentName."']");
			if ($ifPrevInclude && !$componentData["onlyOne"]) $ifPrevInclude->parentNode->removeChild($ifPrevInclude);
			if (!$componentData["onlyOne"] || !$ifPrevInclude) $domQuerySelector->select("head")->appendChild($newStyle);

			if ($componentData["onlyOne"] && !$dataOnlyOneAdd) {
				$newStyle->setAttribute("data-only-one", "true");
				$dataOnlyOneAdd = true;
			}
		}

		$alreadyOne = false;

		if ($componentData["html"] != "") {
			if (!empty($componentData["clearPath"])) {
				$arrClearComponents = $domQuerySelector->select($componentData["clearPath"]." > *");
				if (is_array($arrClearComponents)) {				
					for($i = 0 ; $i < count($arrClearComponents) ; $i++){
						$currChild = $arrClearComponents[$i];
						$currChild->parentNode->removeChild($currChild);
					}
				} else if ($arrClearComponents) {
					$arrClearComponents->parentNode->removeChild($arrClearComponents);
				}
			}

			$parentComponent = $domQuerySelector->select($componentData["designPath"]);
			if (is_array($parentComponent)) $parentComponent = $parentComponent[count($parentComponent)-1]; //:last-child fails in selectors wth #IDs

			if ($parentComponent->isSameNode($domQuerySelector->select("head"))) {
				throw new Exception($componentName." - Design path cannot be <head>");
			}
			
			if ($componentData["onlyOne"] && $domQuerySelector->select("cmp.component.".$componentName, $parentComponent)) {
				$alreadyOne = true;
			}

			if ($componentData["html"] != ""){
				if (!$componentData["onlyOne"] ||  !$domQuerySelector->select("cmp.component.".$componentName, $parentComponent)){
					$newComponent = $DOMDocument->createElement("cmp");
					$newComponent->setAttribute("class", "component ".$componentName);
					$newComponent->setAttribute("data-component-name", $componentName);

					if ($componentData["onlyOne"] && !$dataOnlyOneAdd) {
						$newComponent->setAttribute("data-only-one", "true");
						$dataOnlyOneAdd = true;
					}


					$tmpDomDoc = new DOMDocument('1.0', 'UTF-8');
					@$tmpDomDoc->loadHTML("<?xml encoding='utf-8' ?>"."<tmpd>".$componentData["html"]."</tmpd>");
					$arrTmpdChildnodes = $tmpDomDoc->getElementsByTagName("tmpd")->item(0)->childNodes;
					for ($i = 0 ; $i < $arrTmpdChildnodes->length ; $i++) {
						$currTmpChildnode = $arrTmpdChildnodes->item($i);
						$currTmpChildnode = $DOMDocument->importNode($currTmpChildnode, true);
						$newComponent->appendChild($currTmpChildnode);
					}

					$parentComponent->appendChild($newComponent);
				}
			}
		}

		return $alreadyOne;

	}
}