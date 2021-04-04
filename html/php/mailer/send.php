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

class mailer_send extends core_db_model{
	public function send(){
		mail($this->getEmail(), $this->getSubject(), $this->getTemplate(), $this->getHeaders());
	}

	public function getHeaders(){
		return implode("\r\n", [
			"MIME-Version: 1.0",
			"Content-type: text/html; charset=UTF-8"
		]);
	}

	public function getTemplate(){
		$arrTemplateVars = $this->getTemplateData();
		$template = file_get_contents($this->getCore()->getResource(parent::getTemplate()));
		foreach ($arrTemplateVars as $k => $v) {
			$template = str_replace("{{".$k."}}", $v, $template);
		}
		return $template;
	}
}