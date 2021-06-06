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

class core_seotags extends core_mainobject {
	private static $arrSeoTags = [];

	public function appendSeoTags($arrTags){
		foreach ($arrTags as $kTag => $currTagValue) {
			$kTag = mb_strtolower($kTag);
			self::$arrSeoTags[$kTag] = $currTagValue;
		}
	}

	public function getProcessedTags(){
		$arrTagAttrsByKey = [
			"author" => ["name", "content"],
			"description" => ["name", "content"],
			"copyright" => ["name", "content"],
			"robots" => ["name", "content"],
			"keywords" => ["name", "content"],
			"twitter:card" => ["name", "content"],
			"twitter:site" => ["name", "content"],
			"twitter:creator" => ["name", "content"],
			"og:url" => ["property", "content"],
			"og:title" => ["property", "content"],
			"og:description" => ["property", "content"],
			"og:type" => ["property", "content"],
			"og:image" => ["property", "content"],
		];

		$returnStr = $this->getBaseTitle();
		foreach (self::$arrSeoTags as $kTag => $currTagValue) {
			if (!array_key_exists($kTag, $arrTagAttrsByKey)) continue;

			$returnStr .= "<meta ".$arrTagAttrsByKey[$kTag][0]."=\"".$kTag."\" ".$arrTagAttrsByKey[$kTag][1]."=\"".$currTagValue."\"/>\n";
		}

		return $returnStr;
	}

	public function getBaseTitle(){
		$baseTitle = $this->_getJsonConfig("title");
		if (!$baseTitle) return "";
		return "<title>".$baseTitle."</title>";
	}
}