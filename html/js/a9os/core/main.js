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
a9os_core_main.systemLoaded = false;
a9os_core_main.main = (data) => {
	
	if (data && typeof data == "object" && "__REQUESTSYSKEY__" in data) {
		setTimeout((data) => {
			a9os_core_requestsyskey_jsservice.newRequest(data, {
				fn : (response) => {
					core.link.push("/", {}, true);
					core.reloadPage();
				},
				args : {
					response : false
				}
			});

		}, 1000, data);//new thread, after system load order
	}


	self.mainDiv = self.component.querySelector(".a9os-main");
	if (self.systemLoaded) return;

	//menu handlers
	self.mainDiv.addEventListener("contextmenu", (event) => {
		var arrComposedPath = event.composedPath();
		for (var i = 0 ; i < arrComposedPath.length ; i++) {
			var currPath = arrComposedPath[i];
			if (self.cutCopyPaste.compare(currPath)) {
				return;
			}
			if (currPath.tagName.toUpperCase() == "CMP") break;
			if (currPath.classList.contains("a9os-menu")) break;
			if (self.showMenuR(currPath, event)) break;

			if (currPath.hasAttribute("data-native-menu")) return;
		}
		event.preventDefault();
	});
	
	self.mainDiv.addEventListener("mousedown", (event) => {
		self.removeMenu();
		var arrComposedPath = event.composedPath();
		for (var i = 0 ; i < arrComposedPath.length ; i++) {
			var currPath = arrComposedPath[i];
			if (currPath.tagName.toUpperCase() == "CMP") break;
			if (currPath.classList.contains("a9os-menu")) break;
			if (self.showMenu(currPath, event)) break;
		}
		return false;
	});
	//////

	a9os_core_main.windowCrossCallback.observe();

	a9os_core_main.kbShortcut.attach();

	a9os_core_main.moveEvent.attach();

	self.systemLoaded = true;

	if (typeof a9os_core_taskbar == "undefined") a9os_core_taskbar = false;

	self.coreVersion = data.coreVersion;
	self.deskVersion = data.deskVersion;

	if (data.systemAnonMode != "demo") {
		window.onbeforeunload = () => {
			return true;
		}
	}
}

a9os_core_main.addEventListener = (element, listener, callback, ...extraArgs) => {
	
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

		self.changeWindowScope(element);
		var arrArgs = [event, element].concat(extraArgs);
		callback.apply(element, arrArgs);
	}
}
a9os_core_main.changeWindowScope = (element) => { // hacer mas liviano
	
	var arrSelectWindowCalls = []; //agarro todos los _selectWindow, para ejecutarlos al revés

	if (element.tagName && element.tagName.toLowerCase() == "cmp") element = element.childNodes[0];

	var currComponent = element;
	if (currComponent.goToParentClass) {
		while (currComponent = currComponent.goToParentClass("component", "cmp")){
			var componentName = currComponent.getAttribute("data-component-name");
			if (!window[componentName]) continue;

			window[componentName].__setContext({ component : currComponent , event : event });

			if (typeof window[componentName]._selectWindow !== "undefined") {
				arrSelectWindowCalls.push(window[componentName]._selectWindow);
			}
		}
	}

	for (var i = arrSelectWindowCalls.length - 1; i >= 0; i--) {
		arrSelectWindowCalls[i]();
	}

	return element;
}

a9os_core_main.pushCustomEvent = (element, eventName, eventDetail) => {
	
	var customEvent = new CustomEvent(eventName, { detail : eventDetail });
	element.dispatchEvent(customEvent);
}



a9os_core_main.showMenuR = (elem, event) => {
	
	return self.showMenu(elem, event, "data-menu-r");
}

a9os_core_main.showMenu = (elem, event, attributeName, originalAttrName) => {
	
	var attributeName = attributeName || "data-menu";

	if (!elem.hasAttribute(attributeName)){ // si no toco ningun menu, vuelo los menus
		self.removeMenu();
		if (window.a9os_core_window) a9os_core_window.unsetMenuBarUsed();
		return false;
	}

	if (elem.hasAttribute("data-menu-bar")){ // Archivo | Edición | Ver | Ayuda | ...
		attributeName = "data-menu-bar";
	}

	var arrMenuData = JSON.parse(elem.getAttribute(attributeName));
	self.mainDiv.classList.add("mouse-menu");

	// originType r = from mouse | l = from item | m = from submenu
	var originType = "r";
	if (attributeName == "data-menu" || attributeName == "data-menu-bar"){
		originType = "l";
	}

	var actionElement = elem;
	if (elem.classList.contains("menu-item")){
		originType = "m";
		actionElement = elem.actionElement;
	}

	var menuDirection;
	if (originType == "m"){
		menuDirection = actionElement.parentElement.menuDirection;
	}


	var ifWindow = actionElement.goToParentClass("window")||false;
	if (ifWindow){
		self.selectWindow(ifWindow);
	}

	var newMenu = createMenu(arrMenuData, actionElement, menuDirection, originalAttrName||attributeName);
	self.mainDiv.appendChild(newMenu);
	calculatePosition(newMenu, originType, event, elem);
	newMenu.classList.remove("closed");

	return newMenu;


	function createMenu(arrMenuData, actionElement, menuDirection, attributeName) {
		var menuDirection = menuDirection||"R";

		var newMenu = document.createElement("div");
		newMenu.classList.add("a9os-menu");
		newMenu.classList.add("closed");
		newMenu.menuDirection = menuDirection;
		newMenu.attributeName = attributeName;

		for (var i = 0 ; i < arrMenuData.length ; i++) {
			var itemData = arrMenuData[i];
			
			if (itemData == "separator") {
				newMenu.appendChild(getNewSeparator());
				continue;
			}
			var newMenuItem = getNewMenuItem(itemData, actionElement);
			if (newMenuItem) newMenu.appendChild(newMenuItem);
		}

		//remove duplicate separators
		var arrNewMenuItems = newMenu.querySelectorAll("div");
		for (var i = arrNewMenuItems.length - 1; i >= 0; i--) {
			var currMenuItem = arrNewMenuItems[i];
			if (currMenuItem.classList.contains("hr") && arrNewMenuItems[i-1] && arrNewMenuItems[i-1].classList.contains("hr")) {
				newMenu.removeChild(currMenuItem);
			}
		}
		////////

		return newMenu;
	}
	
	function getNewSeparator() {
		var newSeparator = document.createElement("div");
		newSeparator.classList.add("hr");
		return newSeparator;
	}
	function getNewMenuItem(itemData, actionElement) { //TODO icons
		var menuItem = document.createElement("div");
		menuItem.classList.add("menu-item");
		menuItem.actionElement = actionElement;
		menuItem.menuName = itemData.name;


		var menuComponentName;
		actionElement.goToParentClass("component", "cmp").classList.forEach((cn) => {
			if (cn.indexOf("a9os") != -1){
				menuComponentName = cn;
			}
		});

		// menu bar component switch
		if (menuComponentName == "a9os_core_window" 
			&& menuItem.actionElement.parentElement 
			&& menuItem.actionElement.parentElement.classList.contains("menu-bar")) {
			menuComponentName = menuItem.actionElement.parentElement.parentElement
			.querySelector(".main-content > cmp").getAttribute("data-component-name");
		}
		//////

		if (typeof itemData.active !== "undefined" && itemData.active == false){
			menuItem.classList.add("inactive");
		}

		if (itemData.dynamicShowTrigger) {
			var dynShowMethod = self.getMethodByName(menuComponentName, itemData.dynamicShowTrigger);
			if (dynShowMethod) Object.assign(itemData, dynShowMethod(itemData, actionElement));
		}

		if (itemData.remove) return;


		var namePart = document.createElement("div");
		namePart.classList.add("center");
		namePart.innerHTML = itemData.name;

		var leftPart = document.createElement("div");
		leftPart.classList.add("left");
		leftPart.classList.add("ibl-c");

		var rightPart = document.createElement("div");
		rightPart.classList.add("right");
		rightPart.classList.add("ibl-c");
		rightPart.textContent = (itemData.shortcut||[]).join("+");



		if (itemData.checkbox && itemData.checkbox == true){
			var tmpInput = document.createElement("input");
			tmpInput.type = "checkbox";
			if (itemData.checked && itemData.checked == true){
				tmpInput.checked = true;
			}
			leftPart.appendChild(tmpInput);
		}

		if (itemData.radio && itemData.radio == true){
			var tmpInput = document.createElement("input");
			tmpInput.type = "radio";
			if (itemData.checked && itemData.checked == true){
				tmpInput.checked = true;
			}
			leftPart.appendChild(tmpInput);
		}

		if (itemData.data) {
			menuItem.dataData = itemData.data;
		}



		menuItem.actionElement = actionElement;

		if (itemData.children){
			menuItem.classList.add("has-children");
			menuItem.setAttribute("data-menu", JSON.stringify(itemData.children));
		}

		self.addEventListener(menuItem, "mousedown", (event, menuItem) => {
			event.stopPropagation();
		});

		self.addEventListener(menuItem, ["mouseenter", "touchstart"], (event, menuItem) => {
			menuItem.parentElement.querySelectorAll(".menu-item").forEach((currItem) => {
				currItem.classList.remove("selected");
			});
			menuItem.classList.add("selected");


			if (menuItem.parentElement.childrenMenu){ // solo un children del parent
				removeChildrenMenu(menuItem.parentElement.childrenMenu);
				menuItem.parentElement.childrenMenu = false;
			}
			function removeChildrenMenu(menuToRemove) {
				if (menuToRemove.childrenMenu){
					removeChildrenMenu(menuToRemove.childrenMenu);
				}
				menuToRemove.classList.add("closed");
				self.removeMenu(menuToRemove);
			}

			if (menuItem.hasAttribute("data-menu")){
				var newMenu = self.showMenu(menuItem, event, false, menuItem.parentElement.attributeName);
				menuItem.parentElement.childrenMenu = newMenu;
				newMenu.parentMenu = menuItem.parentElement;
			}
		});

		self.addEventListener(menuItem, ["mouseout", "touchleave"], (event, menuItem) => {
			if (!menuItem.parentElement.childrenMenu) menuItem.classList.remove("selected");
		});


		self.addEventListener(menuItem, "mouseup", (event, menuItem) => {
			if (menuItem.classList.contains("inactive")){
				return;
			}


			if (itemData.checkbox) {
				itemData.checked = (!itemData.checked);
			}

			var itemInput = menuItem.querySelector("input");
			if (itemInput) itemInput.checked = !itemInput.checked;

			var checkedType = "";
			if (itemData.checkbox) checkedType = "checkbox";
			else if (itemData.radio) checkedType = "radio";
			if (checkedType != "") updateMenuData(menuItem, itemData, checkedType);

			var methodToCall = self.getMethodByName(menuComponentName, itemData.action);
			if (methodToCall) methodToCall(event, menuItem.actionElement, menuItem.dataData);
			else if (itemData.action) {
				console.error("Menu Item - "+menuComponentName+"."+itemData.action+" undefined");
			}

			if (menuItem.parentElement.childrenMenu) {
				return;
			}

			self.removeMenu();
			if (window.a9os_core_window) a9os_core_window.unsetMenuBarUsed();
		});


		menuItem.appendChild(leftPart);
		menuItem.appendChild(namePart);
		menuItem.appendChild(rightPart);
		
		return menuItem;
	}

	function updateMenuData(item, itemData, checkedType) { // checkbox o radio
		var attributeName = item.parentElement.attributeName;
		var actionElement = item.actionElement;
		var arrOrigItemData = JSON.parse(actionElement.getAttribute(attributeName));

		var arrNamePath = [];

		var itemToCompare = item;
		while (itemToCompare) {
			arrNamePath.unshift(itemToCompare.menuName);
			if (itemToCompare.parentElement.parentMenu) itemToCompare = itemToCompare.parentElement.parentMenu.querySelector(".menu-item.selected");
			else itemToCompare = false;
		}

		var namePathI = 0;
		var nameCmpPointer = arrNamePath[namePathI];
		var objToModify = arrOrigItemData;

		while (namePathI < arrNamePath.length) {
			for (var i = 0 ; i < objToModify.length ; i++) {
				if (objToModify[i].name == nameCmpPointer) {
					if (checkedType == "radio") {
						if (namePathI < arrNamePath.length -1) {
							objToModify = objToModify[i].children;	
						}
					} else {
						if (objToModify[i].children) {
							objToModify = objToModify[i].children;
						} else {
							objToModify = [objToModify[i]];
						}
					}

					nameCmpPointer = arrNamePath[++namePathI];
					break;
				}
			}
		}


		for (var i = 0 ; i < objToModify.length ; i++) {
			if (checkedType == "radio") {
				if (objToModify[i].name == item.menuName && item.querySelector("input").checked) {
					objToModify[i].checked = item.querySelector("input").checked;
				} else if (objToModify[i].name != item.menuName) {
					objToModify[i].checked = false;
				}
			} else {
				if (objToModify[i].name == item.menuName) {
					objToModify[i].checked = item.querySelector("input").checked
				}
			}
		}
		
		actionElement.setAttribute(attributeName, JSON.stringify(arrOrigItemData));

		return "";
	}

	function calculatePosition(newMenu, originType, event, originElement) {
		var originElementX = 0;
		var originElementY = 0;
		var originElementW = 0;
		var originElementH = 0;
		var originElementType = "";
		var menuDirection = "R";

		if (originType == "l"){ //Left, to element
			var elementCoords = originElement.getBoundingClientRect();
			originElementX = elementCoords.left;
			originElementY = elementCoords.top;
			originElementW = elementCoords.width;
			originElementH = elementCoords.height;
			originElementType = "standard";
		} else if (originType == "m"){ // menu, to side of menuitem
			originElementX = originElement.parentElement.offsetLeft;
			originElementY = originElement.offsetTop + originElement.parentElement.offsetTop - originElement.parentElement.scrollTop;
			originElementW = originElement.offsetWidth;
			originElementH = originElement.offsetHeight;
			originElementType = "sides";
			menuDirection = originElement.parentElement.menuDirection;
			//originElementDirection = originElement.parentElement.childrenMenuDirection||"r";
		} else { // right, to cursor
			originElementX = event.clientX;
			originElementY = event.clientY+2;
			if (!originElementX && event.touches && event.touches.length > 0) {				
				originElementX = event.touches[0].clientX;
				originElementY = event.touches[0].clientY+2;
			}
			if (!originElementX && event.changedTouches && event.changedTouches.length > 0) {
				originElementX = event.changedTouches[0].clientX;
				originElementY = event.changedTouches[0].clientY+2;
			}
			originElementW = 0;
			originElementH = 0;
			originElementType = "standard";
		}

		var newMenuTop = 0;
		var newMenuLeft = 0;

		if (originElementType == "standard"){	
			if (originElementY+originElementH+newMenu.offsetHeight > a9os_core_main.mainDiv.offsetHeight){ //abajo para arriba
				if (originElementX+newMenu.offsetWidth > a9os_core_main.mainDiv.offsetWidth){ //derecha a izquierda
					newMenuTop = originElementY - newMenu.offsetHeight;
					newMenuLeft = originElementX + originElementW - newMenu.offsetWidth;
					//console.log("A");
				} else {
					newMenuTop = originElementY - newMenu.offsetHeight;
					newMenuLeft = originElementX;
					//console.log("B");
				}
			} else {
				if (originElementX+newMenu.offsetWidth > a9os_core_main.mainDiv.offsetWidth){
					newMenuTop = originElementY+originElementH;
					newMenuLeft = originElementX + originElementW - newMenu.offsetWidth;
					//console.log("C");
				} else {
					newMenuTop = originElementY+originElementH;
					newMenuLeft = originElementX;
					//console.log("D");
				}
			}
		} else if (originElementType == "sides"){
			if (originElementX + originElementW + newMenu.offsetWidth > a9os_core_main.mainDiv.offsetWidth
			||	(originElementX - newMenu.offsetWidth < 0 && menuDirection == "L")){
				menuDirection = (menuDirection=="R")?"L":"R";
			}
			if (originElementY+newMenu.offsetHeight > a9os_core_main.mainDiv.offsetHeight){ // abajo para arriba
				if (menuDirection == "R"){
					newMenuTop = originElementY + originElementH - newMenu.offsetHeight;
					newMenuLeft = originElementX + originElementW -5;
					//console.log("E");
				} else { //L
					newMenuTop = originElementY + originElementH - newMenu.offsetHeight +5;
					newMenuLeft = originElementX - newMenu.offsetWidth +5;
					//console.log("F");
				}
			} else {
				if (menuDirection == "R"){
					newMenuTop = originElementY;
					newMenuLeft = originElementX + originElementW -5;
					//console.log("G");
				} else { //L
					newMenuTop = originElementY;
					newMenuLeft = originElementX - newMenu.offsetWidth +5;
					//console.log("H");
				}
			}
		}
		
		if (newMenuTop < 0){
			newMenuTop = 2;
		}
		if (newMenuLeft < 0) {
			newMenuLeft = 0;
		}
		
		newMenu.style.left = newMenuLeft;
		newMenu.style.top = newMenuTop - 2;
		newMenu.menuDirection = menuDirection;
	}
}

a9os_core_main.removeMenu = (parentItem) => {
	
	if (!parentItem) {
		self.mainDiv.querySelectorAll(".a9os-menu").forEach((currMenu) => {
			self.removeMenu(currMenu);
		});
		self.mainDiv.classList.remove("mouse-menu");
		return;
	}


	parentItem.classList.add("closed");
	setTimeout((parentItem) => {
		if (self.mainDiv.contains(parentItem)) self.mainDiv.removeChild(parentItem);
	}, 10, parentItem);


	if (self.mainDiv.querySelectorAll(".a9os-menu").length == 0){
		self.mainDiv.classList.remove("mouse-menu");
	}

	var arrDataMenuShownItems = self.mainDiv.querySelectorAll("*[data-menu-shown]");
	for (var i = 0 ; i < arrDataMenuShownItems.length ; i++){
		arrDataMenuShownItems[i].removeAttribute("data-menu-shown");
	}
}


a9os_core_main.moveEvent = {};
a9os_core_main.moveEvent.attach = () => {
	
	var mainDiv = self.component.querySelector(".a9os-main");
	
	mainDiv.boolMoveEvent = {
		preDown : false,
		initPosition : [],
		moving : false,
		matchEvents : []
	};

	self.addEventListener(document, ["mouseup", "touchend", "mousedown"], (event) => {
		//var mainDiv = event.currentTarget;
		if (!mainDiv.boolMoveEvent) return;

		for (var i = 0 ; i < mainDiv.boolMoveEvent.matchEvents.length ; i++){
			var currMatchEvent = mainDiv.boolMoveEvent.matchEvents[i];


			if (!currMatchEvent.isMoved) continue;

			currMatchEvent.isMoved = false;

			var arrArgs = [event, currMatchEvent.element].concat(currMatchEvent.extraArgs);
			try {
				if (currMatchEvent.endCb) currMatchEvent.endCb.apply(currMatchEvent.element, arrArgs);
			} catch (e) {
				console.error(e);
			}
			a9os_core_main.component.style.cursor = "default";
		}

		mainDiv.boolMoveEvent.preDown = false;
		mainDiv.boolMoveEvent.initPosition = [];
		mainDiv.boolMoveEvent.moving = false;
		mainDiv.boolMoveEvent.matchEvents = [];
	});



	self.addEventListener(document, ["mousedown", "touchstart"], (event) => {
		//var mainDiv = event.currentTarget;
		var listenerName = event.listenerName;


		var arrMatchEvents = [];

		if (!self.component.arrMoveEvents) return;
		var arrComposedPath = event.composedPath();

		var eventPointer;
		if (listenerName == "mousedown") {
			eventPointer = event;
		} else if (listenerName == "touchstart") {
			eventPointer = event.touches[0];
		}


		itemEventSearch:
		for (var i = 0 ; i < arrComposedPath.length-2 ; i++) {
			for (var x = 0 ; x < self.component.arrMoveEvents.length ; x++) {
				var currComposedPath = arrComposedPath[i];
				var currMoveEvent = self.component.arrMoveEvents[x];
				if (currComposedPath == currMoveEvent.element) {
					var itemCoords = currMoveEvent.element.getBoundingClientRect();

					currMoveEvent.elementStartPosition = {
						itemCoords : itemCoords,
						x : eventPointer.clientX - itemCoords.left + currMoveEvent.element.scrollLeft,
						y : eventPointer.clientY - itemCoords.top + currMoveEvent.element.scrollTop,
					}

					if (listenerName == "touchstart") {
						currMoveEvent.startTimestamp = event.timeStamp;
					}

					arrMatchEvents.push(currMoveEvent);
					break itemEventSearch;
				}
			}
		}

		mainDiv.boolMoveEvent = {
			preDown : true,
			initPosition : [eventPointer.clientX, eventPointer.clientY],
			moving : false,
			matchEvents : arrMatchEvents
		};
	});

	self.addEventListener(document, ["mousemove", "touchmove"], (event) => {
		//var mainDiv = event.currentTarget;
		var listenerName = event.listenerName;

		if (!mainDiv.boolMoveEvent) return;

		var eventPointer;
		if (listenerName == "mousemove") {
			eventPointer = event;
		} else if (listenerName == "touchmove") {
			eventPointer = event.touches[0];
		}

		if (mainDiv.boolMoveEvent.preDown == false) return;

		if (Math.abs(mainDiv.boolMoveEvent.initPosition[0] - eventPointer.clientX) < 5 
		&&  Math.abs(mainDiv.boolMoveEvent.initPosition[1] - eventPointer.clientY) < 5) return;

		mainDiv.boolMoveEvent.moving = true;

		var moveInterface = {
			global : {
				start : {
					x : mainDiv.boolMoveEvent.initPosition[0],
					y : mainDiv.boolMoveEvent.initPosition[1],
				},
				x : eventPointer.clientX,
				y : eventPointer.clientY,
				percent : {
					x : eventPointer.clientX*100/mainDiv.offsetWidth,
					y : eventPointer.clientY*100/mainDiv.offsetHeight
				}
			},
			element : {
				start : {
					x : 0,
					y : 0,
					boundingClientRect : false
				},
				x : 0,
				y : 0,
				percent : {
					x : 0,
					y : 0
				}
			},
			zoom : 1,
			buttons : ((listenerName == "touchmove")?1:event.buttons),
			path : event.path,
			originalEvent : event
		};

		for (var i = 0 ; i < mainDiv.boolMoveEvent.matchEvents.length ; i++){
			var currMatchEvent = mainDiv.boolMoveEvent.matchEvents[i];

			if (listenerName == "touchmove") {
				if (event.timeStamp - currMatchEvent.startTimestamp < 200) {
					mainDiv.boolMoveEvent.matchEvents.splice(i, 1);
					continue;
				}
			}
			//self.removeMenu();

			//var itemCoords = currMatchEvent.element.getBoundingClientRect();
			var itemCoords = currMatchEvent.elementStartPosition.itemCoords;

			moveInterface.element.x = moveInterface.global.x - itemCoords.left + currMatchEvent.element.scrollLeft;
			moveInterface.element.y = moveInterface.global.y - itemCoords.top + currMatchEvent.element.scrollTop;

			moveInterface.element.percent.x = moveInterface.element.x*100/itemCoords.width;
			moveInterface.element.percent.y = moveInterface.element.y*100/itemCoords.height;

			moveInterface.element.start.x = currMatchEvent.elementStartPosition.x;
			moveInterface.element.start.y = currMatchEvent.elementStartPosition.y;
			moveInterface.element.start.boundingClientRect = itemCoords;

			var arrArgs = [moveInterface, currMatchEvent.element].concat(currMatchEvent.extraArgs);
			currMatchEvent.moveCb.apply(currMatchEvent.element, arrArgs);
			a9os_core_main.component.style.cursor = getComputedStyle(currMatchEvent.element, "").cursor;

			currMatchEvent.isMoved = true;

			if (listenerName == "touchmove") event.preventDefault();
		}

	});




}

a9os_core_main.moveEvent.add = (element, moveCb, endCb, ...extraArgs) => {
	
	if (!self.component.arrMoveEvents) self.component.arrMoveEvents = [];

	if (!NodeList.prototype.isPrototypeOf(element) && !Array.isArray(element)) {
		self.component.arrMoveEvents.push({
			element : element,
			moveCb : moveCb,
			endCb : endCb,
			extraArgs : extraArgs
		});
	} else {
		for (var i = 0 ; i < element.length ; i++){
			self.component.arrMoveEvents.push({
				element : element[i],
				moveCb : moveCb,
				endCb : endCb,
				extraArgs : extraArgs
			});
		}
	}

	//clear move events
	setTimeout(() => {
		for (var i = self.component.arrMoveEvents.length - 1; i >= 0; i--) {
			if (!self.component.contains(self.component.arrMoveEvents[i].element)) {
				self.component.arrMoveEvents.splice(i, 1);
			}
		}
	}, 0);//prevent clear from not appended element
	//////

}
a9os_core_main.moveEvent.remove = (element) => {
	if (!self.component.arrMoveEvents) return;

	for (var i = self.component.arrMoveEvents.length - 1; i >= 0; i--) {
		if (self.component.arrMoveEvents[i].element == element) {
			self.component.arrMoveEvents.splice(i, 1);
		}
	}
}





a9os_core_main.moveEvent.autoscroll = {};
a9os_core_main.moveEvent.autoscroll.add = (target, interface) => {
	//autoscroll - PASAR A FN PARA APLICAR A SELECCION DE ITEMS
	if (target.scrollHeight > target.clientHeight
	||  target.scrollWidth > target.clientWidth) {

		if (target.scrollTop > 0 && interface.element.y - target.scrollTop < 20) {
			if (!target.autoscrollIntervalY1) {
				target.autoscrollIntervalY1 = setInterval((target) => {
					target.scrollTop -= 20;
				}, 50, target);
			}
		} else {
			if (target.autoscrollIntervalY1) {
				clearInterval(target.autoscrollIntervalY1);	
				target.autoscrollIntervalY1 = false;
			}
		}

		if (target.scrollTop + target.clientHeight < target.scrollHeight
		&&  interface.element.y - target.scrollTop > target.clientHeight - 20) {
			if (!target.autoscrollIntervalY2) {
				target.autoscrollIntervalY2 = setInterval((target) => {
					target.scrollTop += 20;
				}, 50, target);
			}
		} else {
			if (target.autoscrollIntervalY2) {
				clearInterval(target.autoscrollIntervalY2);	
				target.autoscrollIntervalY2 = false;
			}
		}



		if (target.scrollLeft > 0 && interface.element.x - target.scrollLeft < 20) {
			if (!target.autoscrollIntervalX1) {
				target.autoscrollIntervalX1 = setInterval((target) => {
					target.scrollLeft -= 20;
				}, 50, target);
			}
		} else {
			if (target.autoscrollIntervalX1) {
				clearInterval(target.autoscrollIntervalX1);	
				target.autoscrollIntervalX1 = false;
			}
		}

		if (target.scrollLeft + target.clientWidth < target.scrollWidth
		&&  interface.element.x - target.scrollLeft > target.clientWidth - 20) {
			if (!target.autoscrollIntervalX2) {
				target.autoscrollIntervalX2 = setInterval((target) => {
					target.scrollLeft += 20;
				}, 50, target);
			}
		} else {
			if (target.autoscrollIntervalX2) {
				clearInterval(target.autoscrollIntervalX2);	
				target.autoscrollIntervalX2 = false;
			}
		}
	}
	///////
}

a9os_core_main.moveEvent.autoscroll.cancelAll = (target) => {
	clearInterval(target.autoscrollIntervalX1);
	clearInterval(target.autoscrollIntervalX2);
	clearInterval(target.autoscrollIntervalY1);
	clearInterval(target.autoscrollIntervalY2);
}



a9os_core_main.removeWindow = (wind0wOrCmp, preventBackWindowSelect) => {
	
	if (wind0wOrCmp.classList.contains("window")) {
		var wind0w = wind0wOrCmp;
	} else {
		var wind0w = wind0wOrCmp.goToParentClass("window");
	}

	wind0w.classList.add("close");
	if (window.a9os_core_taskbar_windowlist) a9os_core_taskbar_windowlist.item.remove(wind0w.getAttribute("data-taskbar-item-id"));

	setTimeout((wind0w, preventBackWindowSelect) => {
		if (wind0w.classList.contains("top-window")) {
			var prevWindow = self.getPrevWindow(wind0w);
			if (prevWindow && !preventBackWindowSelect) self.selectWindow(prevWindow);

			if (window.a9os_app_vf_desktop && !prevWindow) a9os_app_vf_desktop.selectDesktop();
		}

		self.mainDiv.removeChild(wind0w.goToParentClass("a9os_core_window"));
		core.collectComponentGarbage();
	}, 101, wind0w, preventBackWindowSelect); //< efecto de close
}

a9os_core_main.selectWindow = (componentOrWindow) => {
	
	if (!componentOrWindow) return false;

	if (componentOrWindow.classList.contains("window")) {
		var wind0w = componentOrWindow;
	} else {
		var wind0w = componentOrWindow.goToParentClass("window");
	}

	if (!wind0w) return;
	if (wind0w.classList.contains("top-window")) return;
	if (wind0w.classList.contains("minimized")) a9os_core_window.minimizeRestoreWindow(wind0w);
	core.link.push(wind0w.goToParentClass("component", "cmp").getAttribute("data-url"), {}, true);
	core.link.title(wind0w.querySelector("*[data-textcontent='title']").textContent);
	core.link.favicon(wind0w.querySelector(".window-bar .nav-icon img"));
	self.changeWindowScope(wind0w.querySelector(".main-content > cmp"));

	var arrWindows = self.component.querySelectorAll("cmp.a9os_core_window > .window");
	if (arrWindows.length == 0){
		return; 
	}
	wind0w.style.zIndex = arrWindows.length+1;
	var arrIndexes = [];
	for (var i = 0 ; i < arrWindows.length ; i++){
		arrWindows[i].classList.remove("top-window");
		if (window.a9os_core_taskbar_windowlist) a9os_core_taskbar_windowlist.item.unselect(arrWindows[i].getAttribute("data-taskbar-item-id"));
		arrIndexes.push({ i : parseInt(arrWindows[i].style.zIndex), elem : arrWindows[i] });
	}
	wind0w.classList.add("top-window");
	if (window.a9os_core_taskbar_windowlist) a9os_core_taskbar_windowlist.item.select(wind0w.getAttribute("data-taskbar-item-id"));
	arrIndexes.sort((a,b) => {
		return a.i - b.i;
	});

	for (var i = 0 ; i < arrIndexes.length ; i++){
		arrIndexes[i].elem.style.zIndex = i;
	}

	if (window.a9os_core_taskbar) a9os_core_taskbar.updateBackgroundColor();

	if (window.a9os_app_vf_desktop) {
		var vfFilesContainer = a9os_app_vf_desktop.component.querySelector(".vf-files-container");
		vfFilesContainer.classList.remove("selected");
		a9os_app_vf_desktop.component.classList.remove("selected");
	}
}

a9os_core_main.selectWindowByPath = (path, onlyOneWindows) => {
	var isOnlyOneClassname = "";
	if (onlyOneWindows) isOnlyOneClassname = ".only-one";
	var wind0w = document.querySelector("cmp.a9os_core_window[data-url='"+path+"'] .window"+isOnlyOneClassname);
	if (wind0w){
		self.selectWindow(wind0w);
		return wind0w;
	}

	return false;
}
a9os_core_main.getPrevWindow = (wind0w) => {
	
	var returnWindow;
	var arrWindows = self.component.querySelectorAll("cmp.a9os_core_window > .window:not(.minimized)");

	for (var i = 0 ; i < arrWindows.length ; i++) {
		var backWindow = arrWindows[i];
		if (parseInt(backWindow.style.zIndex) == parseInt(wind0w.style.zIndex)-1){
			returnWindow = backWindow;
			break;
		} else if (parseInt(backWindow.style.zIndex) != parseInt(wind0w.style.zIndex)) {
			returnWindow = backWindow;
		}
	}

	return returnWindow;
}

a9os_core_main.getMethodByName = (componentName, strMethod) => {
	
	if (!strMethod) return false;

	var arrComponentOverride = strMethod.split("]");
	if (arrComponentOverride[1]) {
		strMethod = arrComponentOverride[1].substring(1); // saco el primer "."

		var methodToCall = window[arrComponentOverride[0].substring(1)]||false;
		if (!methodToCall) return false;
	} else {
		var methodToCall = window[componentName]||false;
		if (!methodToCall) return false;
	}


	var arrAction = strMethod.split(".");
	for (var i = 0 ; i < arrAction.length ; i++){
		methodToCall = methodToCall[arrAction[i]]||false;
		if (!methodToCall) return false;
	}
	return methodToCall;
}

a9os_core_main.splitFilePath = (path) => {
	

	if (path.substr(-1) == "/") path = path.substr(0, path.length -1);
	var arrFilePath = path.split("/");
	var fileName = arrFilePath[arrFilePath.length - 1];

	arrFilePath.pop();
	var filePath = arrFilePath.join("/");

	return [filePath, fileName];
}

a9os_core_main.getFileExtension = (path) => {
	
	path = path.split("/");
	path = path[ path.length -1 ];
	var fileExtension = path.split(".");
	fileExtension.shift();
	fileExtension = fileExtension[ fileExtension.length-1 ]||"";
	fileExtension = fileExtension.trim();
	fileExtension = fileExtension.toUpperCase();

	return fileExtension;
}


a9os_core_main.isMobile = () => {
	
	return a9os_core_main.mainDiv.offsetWidth < 650;
}


a9os_core_main.windowCrossData = {};
a9os_core_main.windowCrossData.list = {};
a9os_core_main.windowCrossData.add = (obj) => {
	
	var randomId = core.getRandomId(self.windowCrossData.list, true);

	self.windowCrossData.list[randomId] = obj;
	
	return randomId;
}
a9os_core_main.windowCrossData.get = (id) => {
	var returnData = self.windowCrossData.list[id];
	if (!returnData) return false;
	delete self.windowCrossData.list[id];
	
	return returnData||false;
}



a9os_core_main.windowCrossCallback = {};
a9os_core_main.windowCrossCallback.list = {};

a9os_core_main.windowCrossCallback.add = (callbackFn, component) => {
	
	var randomId = core.getRandomId(self.windowCrossCallback.list, true);

	self.windowCrossCallback.list[randomId] = {
		callbackFn : callbackFn,
		component : component
	};

	return randomId;
}

a9os_core_main.windowCrossCallback.execute = (crossCallbackId, arrInjectArgs) => {
	
	if (!self.windowCrossCallback.list[crossCallbackId]){
		console.error("windowCrossCallback not found");
		return;
	}
	var arrArgs = self.windowCrossCallback.getArgs(crossCallbackId);
	var arrIndexedFinal = []; // fn.apply solo acepta arrays indexados no objetos por nombre. Cuidado con las posiciones
	for (var i in arrArgs) {
		if (arrInjectArgs && arrInjectArgs[i]) {	
			arrArgs[i] = arrInjectArgs[i];
		}

		arrIndexedFinal.push(arrArgs[i]);
	}
	
	core.callCallback({
		fn : self.windowCrossCallback.list[crossCallbackId].callbackFn.fn,
		args : arrArgs
	});
	self.windowCrossCallback.remove(crossCallbackId);
}

a9os_core_main.windowCrossCallback.remove = (crossCallbackId) => {
	
	if (self.windowCrossCallback.list[crossCallbackId]) {
		delete self.windowCrossCallback.list[crossCallbackId];
		return true;
	}

	return false;
}

a9os_core_main.windowCrossCallback.getArgs = (crossCallbackId, arrRequestedArgs) => {
	
	if (!self.windowCrossCallback.list[crossCallbackId]){
		console.error("windowCrossCallback not found");
		return;
	}

	var arrArgs = self.windowCrossCallback.list[crossCallbackId].callbackFn.args;
	var arrOutput = {};
	if (!arrRequestedArgs) {
		return arrArgs;
	}
	for (var i = 0 ; i < arrRequestedArgs.length ; i++){
		arrOutput[arrRequestedArgs[i]] = arrArgs[arrRequestedArgs[i]];
	}

	return arrOutput;
}

a9os_core_main.windowCrossCallback.observe = () => {
	function observe () {
		var arrCrossCallbacks = self.windowCrossCallback.list;

		for (var i in arrCrossCallbacks){
			if (typeof arrCrossCallbacks[i].component === "undefined") {
				// debugger;
				console.error("a9os_core_main - windowCrossCallback without component");
				//arrCrossCallbacks[i].__setContext({ component : false });
			}

			if (!arrCrossCallbacks[i].component || !a9os_core_main.component.contains(arrCrossCallbacks[i].component)){
				self.windowCrossCallback.remove(i);
			}
		} 

		if (Object.keys(arrCrossCallbacks).length == 0) return;
		//console.log("CCI", arrCrossCallbacks);
	}

	setInterval(observe, 3000);
}


a9os_core_main.cutCopyPaste = {};
a9os_core_main.cutCopyPaste.compare = (element) => {
	if (element.tagName.toUpperCase() == "TEXTAREA") return true;
	if (element.tagName.toUpperCase() == "INPUT") {
		var arrSelectableTypes = ["email", "number", "password", "search", "tel", "text", "url"];
		if (arrSelectableTypes.indexOf(element.type.toLowerCase()) != -1) return true;
	}
	if (element.contentEditable == "true") return true;
	
	return false;
}

a9os_core_main.kbShortcut = {};
a9os_core_main.kbShortcut.attach = () => {
	
	var keyDownFn = {
		fn : (event, wind0w) => {
			var arrAlterKeys = {
				ctrlKey : 17,
				shiftKey : 16,
				altKey : 18,
				metaKey : 91
			};

			arrPressedKeys = [];
			for (var key in arrAlterKeys) {
				if (event[key]) arrPressedKeys.push(arrAlterKeys[key]);
			}
			if (["control", "shift", "alt"].indexOf(event.key.toLowerCase()) == -1) {
				arrPressedKeys.push(event.which);

			}
			arrPressedKeys.sort((a,b) => { return a-b; });

			var arrShortcuts = wind0w.arrShortcuts;
			if (!arrShortcuts || arrShortcuts.length == 0) return;

			for (var i = 0 ; i < arrShortcuts.length ; i++) {
				var currShortcut = arrShortcuts[i];
				if (currShortcut.shortcut.join("-") == arrPressedKeys.join("-")) {
					event.stopPropagation();
					event.preventDefault();
					event.cancelBubble = true;
					event.returnValue = false;

					core.callCallback(currShortcut.action, {
						event : event
					});

					return false;
				}
			}
		},
		args : {
			event : false,
			wind0w : false
		}
	}

	a9os_core_main.addEventListener(document.body, "keydown", (event, body) => {

		var arrElementsWithShortcuts = a9os_core_main.component.querySelectorAll("*[data-has-shortcuts]");

		for (var i = 0 ; i < arrElementsWithShortcuts.length ; i++){
			var currElementWithShortcut = arrElementsWithShortcuts[i];
			if (currElementWithShortcut.classList.contains("window")) {
				if (currElementWithShortcut.classList.contains("top-window")) {
					core.callCallback(keyDownFn, {
						event : event,
						wind0w : currElementWithShortcut
					});
				}
			} else if (currElementWithShortcut.classList.contains("vf-files-container") && currElementWithShortcut.classList.contains("selected")) {
				//VF DSKTOP vfFilesContainer
				core.callCallback(keyDownFn, {
					event : event,
					wind0w : currElementWithShortcut
				});
			} else if (!currElementWithShortcut.classList.contains("vf-files-container")) {
				core.callCallback(keyDownFn, {
					event : event,
					wind0w : currElementWithShortcut
				});
			}
		}
	});
}
a9os_core_main.kbShortcut.add = (wind0w, shortcuts) => {
	
	for (var i = 0 ; i < shortcuts.length ; i++) {
		shortcuts[i].shortcut = self.kbShortcut.convertKeyCodes(shortcuts[i].shortcut);
	}

	wind0w.arrShortcuts = shortcuts;
	wind0w.setAttribute("data-has-shortcuts", true);
}

a9os_core_main.kbShortcut.convertKeyCodes = (arrShortcutKeys) => {
	var arrFinal = [];
	for (var i = 0 ; i < arrShortcutKeys.length ; i++){
		switch (arrShortcutKeys[i].toLowerCase()){
			case "ctrl" : 
				arrFinal.push(17);
				break;
			case "alt" : 
				arrFinal.push(18);
				break;
			case "shift" :
				arrFinal.push(16);
				break;
			case "meta" :
				arrFinal.push(91);
				break;
			case "rmeta" :
				arrFinal.push(92);
				break;
			case "esc" : 
				arrFinal.push(27);
				break;
			case "f1" : 
				arrFinal.push(112);
				break;
			case "f2" : 
				arrFinal.push(113);
				break;

			case "f3" : 
				arrFinal.push(114);
				break;

			case "f4" : 
				arrFinal.push(115);
				break;

			case "f5" : 
				arrFinal.push(116);
				break;

			case "f6" : 
				arrFinal.push(117);
				break;

			case "f7" : 
				arrFinal.push(118);
				break;

			case "f8" : 
				arrFinal.push(119);
				break;

			case "f9" : 
				arrFinal.push(120);
				break;

			case "f10" : 
				arrFinal.push(121);
				break;

			case "f11" : 
				arrFinal.push(122);
				break;

			case "f12" : 
				arrFinal.push(123);
				break;
			case "tab":
				arrFinal.push(9);
				break;
			case "esc":
				arrFinal.push(27);
				break;
			case "del":
				arrFinal.push(46);
				break;
			case "enter":
				arrFinal.push(13);
				break;
			case "meta":
				arrFinal.push(91);
				break;
			case "space":
				arrFinal.push(32);
				break;
			case "-":
				arrFinal.push(189);
				break;
			case "+":
				arrFinal.push(187);
				break;
			case "np-":
				arrFinal.push(109);
				break;
			case "np+":
				arrFinal.push(107);
				break;
			case "larrow":
				arrFinal.push(37);
				break;
			case "uarrow":
				arrFinal.push(38);
				break;
			case "rarrow":
				arrFinal.push(39);
				break;
			case "darrow":
				arrFinal.push(40);
				break;
			default :
				arrFinal.push(arrShortcutKeys[i].toUpperCase().charCodeAt(0));
				break;
		}
	}

	arrFinal.sort((a,b) => { return a-b; });

	return arrFinal;
}

a9os_core_main.colorLogic = {};
a9os_core_main.colorLogic.isLigther = (color) => {	
	var red = 0;
	var green = 0; 
	var blue = 0;
	if (color.indexOf("#") === 0) {
		red = parseInt(color.slice(1,3), 16);
		green = parseInt(color.slice(3,5), 16);
		blue = parseInt(color.slice(5,7), 16);
	} else if (color.indexOf("rgb") === 0) {
		var tmpPart = color.split(" ").join("");
		tmpPart = tmpPart.split("(")[1];
		tmpPart = tmpPart.split(")")[0];
		tmpPart = tmpPart.split(",");
		red = parseInt(tmpPart[0]);
		green = parseInt(tmpPart[1]);
		blue = parseInt(tmpPart[2]);
	}

	if ((red*0.2126)+(green*0.7152)+(blue*0.0722) > 255/1.77){ //anda mejor /wiki/Luma_(video)
	//if ((red*0.333)+(green*0.333)+(blue*0.333) > 255/2){
		return true;
	} else {
		return false;
	}
}

a9os_core_main.colorLogic.getAverageRGB = (imgEl, qtyColors) => {
	var qtyColors = qtyColors||1;
	qtyColors = parseInt(qtyColors);

	if (imgEl.naturalWidth > imgEl.naturalHeight) {
		var wQtyPx = qtyColors;
		var hQtyPx = 1;
	} else {
		var wQtyPx = 1;
		var hQtyPx = qtyColors;
	}

	var canvas = document.querySelector("#svg-converter");
	canvas = canvas.getContext("2d");
	canvas.clearRect(0, 0, wQtyPx, hQtyPx);
	canvas.drawImage(imgEl, 0, 0, wQtyPx, hQtyPx);
	var canvasData = canvas.getImageData(0, 0, wQtyPx, hQtyPx);

	var arrOutputColors = [];
	var arrHex = [];
	for (var c = 0 ; c < qtyColors*4 ; c++) {

		var currTmpVal = canvasData.data[c].toString(16);
		if (currTmpVal.length == 0) currTmpVal = "00";
		else if (currTmpVal.length == 1) currTmpVal = "0"+currTmpVal;
		arrHex.push(currTmpVal);

		if (c % 4 == 3) {
			arrOutputColors.push("#" + arrHex[0] + arrHex[1] + arrHex[2]);
			arrHex = [];
		}
	}

	if (arrOutputColors.length == 1) return arrOutputColors[0];
	return arrOutputColors;
}


a9os_core_main.testCompatSandbox = () => {
	a9os_core_compatsandbox.use("test/pruebajs", {
		fn : (compat) => {
			console.log("prueba suma", compat.window.prueba_suma(2,6));
			console.log("prueba return obj", compat.window.return_arg(self.component));
			compat.window.prueba_consolelog("PRUEBA CONSOLELOG desde iframe");
		}, 
		args : {
			compat : false
		}
	}, self.component);
}