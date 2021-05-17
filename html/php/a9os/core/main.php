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

class a9os_core_main extends core_db_model {
	const arrBaseVersion = [0, 1, 0];
	const arrVersion = [0, 1, 6]; //SAVED IN A9OS_USER TABLEINFO

	public function main($data){
		$systemAnonMode = $this->getCore()->getModel("a9os.core.main")->getSystemAnonMode();

		if ($this->checkNeedUpdate()) {
			$a9osUser = $this->getCore()->getModel("a9os.user")->getSessionUser();
			if ($systemAnonMode == "closed" && $a9osUser->getName() == "__anon__") {
				// do not perform update
			} else if ($systemAnonMode == "demo"){
				// do not perform update
			} else {
				$a9osCoreInstall = $this->getCore()->getModel("a9os.core.install");
				$a9osCoreInstall->updateSystem();
			}
		}

		$coreVersion = implode(".", $this->getCore()::arrVersion);
		$deskVersion = implode(".", self::arrVersion);
		return [
			"coreVersion" => $coreVersion,
			"deskVersion" => $deskVersion,
			"systemAnonMode" => $systemAnonMode
		];
	}


	private function checkNeedUpdate(){
		$a9osUserToInstalledVersion = $this->getCore()->getModel("a9os.user");
		$arrInstalledVersion = $a9osUserToInstalledVersion->_getA9osVersion();

		if (!$arrInstalledVersion) return false;
		if ($arrInstalledVersion == self::arrVersion) return false;

		return true;
	}


	public function getAppByPath($path){
		$cc = $this->getCore()->getModel("core.controller");
		$cca = $this->getCore()->getModel("core.controller.application");
		$aa = $this->getCore()->getModel("application.application");

		$aa = $aa->_setSelect("
			SELECT aa.* from {$aa->getTableName()} aa

			left join {$cca->getTableName()} cca
				on (cca.{$aa->getPrimaryIdx()} = aa.{$aa->getPrimaryIdx()})

			left join {$cc->getTableName()} cc
				on (cca.{$cc->getPrimaryIdx()} = cc.{$cc->getPrimaryIdx()})

			where cc.path = {$aa->_quote($path)}
		");

		return $aa->fetch();
	}

	public function getSystemAnonMode(){
		return $this->_getJsonConfig("system_anon_mode");
	}

}
