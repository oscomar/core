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
/*FROM https://github.com/tj/php-selector*/

class core_booter_domselector extends core_mainobject{
	private $xpath;

	public function setDomDocument($domDocument){
		$this->xpath = new DOMXpath($domDocument);

		return true;
	}

	public function select($selector, $contextNode = false) {
		if ($contextNode) $elements = $this->xpath->evaluate($this->selector_to_xpath($selector), $contextNode);
		else $elements = $this->xpath->evaluate($this->selector_to_xpath($selector));

		if ($elements->length == 0) return false;
		elseif ($elements->length == 1) return $elements->item(0);
		else {
			$arrNodes = [];
			for ($i = 0 ; $i < $elements->length ; $i++) $arrNodes[] = $elements->item($i);

			return $arrNodes;
		}
	}

	private function selector_to_xpath($selector) {
		// remove spaces around operators
		$selector = preg_replace('/\s*>\s*/', '>', $selector);
		$selector = preg_replace('/\s*~\s*/', '~', $selector);
		$selector = preg_replace('/\s*\+\s*/', '+', $selector);
		$selector = preg_replace('/\s*,\s*/', ',', $selector);
		$selectors = preg_split('/\s+(?![^\[]+\])/', $selector);

		foreach ($selectors as $k => $selector) {
			// ,
			$selector = preg_replace('/,/', '|descendant-or-self::', $selector);
			// input:checked, :disabled, etc.
			$selector = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $selector);
			// input:autocomplete, :autocomplete
			$selector = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $selector);
			// input:button, input:submit, etc.
			$selector = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $selector);
			// foo[id]
			$selector = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\1[@\2]', $selector);
			// [id]
			$selector = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\1]', $selector);
			// foo[id=foo]
			$selector = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\1="\2"]', $selector);
			// [id=foo]
			$selector = preg_replace('/^\[/', '*[', $selector);
			// div#foo
			$selector = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\1[@id="\2"]', $selector);
			// #foo
			$selector = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\1"]', $selector);
			// div.foo
			$selector = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\1[contains(concat(" ",@class," ")," \2 ")]', $selector);
			// .foo
			$selector = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \1 ")]', $selector);
			// div:first-child
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\1[position()=1]', $selector);
			// div:last-child
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\1[position()=last()]', $selector);
			// :first-child
			$selector = str_replace(':first-child', '*/*[position()=1]', $selector);
			// :last-child
			$selector = str_replace(':last-child', '*/*[position()=last()]', $selector);
			// :nth-last-child
			$selector = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\1 - 1))]', $selector);
			// div:nth-child
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\2 and self::\1]', $selector);
			// :nth-child
			$selector = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\1]', $selector);
			// :contains(Foo)
			$selector = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\1[contains(string(.),"\2")]', $selector);
			// >
			$selector = preg_replace('/>/', '/descendant::', $selector); //WARN!
			// ~
			$selector = preg_replace('/~/', '/following-sibling::', $selector);
			// +
			$selector = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\1[position()=1]', $selector);
			$selector = str_replace(']*', ']', $selector);
			$selector = str_replace(']/*', ']', $selector);

			$selectors[$k] = $selector;
		}

		// ' '
		$selector = implode('/descendant::', $selectors);
		$selector = 'descendant-or-self::' . $selector;
		// :scope
		$selector = preg_replace('/(((\|)?descendant-or-self::):scope)/', '.\3', $selector);
		// $element
		$sub_selectors = explode(',', $selector);

		foreach ($sub_selectors as $key => $sub_selector) {
			$parts = explode('$', $sub_selector);
			$sub_selector = array_shift($parts);

			if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
				$results = $matches[0];
				$results[] = str_repeat('/..', count($results) - 2);
				$sub_selector .= implode('', $results);
			}

			$sub_selectors[$key] = $sub_selector;
		}

		$selector = implode(',', $sub_selectors);
		
		return $selector;
	}
}