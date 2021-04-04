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

class a9os_core_requestsyskey extends a9os_core_window {

	public function getRequestLoadData($model, $function, $postData){
		$errorException = new Exception("not allowed and user cannot request system key", 130);

		$this->clearOldRegisters();

		$a9osUser = $this->getCore()->getModel("a9os.user")->getSessionUser();

		if (!$a9osUser) throw $errorException;
		if (!$a9osUser->getCanRequestSyskey()) throw $errorException;

		$newRequest = $this->getCore()->getModel("a9os.core.requestsyskey");
		$newRequest->setRequestId($this->getCore()->getRandomId());
		$newRequest->setModelName($model);
		$newRequest->setFunctionName($function);
		$newRequest->setPostData(json_encode($postData));
		$newRequest->setDateAdd(date('Y-m-d H:i:s'));
		$newRequest->setUserId($a9osUser->getID());
		$newRequest->setTries(0);
		$newRequest->save();

		return [
			"__REQUESTSYSKEY__" => true,
			"request_id" => $newRequest->getRequestId(),
			"controllerString" => $model."::".$function
		];
		
	}

	public function clearOldRegisters(){
		$requestSyskey = $this->getCore()->getModel("a9os.core.requestsyskey");
		$requestSyskey->deleteWhere("date_add < ".$this->_quote(date("Y-m-d H:i:s", strtotime("-15 minute"))));
	}

	public function main($data){
		return ["window" => [
				"windowColor" => "rgba(40,40,40,0.6)",
				"title" => "Ingresar System Key",
				"favicon-url" => "/resources/a9os/core/requestsyskey/icon.svg",
				"fullscreen" => true
			]
		];
	}

	public function tryPass($data){
		$arrTryData = $data["data"];

		$arrCancelResponse = [
			"status" => "cancel",
			"request_id" => $arrTryData["requestId"]
		];

		$requestObj = $this->getCore()->getModel("a9os.core.requestsyskey")->load($arrTryData["requestId"], "request_id");

		if (!$requestObj) return $arrCancelResponse;

		$a9osUser = $this->getCore()->getModel("a9os.user")->getSessionUser();
		if (!$a9osUser) return $arrCancelResponse;
		if (!$a9osUser->getCanRequestSyskey()) return $arrCancelResponse;

		if ($a9osUser->getID() != $requestObj->getUserId()) {
			$requestObj->delete();

			return $arrCancelResponse;
		}

		if (!$this->tryPassword($arrTryData["passTry"])) {
			if ($requestObj->getTries() + 1 >= 4) {
				$requestObj->delete();
				return $arrCancelResponse;
			}
			else {
				$requestObj->setTries($requestObj->getTries()+1);
				$requestObj->save();

				return [
					"status" => "try_again",
					"request_id" => $arrTryData["requestId"]
				];
			}

		}

		$this->putCorrectPass($arrTryData["passTry"]);

		$methodName = $requestObj->getFunctionName();
		$modelName = $requestObj->getModelName();
		$arrPostData = json_decode($requestObj->getPostData(), true);

		$requestObj->delete();
		$returnModelData = $this->getCore()->getModel($modelName)->$methodName($arrPostData);

		return [
			"status" => "ok",
			"request_id" => $arrTryData["requestId"],
			"response" => $returnModelData
		];

	}

	private function tryPassword($passAttempt){
		$protection = $this->_getProtection();
		return $protection->requestsyskeyTrypass($passAttempt);
	}

	private function putCorrectPass($password){
		$protection = $this->_getProtection();
		$protection->putPassword($password);

		return true;
	}




	protected function _manageTable($tableInfo, $tableHandle) {
		$protectionModel = $this->_getProtection();
		if ($protectionModel->restrictToChildClasses(__CLASS__)) return false;

		if ($tableInfo && $tableInfo["version"] && $tableInfo["version"] == 1) return false;

		if (!$tableInfo) {
			$tableHandle->addField("request_id", "varchar(8)", false, false, false);
			$tableHandle->addField("model_name", "varchar(200)", false, false, false);
			$tableHandle->addField("function_name", "varchar(100)", false, false, false);
			$tableHandle->addField("post_data", "TEXT", false, true, false);
			$tableHandle->addField("date_add", "DATETIME", false, false, false);
			$tableHandle->addField("user_id", "int", false, false, false);
			$tableHandle->addField("tries", "int", false, false, false, "'0'");

			$tableHandle->createIndex("request_id", ["request_id"], "unique");
			$tableInfo = ["version" => 1];
			
			$tableHandle->setTableInfo($tableInfo);
			$tableHandle->save();
		}


		return true;
	}
}