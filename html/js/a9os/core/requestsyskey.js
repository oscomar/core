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
a9os_core_requestsyskey.jss = a9os_core_requestsyskey_jsservice;

a9os_core_requestsyskey.main = (data) => {
	if (a9os_core_main.mainDiv.querySelectorAll("cmp.component.a9os_core_requestsyskey").length > 1) {
		a9os_core_window.close();
		return;
	}

	if (data.window) a9os_core_window.processWindowData(data);


	var controllerStringDiv = self.component.querySelector(".controller-string");
	controllerStringDiv.textContent = self.jss.currRequest.requestData.controllerString;

	var passSpan = self.component.querySelector(".main .password-span");
	var shadowRoot = passSpan.attachShadow({ mode: "closed" });
	shadowRoot.appendChild(self.component.querySelector(".password-input"));

	var passInput = shadowRoot.querySelector(".password-input");
	var badPassLabel = self.component.querySelector(".bad-pass");
	var cancelBtn = self.component.querySelector(".buttons .cancel");
	var submitBtn = self.component.querySelector(".buttons .submit");

	a9os_core_main.addEventListener(cancelBtn, "click", (event, cancelBtn) => {
		cancel("Acción no permitida");
	});


	a9os_core_main.addEventListener(submitBtn, "click", submitTry);

	a9os_core_main.addEventListener(passInput, "keyup", (event, passInput) => {
		if (passInput.value.length == 0) submitBtn.disabled = true;
		else submitBtn.disabled = false;

		badPassLabel.classList.remove("show");

		if (event.which == 13) {
			submitTry();
		}
	});



	function submitTry() {
		//check if correct and close cancel if max tries
		submitBtn.disabled = true;
		core.sendRequest(
			"/requestsyskey/try",
			{
				requestId : self.jss.currRequest.requestData.request_id,
				passTry : passInput.value
			},
			{
				fn : (response) => {
					if (!self.jss.currRequest || !self.jss.currRequest.requestData.request_id || response.request_id != self.jss.currRequest.requestData.request_id) {
						cancel("Acción cancelada");
						return;
					}

					if (response.status == "cancel") {
						cancel("Acción cancelada");
						return;
					}

					if (response.status == "try_again") {
						badPassLabel.classList.add("show");
						passInput.value = "";

						return;
					}

					if (response.status == "ok") {
						a9os_core_window.close();
						if (self.jss.currRequest.successFinalCallback) {
							core.callCallback(self.jss.currRequest.successFinalCallback, {
								response : response.response
							});

							if (self.jss.currRequest.successFinalCallback.args.component) {
								a9os_core_main.selectWindow(self.jss.currRequest.successFinalCallback.args.component.goToParentClass("window", "cmp"));
							}
						}
					}
				},
				args : {
					response : false
				}
			}
		);
	}


	function cancel(msg) {
		a9os_core_taskbar_popuparea.new(msg, "/resources/a9os/core/requestsyskey/icon.svg", "warn");
		a9os_core_window.close();
		if (self.jss.currRequest
		&& self.jss.currRequest.successFinalCallback
		&& self.jss.currRequest.successFinalCallback.args.component) {
			a9os_core_main.selectWindow(self.jss.currRequest.successFinalCallback.args.component.goToParentClass("window", "cmp"));
		}

		if (self.jss.currRequest
		&& self.jss.currRequest.canceledErrorCallback) {
			core.callCallback(self.jss.currRequest.canceledErrorCallback, {
				status : "requestsyskey_not_allowed"
			});
		}

	}
}

//onsubmit sendxhr controller try
// status, request_id, finalReturnData
//if status success, jsservice, if == requestid, execute finallcallback w/finalReturnData
