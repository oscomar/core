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
var core = {};
core.main = (fromPopState) => {
	window.onpopstate = () => { core.main(true) };
	core.appendGoToParentClass();
	core.servWk.main();

	var path = window.location.pathname;
	var pathData = core.parseQueryString(window.location.search);
	
	//A9OS mod

	var maximizedInitialWindow = false;
	if (fromPopState) maximizedInitialWindow = true;
	////



	if (document.querySelector("head .boot-data-tag")) {
		core.initFromBootData();
		core.scopeComponentByUrl(path+window.location.search+window.location.hash);

		if (maximizedInitialWindow) {
			var wind0w = document.querySelector(".window.top-window");
			if (window.a9os_core_window && wind0w && !wind0w.classList.contains("no-resize")) 
				a9os_core_window.maxmizeRestore(false, wind0w);
		}

	} else {
		var arrActuator = document.querySelectorAll("cmp.actuator-part");
		var actuatorUrl = false;

		for (var i = arrActuator.length - 1; i >= 0; i--) {
			if (arrActuator[i].classList.contains("with-own-url")) {
				actuatorUrl = arrActuator[i].getAttribute("data-url");
				break;
			}
		}

		core.sendRequest(path, pathData, {
			fn : (response, maximizedInitialWindow, currentUrl, arrActuator) => {
				if (arrActuator) core.changeComponentScopesByElement(arrActuator[arrActuator.length-1], true);

				core.rx(response, currentUrl);

				if (maximizedInitialWindow) {
					var wind0w = document.querySelector(".window.top-window");
					if (window.a9os_core_window && wind0w && !wind0w.classList.contains("no-resize")) 
						a9os_core_window.maxmizeRestore(false, wind0w);
				}
			},
			args : {
				response : false,
				maximizedInitialWindow : maximizedInitialWindow,
				currentUrl : path+window.location.search+window.location.hash,
				arrActuator : arrActuator
			}
		}, false, false, false, false, actuatorUrl);
	}


	//setTimeout(core.servWk.showNotification, 3000);

	
	/*var originalConsoleLog = console.log;
	console.log = (...args) => {
		document.querySelector("#tmpdebug").textContent = JSON.stringify(args);
		originalConsoleLog.apply(false, args);
	}*/
}

core.appendGoToParentClass = () => {
	if (!HTMLElement.prototype.goToParentClass){		
		HTMLElement.prototype.goToParentClass = function (className, tagName){
			if (this.tagName.toLowerCase() == "body") return false;
			var returnElement = this.parentElement;
			if (!returnElement) return false;
			if (returnElement.classList.contains(className) || (tagName && tagName.toLowerCase() == returnElement.tagName && returnElement.classList.contains(className))){
				return returnElement;
			} else {
				return returnElement.goToParentClass(className, tagName);
			}
		};
	}
}












core.parseQueryString = (str) => {
	str = str.substring(1, str.length);
	var arrKeyValue = str.split("&");
	var arrData = {};
	for (var i = 0 ; i < arrKeyValue.length ; i++){
		var tmpKV = arrKeyValue[i].split("=");
		arrData[tmpKV[0]] = tmpKV[1];
	}
	return arrData;
}

core.getDirectRequestUrl = (path, data) => {
	var formData = {};
	formData.path = path;
	formData.data = data;
	return MAIN_DIR+"/service?raw&data="+btoa(JSON.stringify(formData));
}

core.sendRequest = (path, data, cb, ifBlobResponse, preventLoading, cbErr, cbUplLoading, actuatorPath) => {
	if (!preventLoading) core.loading.set();
	var formData = new FormData();
	formData.append("path", path);
	formData.append("fullPath", window.location.pathname+window.location.search+window.location.hash);
	if (actuatorPath) formData.append("actuatorPath", actuatorPath);
	formData.append("onlyonePreventComponents", JSON.stringify(core.getOnlyonePreventComponents()));

	if (data instanceof Blob) {
		formData.append("data", data);
	} else {
		formData.append("data", JSON.stringify(data));
	}


	core.sendXhr(
		MAIN_DIR+"/service",
		formData,
		{
			fn : (response, preventLoading, cb, cbErr, path) => {
				if (!preventLoading) core.loading.unset();
				document.body.classList.add("loaded");
				if (ifBlobResponse) return core.callCallback(cb, { response : response.response });

				try {
					var arrResp = JSON.parse(response.responseText);
				} catch (e) {
					if (e instanceof SyntaxError && window.a9os_core_taskbar_popuparea) {
						a9os_core_taskbar_popuparea.new("Server error: response SyntaxError on "+path, false, "error");
					}
				}
				if (arrResp[""]) { // empty component_name
					arrResp = arrResp[""].data;
				}

				if (arrResp && typeof arrResp == "object" && "__REQUESTSYSKEY__" in arrResp && window.a9os_core_requestsyskey_jsservice) {
					a9os_core_requestsyskey_jsservice.newRequest(arrResp, cb, cbErr);
					return false;
				}
				if (arrResp && arrResp == "__DEMO__") {
					console.log("DEMO MODE");
					if (window.a9os_core_taskbar_popuparea) a9os_core_taskbar_popuparea.showDemoPopup();
					core.callCallback(cbErr, { response : arrResp });
					return false;
				}

				return core.callCallback(cb, { response : arrResp });
			},
			args : {
				response : false,
				preventLoading : preventLoading,
				cb : cb,
				cbErr : cbErr,
				path : path
			}
		},
		{
			fn : (status) => {
				core.loading.unset();
				document.body.classList.add("loaded");
				return core.callCallback(cbErr, { status : status });
			},
			args : {
				status : false
			}
		},
		ifBlobResponse,
		cbUplLoading
	);
}


core.sendXhr = (url, formData, cb, cbErr, ifBlobResponse, cbUplLoading) => {
	var x = new XMLHttpRequest();
	x.open("POST", url, true);
	//x.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	if (ifBlobResponse) x.responseType = "blob";

	if (cbUplLoading) {	
		x.upload.onprogress = (event) => {
			core.callCallback(cbUplLoading, {
				event : event
			});
		}
	}

	x.onreadystatechange = ((x, cb, cbErr, url) => {
		return () => {
			if (x.readyState == 4 && x.status == 200 && cb) {
				if (x.responseType != "blob" && x.responseText && x.responseText == "__RELOAD__") location.reload();
				
				core.callCallback(cb, {
					response : x
				});
			}
			if (cbErr && x.readyState == 4 && x.status != 200){
				if (window.a9os_core_taskbar_popuparea) a9os_core_taskbar_popuparea.new("Server error: "+x.status+" on "+url, false, "error");
				core.callCallback(cbErr, {
					status : x.status
				});
			}
		}
	})(x, cb, cbErr, formData.get&&formData.get("path"));
	x.send(formData);
}



core.getOnlyonePreventComponents = () => {// agregar los que no usan html
	var arrOnlyOneLoadedCmps = document.querySelectorAll("*[data-only-one]");
	var arrComponentNames = [];

	for (var i = 0 ; i < arrOnlyOneLoadedCmps.length ; i++){
		arrComponentNames.push(arrOnlyOneLoadedCmps[i].getAttribute("data-component-name"));
	}

	return arrComponentNames;
}








core.rx = (response, currentUrl) => {
	var arrCompCallbacks = [];
	var arrCompCallbacksWithRequestSyskey = [];

	for (var currComponentName in response) {
		var currComponent = response[currComponentName];
		arrCompCallbacks.push(core.setComponentData(currComponentName, currComponent, currentUrl));
	}

	for (var i = 0 ; i < arrCompCallbacks.length ; i++) {
		var currComponentCb = arrCompCallbacks[i];

		if (currComponentCb.requestSyskey) { //REQUESTSYSKEY from coremain!!
			setTimeout((currComponentCb) => {
				a9os_core_requestsyskey_jsservice.newRequest(currComponentCb.data, {
					fn : (response) => {
						currComponentCb.data = response;
						//currComponentCb.requestSyskey still
						core.rxExecute(currComponentCb);
					},
					args : {
						response : false,
						currComponentCb : currComponentCb
					}
				});
			}, 100, currComponentCb);

		} else {
			core.rxExecute(currComponentCb);
		}
	}


	core.parseHrefHandlers();
}

core.rxExecute = (currComponentCb) => {
	if (!currComponentCb.componentEntryPoint) return;
	if (currComponentCb.component == null) return;
	if (currComponentCb.onlyOne == true && currComponentCb.alreadyOne == true) {
		return;
	}
	
	if (currComponentCb.component){
		core.preProcess(currComponentCb.component, currComponentCb.data);
		window[currComponentCb.componentName].__setContext({ component : currComponentCb.component , event : false });
	}

	if (currComponentCb && currComponentCb.data && currComponentCb.onlyOne) currComponentCb.data.__onlyOne = true;

	currComponentCb.componentEntryPoint(currComponentCb.data);

	/*var newEvent = new CustomEvent("componentLoaded", currComponentCb);
	document.body.dispatchEvent(newEvent);*/


}

core.initFromBootData = () => {
	var bootDataTag = document.querySelector("head .boot-data-tag");
	var arrBootData = JSON.parse(bootDataTag.innerHTML);
	bootDataTag.parentElement.removeChild(bootDataTag);

	for (var componentName in arrBootData) {
		var componentData = arrBootData[componentName];
		
		Object.freeze(componentName);

		if (window[componentName] && typeof window[componentName].main !== "undefined"){
			componentData.componentEntryPoint = window[componentName].main;
		}

		var parentComponent = document.querySelectorAll(componentData.designPath);
		parentComponent = parentComponent[parentComponent.length-1]; //:last-child fails in selectors wth #IDs

		componentData.component = (parentComponent)?parentComponent.querySelector("cmp.component."+componentName):false;

		if (componentData.data && componentData.data == "__DEMO__") {
			console.log("DEMO MODE2");
			if (window.a9os_core_taskbar_popuparea) a9os_core_taskbar_popuparea.showDemoPopup();
			return false;
		}

		if (componentData.component){
			core.preProcess(componentData.component, componentData.data);
			window[componentName].__setContext({ component : componentData.component , event : false });
		}

		if (componentData && componentData.data && componentData.onlyOne) componentData.data.__onlyOne = true;

		if (componentData.componentEntryPoint) componentData.componentEntryPoint(componentData.data);

	}

	core.parseHrefHandlers();
	document.body.classList.add("loaded");
}

core.setComponentData = (componentName, componentData, currentUrl) => {
	if (componentName.indexOf("body ") != -1){
		return;
	}

	var dataOnlyOneAdd = false;

	if (componentData.js != "") {
		var newScript = document.createElement("script");
		newScript.innerHTML = componentData.js;
		newScript.setAttribute("data-component-name", componentName);

		if (componentData.html != "") newScript.setAttribute("data-has-html", "true");
		var ifPrevInclude = document.querySelector("head script[data-component-name='"+componentName+"']");
		if (ifPrevInclude && !componentData.onlyOne) ifPrevInclude.parentElement.removeChild(ifPrevInclude);
		if (!componentData.onlyOne || !ifPrevInclude) {
			document.querySelector("head").appendChild(newScript);	
			Object.freeze(componentName);
		}
		if (componentData.onlyOne && !dataOnlyOneAdd) {
			 newScript.setAttribute("data-only-one", "true");
			 dataOnlyOneAdd = true;
		}
	}


	if (componentData.css != ""){
		var newStyle = document.createElement("style");
		newStyle.setAttribute("data-component-name", componentName);
		newStyle.innerHTML = componentData.css;

		var ifPrevInclude = document.querySelector("head style[data-component-name='"+componentName+"']");
		if (ifPrevInclude && !componentData.onlyOne) ifPrevInclude.parentElement.removeChild(ifPrevInclude);
		if (!componentData.onlyOne || !ifPrevInclude) document.querySelector("head").appendChild(newStyle);

		if (componentData.onlyOne && !dataOnlyOneAdd) {
			 newStyle.setAttribute("data-only-one", "true");
			 dataOnlyOneAdd = true;
		}
	}

	//html
	if (componentData.html != ""){
		if (componentData.clearPath) {
			var arrClearComponents = document.querySelectorAll(componentData.clearPath+" > *");
			for(var i = 0 ; i < arrClearComponents.length ; i++){
				var currChild = arrClearComponents[i];
				currChild.parentElement.removeChild(currChild);
			}
		}

		var parentComponent = document.querySelectorAll(componentData.designPath);
		parentComponent = parentComponent[parentComponent.length-1]; //:last-child fails in selectors wth #IDs

		if (parentComponent.isSameNode(document.querySelector("body > head"))) {
			throw component_name + " - Design path cannot be <head>";
		}

		var alreadyOne = false;
		if (componentData.onlyOne && parentComponent.querySelector("cmp.component."+componentName)) {
			alreadyOne = true;
		}

		if (componentData.html != "") {		
			if (!componentData.onlyOne || !parentComponent.querySelector("cmp.component."+componentName)){
				var newComponent = document.createElement("cmp");
				newComponent.classList.add("component");
				newComponent.classList.add(componentName);
				newComponent.setAttribute("data-component-name", componentName);

				core.setComponentUrl(newComponent, currentUrl);

				if (componentData.onlyOne && !dataOnlyOneAdd) {
					newComponent.setAttribute("data-only-one", "true");
					dataOnlyOneAdd = true;
				}

				newComponent.innerHTML = componentData.html;
				parentComponent.appendChild(newComponent);

				core.changeComponentScopesByElement(newComponent, true);
			}
		}
	}
	////////

	var componentEntryPoint = false;
	if (window[componentName] && typeof window[componentName].main !== "undefined"){
		componentEntryPoint = window[componentName].main;
	}
	var compCallbackObj = {
		componentEntryPoint : componentEntryPoint,
		componentName : componentName,
		component : (parentComponent)?parentComponent.querySelector("cmp.component."+componentName+":last-child"):false, //traigo la referencia al nuevo hijo de arriba
		onlyOne : componentData.onlyOne,
		alreadyOne : alreadyOne,
		data : componentData.data
	};

	if (componentName != "a9os_core_main" && componentName != "a9os_core_window") {
		if (componentData.data 
		&& typeof componentData.data == "object" 
		&& "__REQUESTSYSKEY__" in componentData.data) {
			compCallbackObj.requestSyskey = true;
		}
	}

	if (componentData.data && componentData.data == "__DEMO__") {
		console.log("DEMO MODE2");
		if (window.a9os_core_taskbar_popuparea) a9os_core_taskbar_popuparea.showDemoPopup();
		return false;
	}

	return compCallbackObj;
}








core.preProcess = (currComponent, data, foreachDirectChild) => {

	var dataKeys = ["data-textcontent", "data-src", "data-href", "data-foreach", "data-value", "data-checked", "data-data", "data-tooltip-title"];
	for (var z = 0 ; z < dataKeys.length ; z++){
		var currKey = dataKeys[z];
		if (currComponent.hasAttribute(currKey)){
			fillComponent(currKey, currComponent, data);
		}
		if (currKey == "data-foreach" && foreachDirectChild){
			var arrMatchElements = [];
			if (arrComponentCildrens = currComponent.children){ //only direct childs
				for (var i = 0 ; i < arrComponentCildrens.length ; i++){
					if (arrComponentCildrens[i].hasAttribute(currKey)){
						arrMatchElements.push(arrComponentCildrens[i]);
					}
				}
				
			}
		} else {
			var arrMatchElements = currComponent.querySelectorAll("*["+currKey+"]");
		}

		for (var x = 0 ; x < arrMatchElements.length ; x++){
			var currInnerComponent = arrMatchElements[x];
			if (currInnerComponent.hasAttribute(currKey)){
				fillComponent(currKey, currInnerComponent, data);
			}
		}
		
	}

	function fillComponent(currKey, component, data){
		switch (currKey){
			case "data-textcontent":
				if ((data = core.getDataByPath(data, component.getAttribute(currKey))) !== null) {
					if (typeof data == "object") data = JSON.stringify(data);
					component.innerHTML = data;
				}
			break;
			case "data-src":
				if ((data = core.getDataByPath(data, component.getAttribute(currKey))) !== null){
					if (typeof data == "object") data = JSON.stringify(data);
					component.src = data;
				}
			break;
			case "data-href":
				if ((data = core.getDataByPath(data, component.getAttribute(currKey))) !== null){
					if (typeof data == "object") data = JSON.stringify(data);
					component.href = data;
				}
			break;
			case "data-value":
				if ((data = core.getDataByPath(data, component.getAttribute(currKey))) !== null) {
					var origData = data;
					if (typeof data == "object") data = JSON.stringify(data);
					component.setAttribute("value", data);
					component.value = origData;
				}
			break;
			case "data-checked":
				if ((data = core.getDataByPath(data, component.getAttribute(currKey))) !== null) {
					var origData = data;
					component.checked = origData;
				}
			break;
			case "data-tooltip-title":
				if ((data = core.getDataByPath(data, component.getAttribute(currKey))) !== null) {
					var origData = data;
					component.setAttribute("title", origData);
				}
			break;
			case "data-data":
				var arrMultiDataData = component.getAttribute(currKey).split(",");
				var origData = data;
				for (var i = 0 ; i < arrMultiDataData.length ; i++) {
					arrMultiDataData[i] = arrMultiDataData[i].trim();
					var arrDataKey = arrMultiDataData[i].split(":");
					if ((data = core.getDataByPath(origData, arrDataKey[1].trim())) !== null) {
						if (typeof data == "object") data = JSON.stringify(data);
						component.setAttribute("data-"+arrDataKey[0].trim(), data);		
					}
				}
			break;
			case "data-foreach":
				var foreachCmp = component.getAttribute(currKey);
				var arrForeachGrp = foreachCmp.split(":");
				var currArrDataset = core.getDataByPath(data, arrForeachGrp[0]);

				if (currArrDataset === null && component.originalForeachItem) break;

				component.originalForeachItem = component.originalForeachItem||false;
				var previousForeachItem = false;
				if (component.originalForeachItem) {
					previousForeachItem = component.originalForeachItem;
					component.originalForeachItem = false;
				}

				var clildrenLength = component.children.length;

				if (!previousForeachItem) {
					var qtyTopPrevents = 0;
					var qtyBottomPrevents = 0;
					for (var j = 0 ; j < clildrenLength ; j++){
						var currChildren = component.children[qtyTopPrevents + qtyBottomPrevents];
						if (currChildren.hasAttribute("data-preventloop")) {
							if (!previousForeachItem) { //arriba
								currChildren.setAttribute("data-preventloop-top", "true");
								qtyTopPrevents++;
							} else { //abajo
								currChildren.setAttribute("data-preventloop-bottom", "true");
								qtyBottomPrevents++;
							}
						} else {
							if (!previousForeachItem)
								previousForeachItem = currChildren.cloneNode(true);
							component.removeChild(currChildren);
						}
					}
				}

				//reset all items
				var qtyBottomPrevents = component.querySelectorAll("*[data-preventloop-bottom]").length;
				for (var i = component.children.length - 1; i >= 0; i--) {
					var currChildren = component.children[i];
					if (!currChildren.hasAttribute("data-preventloop"))
						component.removeChild(currChildren);
					
				}
				//////
				
				component.originalForeachItem = previousForeachItem;
				if (currArrDataset === null) break;

				if (!Array.isArray(currArrDataset)) currArrDataset = Object.values(currArrDataset);
				
				for (var i = 0 ; i < currArrDataset.length ; i++){
					copyElement = previousForeachItem.cloneNode(true);
					copyElement.classList.add(arrForeachGrp[1]+"-ix"+i);

					var currData = currArrDataset[i];
					var newData = {};
					newData[arrForeachGrp[1]] = currData;
					core.preProcess(copyElement, newData, true);

					component.insertBefore(copyElement, component.children[component.children.length - qtyBottomPrevents]);
				}
			break;
		}
	}
}

core.getDataByPath = (data, path) => {
	if (!data || (data && data == "")){
		return null;
	}
	var arrPaths = path.split(".");
	var tmpDataPart = data;
	for (var i = 0 ; i < arrPaths.length ; i++){
		if (typeof tmpDataPart[arrPaths[i]] === "undefined"){
			return null;
		}
		tmpDataPart = tmpDataPart[arrPaths[i]];
	}
	return tmpDataPart;
}

core.parseHrefHandlers = () => {
	var arrA = document.querySelectorAll("#main-content a");
	for (var  i = 0 ; i < arrA.length ; i++){
		if (!arrA[i].hrefProcessed){
			arrA[i].addEventListener("click", ((element) => { return (event) => {
				core.link.handle(element, event);
			}})(arrA[i]));
			arrA[i].hrefProcessed = true;
		}
	}
}







core.reloadPage = () => {
	window.onbeforeunload = null;
	location.reload();
}

core.collectComponentGarbage = () => {
	var arrComponentResources = document.querySelectorAll("head script[data-component-name][data-has-html], head style[data-component-name]");

	for (var i = 0 ; i < arrComponentResources.length ; i++){

		if (!document.querySelector("cmp.component."+arrComponentResources[i].getAttribute("data-component-name"))) {
			arrComponentResources[i].parentElement.removeChild(arrComponentResources[i]);
			if (arrComponentResources[i].tagName.toLowerCase() == "script") {
				window[arrComponentResources[i].getAttribute("data-component-name")] = null;
			}
		}
	}
}








core.callCallback = (callbackFunction, arrInjectedArgs, thisArg) => {
	if (!callbackFunction) return null;
	if (!callbackFunction.fn) {
		console.error("callCallback: function object has no function");
		return null;
	}
	if (!callbackFunction.args) {
		console.error("callCallback: function object has no arguments");
		return null;
	}

	for (var i in callbackFunction.args) {
		if (arrInjectedArgs && arrInjectedArgs[i]) {	
			callbackFunction.args[i] = arrInjectedArgs[i];
		}
	}


	return callbackFunction.fn.apply(thisArg||null, Object.values(callbackFunction.args));
}






core.addEventListener = (element, listener, callback, ...extraArgs) => {
	
	if (!element) {
		console.error("Element not defined");
		return false;
	}
	
	if (!NodeList.prototype.isPrototypeOf(element) && !Array.isArray(element)) {
		addListenerInElement(element, listener, callback, extraArgs);
	} else {
		for (var i = 0 ; i < element.length ; i++){
			addListenerInElement(element[i], listener, callback, extraArgs);
		}
	}

	function addListenerInElement(element, listener, callback, extraArgs) {
		if (!Array.isArray(listener)) {
			addListenerInListener(element, listener, callback, extraArgs);
		} else {
			for (var i = 0 ; i < listener.length ; i++) {
				addListenerInListener(element, listener[i], callback, extraArgs);
			}
		}
	}

	function addListenerInListener(element, listener, callback, extraArgs) {
		var useCapture = false;
		if (listener.indexOf("-") == listener.length-1) {
			useCapture = true;	
			listener = listener.slice(0, -1);
		}

		element.addEventListener(listener, (event) => {
			event.listenerName = listener;
			realCallback(event, callback, extraArgs);
		}, useCapture);
	}

	function realCallback (event, callback, extraArgs) {
		var element = event.currentTarget;

		if (element == document.querySelector(".a9os-main")){ //For right menu handle
			element = event.composedPath()[0];
		}

		var updateActuator = (event.listenerName == "click" || event.listenerName == "mousedown" || event.listenerName == "touchstart");

		if (event.listenerName != "componentsetcontext") core.changeComponentScopesByElement(element, updateActuator);

		var arrArgs = [event, element].concat(extraArgs);
		callback.apply(element, arrArgs);
	}
}
core.changeComponentScopesByElement = (element, updateActuator) => {
	
	var arrSelectWindowCalls = []; //agarro todos los _selectWindow, para ejecutarlos al revés

	if (element.tagName && element.tagName.toLowerCase() == "cmp") element = element.childNodes[0];

	var currComponent = element;
	if (!currComponent.goToParentClass) return;


	var urlSet = false;

	var arrCurrentActuatorPath = [];

	while (currComponent = currComponent.goToParentClass("component", "cmp")){
		var componentName = currComponent.getAttribute("data-component-name");


		if (updateActuator
		&& currComponent.classList.contains("with-own-url") 
		&& currComponent.hasAttribute("data-url") 
		&& !urlSet) {

			urlSet = true;
			core.link.push(currComponent.getAttribute("data-url"), {}, true);

		}

		if (updateActuator) {
			arrCurrentActuatorPath.push(currComponent);
			currComponent.classList.add("actuator-part");
		}

		if (!window[componentName]) continue;
		window[componentName].__setContext({ component : currComponent , event : event });

		if (typeof window[componentName]._selectWindow !== "undefined") {
			arrSelectWindowCalls.push(window[componentName]._selectWindow);
		}

		if (updateActuator) core.pushCustomEvent(currComponent, "componentsetcontext");
	}

	if (updateActuator) {
		var arrActuatorPartClassedCmps = document.querySelectorAll("cmp.actuator-part");
		for (var i = 0 ; i < arrActuatorPartClassedCmps.length ; i++) {
			var currActuatorPartClassedCmp = arrActuatorPartClassedCmps[i];

			var isPartOfCurrentActuator = false;
			for (var x = 0 ; x < arrCurrentActuatorPath.length ; x++) {
				var currCurrentActuatorPath = arrCurrentActuatorPath[x];
				if (currActuatorPartClassedCmp.isSameNode(currCurrentActuatorPath)) {
					isPartOfCurrentActuator = true;
					break;
				}
			}

			if (!isPartOfCurrentActuator) currActuatorPartClassedCmp.classList.remove("actuator-part");
		}
	}

	for (var i = arrSelectWindowCalls.length - 1; i >= 0; i--) {
		arrSelectWindowCalls[i]();
	}

	return element;
}
core.setComponentUrl = (component, url) => {
	component.classList.add("with-own-url");
	component.setAttribute("data-url", url);

	return component;
}
core.setUrlByActuatorPart = (url) => {
	var arrActuatorPart = document.querySelectorAll("cmp.actuator-part.with-own-url:not([data-url='/'])");
	if (arrActuatorPart.length == 0) return;
	arrActuatorPart[arrActuatorPart.length - 1].setAttribute("data-url", url);

}
core.removeComponentUrlHandle = (component) => {
	component.classList.remove("with-own-url");
	component.removeAttribute("data-url");
}

core.scopeComponentByUrl = (url) => {
	var cmpByUrl = document.querySelectorAll("cmp.with-own-url[data-url='"+url+"']");
	if (cmpByUrl.length == 0) return false;

	cmpByUrl = cmpByUrl[cmpByUrl.length - 1];

	core.changeComponentScopesByElement(cmpByUrl, true);

	return cmpByUrl;
}


core.pushCustomEvent = (element, eventName, eventDetail) => {
	
	var customEvent = new CustomEvent(eventName, { detail : eventDetail });
	element.dispatchEvent(customEvent);
}
























core.getRandomId = (arrToCompareUnique, fromIndexes, length) => {
	var length = length||5;
	var arrToCompareUnique = arrToCompareUnique||[];
	var fromIndexes = fromIndexes|false;

	arrRnd = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
	if (fromIndexes) arrToCompareUnique = Object.keys(arrToCompareUnique);

	var newRnd = "";
	do {
		for (var i = 0 ; i < length ; i++) newRnd += arrRnd[Math.floor(Math.random() * (arrRnd.length-1))]; 
	} while (arrToCompareUnique.includes(newRnd));

	return newRnd;
}






core.cookie = {};
core.cookie.remove = (name) => {
	document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}







core.link = {};
core.link.handle = (element, event) => {
	if ((element.href.split("/")[3] == MAIN_DIR.split("/")[0] || MAIN_DIR.split("/")[0] == "") && element.href.split("/")[2] == document.location.host){
		if (element.href.indexOf(document.location.protocol+"//"+document.location.host+document.location.pathname) == 0 && element.href.indexOf("#") !== -1) {
			core.link.push(element.href, {}, true);
		} else {
			core.link.push(element.href);
		}
		event.preventDefault();
		return false;
	} 
	return true;
}

core.link.push = (url, hashData, preventInitLoop, pushToHistory) => {
	var hashString = core.link.hash.arrayToQueryString(hashData);
	if ((url+hashString).length > 2048) console.error("URL max length exceeded");


	if (url+hashString == window.location.pathname+window.location.search+window.location.hash
	&& preventInitLoop) return;
	
	if (!pushToHistory) history.replaceState({}, "", url+hashString);
	else history.pushState({}, "", url+hashString);

	if (!preventInitLoop) core.main();
}

core.link.title = (title) => {
	document.title = title;
}

core.link.favicon = (imgElOrUrl) => {

	var imgToCanvasToHeadFn = {
		fn : (loadedImgDiv) => {
			var canvas = document.querySelector("#svg-converter");
			canvas.getContext("2d").clearRect(0,0, canvas.width, canvas.height);
			canvas.getContext("2d").drawImage(loadedImgDiv,0,0, canvas.width, canvas.height);
			var faviconUrl = canvas.toDataURL('image/png');
			var link = document.querySelector("link[rel*='icon']") || document.createElement('link');
			link.rel = 'shortcut icon';
			link.href = faviconUrl;
			link.type = "image/png";
			document.getElementsByTagName('head')[0].appendChild(link);
		},
		args : {
			loadedImgDiv : false
		}
	};


	if (imgElOrUrl instanceof HTMLElement) {
		if (imgElOrUrl.complete) {
			core.callCallback(imgToCanvasToHeadFn, {
				loadedImgDiv : imgElOrUrl
			});
		} else {
			core.addEventListener(imgElOrUrl, "load", (e, img, imgToCanvasToHeadFn) => {
				core.callCallback(imgToCanvasToHeadFn, {
					loadedImgDiv : img
				});
			}, imgToCanvasToHeadFn);
		}
	} else {
		core.sendXhr(
			imgElOrUrl,
			{},
			{
				fn : (response, imgToCanvasToHeadFn) => {
					var img = document.createElement("img");
					img.src = a9os_app_vf_main.fileHandle.getBlobUrl(response.response);
					core.addEventListener(img, "load", (e, img, imgToCanvasToHeadFn) => {
						core.callCallback(imgToCanvasToHeadFn, {
							loadedImgDiv : img
						});
					}, imgToCanvasToHeadFn);
				},
				args : {
					response : false,
					imgToCanvasToHeadFn : imgToCanvasToHeadFn
				}
			},
			false,
			true,
		);
	}
}


core.link.hash = {};
core.link.hash.get = () => {
	return core.link.hash.queryStringToArray();
}
core.link.hash.set = (arrNewData) => {	
	var arrHashData = core.link.hash.queryStringToArray();

	for (var i in arrNewData) {
		arrHashData[i] = arrNewData[i];
		if (arrNewData[i] === null) delete arrHashData[i];
	}

	var finalString = core.link.hash.arrayToQueryString(arrHashData);

	core.setUrlByActuatorPart(window.location.pathname+finalString);

	core.link.push(window.location.pathname+finalString, {}, true);
}

core.link.hash.queryStringToArray = () => {
	if (window.location.search == ""){
		return [];
	}

	var arrPreHash = window.location.search.split("?")[1].split("&");
	var arrSal = [];
	for (var i = 0 ; i < arrPreHash.length ; i++){
		var currKeyValue = arrPreHash[i].split("=");
		arrSal[currKeyValue[0]] = atob(currKeyValue[1]);

		if (core.link.hash._isJson(arrSal[currKeyValue[0]])) arrSal[currKeyValue[0]] = JSON.parse(arrSal[currKeyValue[0]]);
	}

	return arrSal;
}

core.link.hash.arrayToQueryString = (arrData) => {
	var finalString = "?";

	for (var i in arrData){
		if (arrData[i] != ""){
			if (typeof arrData[i] == "object") {
				arrData[i] = JSON.stringify(arrData[i]);
			}
			finalString += i + "=" + btoa(arrData[i]) + "&";
		}
	}
	finalString = finalString.substring(0, finalString.length - 1);

	return finalString;
}

core.link.hash._isJson = (str) => {
	try {
		JSON.parse(str);
	} catch (e) {
		return false;
	}
	return true;
}











core.loading = {};
core.loading.qtyLoadings = 0;
core.loading.set = () => {
	core.loading.qtyLoadings++;
	//console.error("SET"+core.loading.qtyLoadings);
	document.body.classList.add("loading");
}
core.loading.unset = () => {
	core.loading.qtyLoadings--;
	//console.error("UNSET"+core.loading.qtyLoadings);
	if (core.loading.qtyLoadings < 0) core.loading.qtyLoadings = 0;
	if (core.loading.qtyLoadings == 0) document.body.classList.remove("loading");
}






core.devToolsSec = {};
core.devToolsSec.started = false;
core.devToolsSec.start = () => {
	if (core.devToolsSec.started) return;
	core.devToolsSec.started = true;

	var checkStatus = false;

	var img = document.createElement("img");
	Object.defineProperty(img, 'id', {
		get : () => {
			checkStatus = true;
		}
	});

	var intr = setInterval((img) => {
		checkStatus = false;
		console.dir(img);
		if (checkStatus) {
			document.head.innerHTML = "";
			document.body.innerHTML = "refresh the page";
			core = null;
			var windowObjectKeys = Object.keys(window);
			for (var i = 0 ; i < windowObjectKeys.length ; i++){
				if (windowObjectKeys[i].indexOf("a9os") !== -1) {
					window[windowObjectKeys[i]] = null;
				}
			}
			console.clear();
			clearInterval(intr);
		}

	}, 100, img);
}
// core.devToolsSec.start();









core.asyncGear = {};
core.asyncGear.arrGears = {};
core.asyncGear.loop = (forceAddLoop) => {
	var forceAddLoop = forceAddLoop||false;

	var arrGears = core.asyncGear.arrGears;
	var arrGearIds = Object.keys(arrGears);

	if (arrGearIds.length == 0) {
		setTimeout(core.asyncGear.loop, 5000);
		return;
	}

	var arrGearsToSend = {};
	for (var i = 0 ; i < arrGearIds.length ; i++) {
		arrGearsToSend[arrGearIds[i]] = arrGears[ arrGearIds[i] ].lastMsgId;
	}

	core.sendRequest(
		"/asyncgear/getMessages", 
		{
			arrGearsToSend : arrGearsToSend,
		},
		{
			fn : (response, forceAddLoop) => {
				for (var currGearId in response) {
					var arrGearMsgs = response[currGearId];

					for (var i = 0 ; i <  arrGearMsgs.length ; i++){
						if (!core.asyncGear.arrGears[currGearId]) continue;
						
						core.callCallback(
							core.asyncGear.arrGears[currGearId].fn, 
							{
								message : arrGearMsgs[i]
							}
						);

						core.asyncGear.arrGears[currGearId].lastMsgId = arrGearMsgs[i].message_id;

						if (arrGearMsgs[i].is_final_message == 1) {
							delete core.asyncGear.arrGears[currGearId];
						}
					}
				}

				if (!forceAddLoop) setTimeout(core.asyncGear.loop, 1000);
			},
			args : {
				response : false,
				forceAddLoop : forceAddLoop
			}
		},
		false,
		true
	);
}
core.asyncGear.loop();

core.asyncGear.append = (gearId, gearFn, reappendId) => {
	core.asyncGear.arrGears[gearId] = { fn : gearFn, lastMsgId : 0 };
	setTimeout(() => {
		core.asyncGear.loop(true);
	}, 500);


	if (reappendId) {
		core.asyncGear.reappendId.addNew(gearId, reappendId);
	}
}

core.asyncGear.reappendId = {};
core.asyncGear.reappendId.addNew = (gearId, reappendId) => {	
	core.sendRequest(
		"/asyncgear/reappendId/addNew",
		{
			gearId : gearId,
			reappendId : reappendId
		},
		false,
		false,
		true
	);
}
core.asyncGear.reappendId.getById = (reappendId, callbackFunction) => {
	core.sendRequest(
		"/asyncgear/reappendId/getById",
		{
			reappendId : reappendId
		},
		{
			fn : (response, callbackFunction) => {
				core.callCallback(callbackFunction, {
					arrGearIds : response
				});
			},
			args : {
				response : false,
				callbackFunction : callbackFunction
			}
		},
		false,
		true
	);
}








core.sec = {};
core.sec.callOnlyFrom = (originFunction, arrBlWl) => {
	var originFunctionHashCode = core.sec.stringHashCode(originFunction.caller.toString());

	var arrBlacklist = arrBlWl.blacklist||false;
	var arrWhitelist = arrBlWl.whitelist||false;

	if (arrBlacklist) {
		for (var i = 0 ; i < arrBlacklist.length ; i++) {
			if (typeof arrBlacklist[i] == 'function' && originFunctionHashCode == core.sec.stringHashCode(arrBlacklist[i].toString())) {
				throw "callOnlyFrom : not allowed";
			}
		}
	}

	if (arrWhitelist) {
		var inWhitelist = false;
		for (var i = 0 ; i < arrWhitelist.length ; i++) {
			if (typeof arrWhitelist[i] == 'function' && originFunctionHashCode == core.sec.stringHashCode(arrWhitelist[i].toString())) {
				inWhitelist = true;
			}
		}

		if (!inWhitelist) throw "callOnlyFrom : not allowed";
	}
	return true;
}
core.sec.stringHashCode = (str) => {
	str = str.replace(/\s+/g, "");
	var hOutput = 0;
	for (var i = 0; i < str.length; i++) {
		hOutput = ( ( hOutput << 5 ) - hOutput ) + str.charCodeAt(i);
		hOutput = hOutput & hOutput; // Convert to 32bit integer
	}

	return hOutput;
	// FROM https://werxltd.com/wp/2010/05/13/javascript-implementation-of-javas-string-hashcode-method/
}






core.servWk = {};
core.servWk.main = () => {
	if ("serviceWorker" in navigator) {
		navigator.serviceWorker.register("/servwk.js").then(() => { });
	}
	window.addEventListener("beforeinstallprompt", (event) => {
		window.deferredPrompt = event;

	});
}

/*core.servWk.showNotification = () => {
	Notification.requestPermission(function(result) {
		if (result === 'granted') {
			navigator.serviceWorker.ready.then(function(registration) {
				registration.showNotification('Vibration Sample', {
					body: 'Buzz! Buzz!',
					icon: '/resources/app-icon-round-192.png',
					vibrate: [200, 100, 200, 100, 200, 100, 200],
					tag: 'vibration-sample'
				});
			});
		}
	});
}*/





window.addEventListener("DOMContentLoaded", core.main);
Object.freeze(core);