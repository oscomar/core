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
a9os_core_window.main = function (data) {
	
	var wind0w = self.component.querySelector(".window");
	if (!wind0w.querySelector(".main-content *")) { // si es una ventana vacía
		 a9os_core_main.removeWindow(wind0w);
		 return;	
	}

	wind0w.style.zIndex = a9os_core_main.component.querySelectorAll(".a9os_core_window").length;
	self.attachMoveEvent();
	self.attachResizeEvent();
	self.attachControlsEvent();
	self.attachSelectionEvent();
	self.attachDocumentResizeEvent();
	self.setUrl(data.fullPath);


	setTimeout((wind0w) => {
		a9os_core_main.selectWindow(wind0w);
		self.setWindowInitialPosition(wind0w);
		wind0w.classList.remove("close");
		self.checkResponsive(wind0w);

		var arrMaxLMaxRWind0ws = a9os_core_main.mainDiv.querySelectorAll(".window.maxl, .window.maxr");
		for (var i = 0 ; i < arrMaxLMaxRWind0ws.length ; i++) {
			self.checkResponsive(arrMaxLMaxRWind0ws[i]);
		}

	}, 1, wind0w);
}
a9os_core_window.setWindowInitialPosition = (wind0w) => {
	
	if (wind0w.style.top != "" || wind0w.style.left != "") return;
	if (!a9os_core_main.component.windowOpenPos){
		a9os_core_main.component.windowOpenPos = 1;
	}

	var openTop = 0;
	var openLeft = 0;
	var openWidth = wind0w.offsetWidth;
	var openHeight = wind0w.openHeight;
	switch (a9os_core_main.component.windowOpenPos) {
		case 1:
			openTop = 20;
			openLeft = 20;
			break;
		case 2:
			openTop = 20;
			openLeft = a9os_core_main.mainDiv.offsetWidth - wind0w.offsetWidth - 20;
			break;
		case 3:
			openTop = a9os_core_main.mainDiv.offsetHeight - wind0w.offsetHeight - 50;
			openLeft = a9os_core_main.mainDiv.offsetWidth - wind0w.offsetWidth - 20;
			break;
		case 4:
			openTop = a9os_core_main.mainDiv.offsetHeight - wind0w.offsetHeight - 50;
			openLeft = 20;
			break;
	}

	if (openTop + wind0w.offsetHeight + 20 > a9os_core_main.mainDiv.offsetHeight) {
		openTop = a9os_core_main.mainDiv.offsetHeight - wind0w.offsetHeight - 60;
	}

	if (openTop < 0) openTop = 20;
	if (openLeft < 0) openLeft = 20;

	//debugger;
	if (openWidth > a9os_core_main.mainDiv.offsetWidth) {

		openWidth = a9os_core_main.mainDiv.offsetWidth - 40;
		openLeft = 20;
	}
	if (openLeft + wind0w.offsetWidth > a9os_core_main.mainDiv.offsetWidth) {
		openLeft = 20;
	}

	wind0w.style.top = openTop;
	wind0w.style.left = openLeft;
	wind0w.style.width = openWidth;
	wind0w.style.height = openHeight;

	a9os_core_main.component.windowOpenPos++;
	if (a9os_core_main.component.windowOpenPos == 5) a9os_core_main.component.windowOpenPos = 1;
}

a9os_core_window.setUrl = (url) => {
//	debugger;
	a9os_core_window.component.setAttribute("data-url", url);
	if (url != window.location.pathname){
		core.link.push(url, {}, true);
	}
}

a9os_core_window.processWindowData = function (data, arrShortcuts, arrMenuCustomActions){
	if (!data.window){
		return false;
	}
	self.createTaskbarItem(data, arrMenuCustomActions);

	self.component.originalTitle = data.window.title;
	core.preProcess(self.component, data.window);

	var wind0w = self.component.querySelector(".window");

	if (data.window.width) wind0w.style.width = data.window.width;
	if (data.window.height) wind0w.style.height = data.window.height;

	setTimeout((data, wind0w) => {
		switch (data.window.position){
			case "center" : 
				wind0w.style.top = ((a9os_core_main.mainDiv.clientHeight - 50) / 2) - (wind0w.clientHeight / 2)+"px";
				wind0w.style.left = (a9os_core_main.mainDiv.clientWidth / 2) - (wind0w.clientWidth / 2)+"px";
				break;
			default:
				break;
		}
	}, 1, data, wind0w);

	if (data.window.resize && data.window.resize == "false"){
		wind0w.classList.add("no-resize");
	}
	if (data.window.fullscreen) {
		wind0w.classList.add("fullscreen");	
	}

	if (data.__onlyOne) wind0w.classList.add("only-one");

	self.checkResponsive(wind0w);

	self.setWindowColor(wind0w);

	var arrShortcuts = arrShortcuts||[];
	arrShortcuts.push({
		shortcut : ["shift", "F4"], 
		action : {
			fn : self.close,
			args : { }
		}
	});

	a9os_core_main.kbShortcut.add(wind0w, arrShortcuts);

	if (self.component.querySelector("iframe")) wind0w.classList.add("with-iframe");
}

a9os_core_window.setWindowColor = (wind0w) => {
	
	var windowBar = wind0w.querySelector(".window-bar");
	if (windowBar.hasAttribute("data-window-color")) {
		var color = windowBar.getAttribute("data-window-color");
		windowBar.style.backgroundColor = color;
		if (a9os_core_main.colorLogic.isLigther(color)) {
			wind0w.classList.add("dark-foreground");
		} else {
			wind0w.classList.remove("dark-foreground");
		}
		var menuBar = wind0w.querySelector(".menu-bar");
		if (menuBar){
			menuBar.style.backgroundColor = color;
		}

		if (color.indexOf("rgba") != -1) {
			wind0w.classList.add("transparent");
			wind0w.querySelector(".main-content").style.backgroundColor = color;
		} else {
			wind0w.classList.remove("transparent");
			wind0w.querySelector(".main-content").style.backgroundColor = null;
		}
	}
	if (window.a9os_core_taskbar) a9os_core_taskbar.updateBackgroundColor();
}
a9os_core_window.getWindowData = () => {
	
	return {
		title : self.component.querySelector(".window-bar .title").textContent,
		originalTitle : self.component.originalTitle
	};
}

a9os_core_window.updateWindowData = (windowData) => {
	
	if (windowData.title) {
		self.component.querySelector(".window-bar .title").textContent = windowData.title;

		var windowItemId = self.component.querySelector(".window").getAttribute("data-taskbar-item-id");
		var taskbarItem = a9os_core_taskbar_windowlist.component.querySelector(".item[data-taskbar-item-id='"+windowItemId+"']");
		if (taskbarItem) {
			taskbarItem.setAttribute("data-title", windowData.title);
			core.link.title(windowData.title);
		}

	}
}

a9os_core_window.attachMoveEvent = () => {
	var wind0w = self.component.querySelector(".window");

	var fromRestore = false;
	var restorePercent = 0;

	a9os_core_main.addEventListener(self.component.querySelectorAll(".window-bar, .menu-bar, .window-dragger"), "mousedown", tmpMoving, wind0w);
	a9os_core_main.addEventListener(self.component.querySelectorAll(".window-bar, .menu-bar, .window-dragger"), "touchstart", tmpMoving, wind0w);
	function tmpMoving(e,r,w) {
		w.classList.add("moving");
		setTimeout((w) => {
			w.classList.add("aftermove");
			setTimeout((w) => {
				w.classList.remove("moving");
				w.classList.remove("aftermove");
			}, 250, w);
		}, 1000, w);
	}

	var originalWind0wOffsetTop = false;
	var originalWind0wOffsetLeft = false;

	a9os_core_main.moveEvent.add(self.component.querySelectorAll(".window-bar, .menu-bar, .window-dragger"), (interface, windowBar, wind0w) => {

		// if (interface.buttons != 1) return ;

		if (!originalWind0wOffsetTop) originalWind0wOffsetTop = wind0w.offsetTop;
		if (!originalWind0wOffsetLeft) originalWind0wOffsetLeft = wind0w.offsetLeft;

		if (wind0w.classList.contains("maximized")){
			restorePercent = 100 * (interface.global.start.x - wind0w.offsetLeft) / wind0w.offsetWidth;

			wind0w.classList.remove("maximized");
			wind0w.classList.remove("max");
			wind0w.classList.remove("maxl");
			wind0w.classList.remove("maxr");
			fromRestore = true;

			setTimeout((wind0w) => {
				self.checkResponsive(wind0w);
			}, 200, wind0w);
		}
		if (interface.global.percent.x < 2 || interface.global.percent.y < 2 || interface.global.percent.x > 98) {
			wind0w.maxType = -1;
			if (interface.global.percent.y < 2){
				wind0w.maxType = 0;
			} else if (interface.global.percent.x < 2){
				wind0w.maxType = 1;
			} else if (interface.global.percent.x > 98) {
				wind0w.maxType = 2;
			}
			self.requestMaxSides(wind0w);
		} else {
			self.clearMaxSides(wind0w);
		}

		wind0w.classList.add("moving");
		if (!fromRestore) {
			wind0w.style.left = interface.global.x + originalWind0wOffsetLeft - interface.global.start.x;
		} else {
			wind0w.style.left = interface.global.x - wind0w.offsetWidth/100*restorePercent;
		}
		

		if (interface.global.y + originalWind0wOffsetTop - interface.global.start.y > 0) {
			wind0w.style.top = interface.global.y + originalWind0wOffsetTop - interface.global.start.y;
		}
		else wind0w.style.top = 0;

	}, (event, windowBar, wind0w) => {
		wind0w.classList.add("aftermove");
		setTimeout((wind0w) => {
			wind0w.classList.remove("moving");
			wind0w.classList.remove("aftermove");
		}, 250, wind0w);

		fromRestore = false;

		originalWind0wOffsetTop = false;
		originalWind0wOffsetLeft = false;

		if (wind0w.classList.contains("no-resize")){
			return;
		}
		if (wind0w.maxType == -1) return;
		a9os_core_window.maxmizeRestore(event, wind0w);


	}, wind0w);
}

a9os_core_window.requestMaxSides = (wind0w) => {
	
	if (wind0w.classList.contains("no-resize")){
		return;
	}
	var arrSides = ["max", "maxl", "maxr"];
	var currMaxClass = arrSides[wind0w.maxType];
	wind0w.querySelector(".max-preview").classList.add(currMaxClass);
}

a9os_core_window.clearMaxSides = (wind0w) => {
	
	wind0w.querySelector(".max-preview").classList.remove("max");
	wind0w.querySelector(".max-preview").classList.remove("maxl");
	wind0w.querySelector(".max-preview").classList.remove("maxr");
	wind0w.maxType = -1;
}


a9os_core_window.attachResizeEvent = () => {
		
	var wind0w = self.component.querySelector(".window");
	var arrResizers = wind0w.querySelectorAll(".draggers > div");


	var windowBar = wind0w.querySelectorAll(".window-bar, .window-dragger");
	a9os_core_main.addEventListener(windowBar, "dblclick", (event, windowBar, wind0w) => {
		if (wind0w.classList.contains("no-resize")) return;
		wind0w.classList.remove("moving");
		self.maxmizeRestore(event, wind0w);
	}, wind0w);

	a9os_core_main.addEventListener(arrResizers, "mousedown", (e,r,w) => {
		w.classList.add("resizing");
	}, wind0w);
	a9os_core_main.addEventListener(arrResizers, "touchstart", (e,r,w) => {
		w.classList.add("resizing");
	}, wind0w);

	a9os_core_main.addEventListener(arrResizers, "dblclick", (e,r,w) => {
		if (w.classList.contains("maxl") || w.classList.contains("maxr")) {
			self.resizeChangeMaxlMaxr(wind0w, -1);
		}
	}, wind0w);

	a9os_core_main.addEventListener(arrResizers, "mousedown", tmpResizing, wind0w);
	a9os_core_main.addEventListener(arrResizers, "touchstart", tmpResizing, wind0w);
	function tmpResizing(e,r,w) {
		w.classList.add("resizing");
		setTimeout((w) => {
			w.classList.remove("resizing");
		}, 1000, w);
	}

	wind0w.boolMobileChange = false;
	a9os_core_main.moveEvent.add(arrResizers, (interface, currResizer, wind0w) => {

		wind0w.classList.add("resizing");
		if (interface.buttons != 1) return;

		var newWidth = wind0w.offsetWidth;
		var newHeight = wind0w.offsetHeight;
		var newTop = wind0w.offsetTop;
		var newLeft = wind0w.offsetLeft;

		if (currResizer.classList.contains("tl")){//top left
			newWidth += newLeft - interface.global.x;
			newHeight += newTop - interface.global.y;
			newTop -= newTop - interface.global.y;
			newLeft -= newLeft - interface.global.x;
		} else if (currResizer.classList.contains("t")){//top
			newHeight += newTop - interface.global.y;
			newTop -= newTop - interface.global.y;
		} else if (currResizer.classList.contains("tr")){//top right
			newWidth += interface.global.x - newLeft - newWidth;
			newHeight += newTop - interface.global.y;
			newTop -= newTop - interface.global.y;
		} else if (currResizer.classList.contains("r")){//right
			newWidth += interface.global.x - newLeft - newWidth;
		} else if (currResizer.classList.contains("br")){//bottom right
			newWidth += interface.global.x - newLeft - newWidth;
			newHeight += interface.global.y - newTop - newHeight;
		} else if (currResizer.classList.contains("b")){//bottom
			newHeight += interface.global.y - newTop - newHeight;
		} else if (currResizer.classList.contains("bl")){//bottom left
			newWidth += newLeft - interface.global.x;
			newHeight += interface.global.y - newTop - newHeight;
			newLeft -= newLeft - interface.global.x;
		} else if (currResizer.classList.contains("l")){//left
			newWidth += newLeft - interface.global.x;
			newLeft -= newLeft - interface.global.x;
		}

		if (newTop < 0) newTop = 0;

		wind0w.classList.add("resizing");
		if (!wind0w.classList.contains("maximized") ) {		
			var oldTop = wind0w.style.top;
			var oldLeft = wind0w.style.left;

			wind0w.style.width = newWidth;
			wind0w.style.height = newHeight;
			wind0w.style.top = newTop;
			wind0w.style.left = newLeft;

			if (parseInt(wind0w.style.width) <= 150) {
				wind0w.style.width = 150;
				wind0w.style.left = oldLeft;
			}
			if (parseInt(wind0w.style.height) <= 32) {
				wind0w.style.height = 32;
				wind0w.style.top = oldTop;
			}

			self.checkResponsive(wind0w);
		}


		self.resizeChangeMaxlMaxr(wind0w, newWidth);


	}, (event, currResizer, wind0w) => {
		wind0w.classList.remove("resizing");

	}, wind0w);

}

a9os_core_window.resizeChangeMaxlMaxr = (wind0w, newWidth) => {
	
	if (!wind0w.classList.contains("maxl") && !wind0w.classList.contains("maxr")) return;

	var coreWindowStyle;
	for (var i = 0 ; i < document.styleSheets.length ; i++) {
		var currStyle = document.styleSheets[i];
		if (currStyle.ownerNode && currStyle.ownerNode.getAttribute("data-component-name") == "a9os_core_window") {
			coreWindowStyle = currStyle.rules;
		}
	}

	var maxLRule = false;
	var maxRRule = false;
	var maxPreviewMaxLRule = false;
	var maxPreviewMaxRRule = false;

	for (var i = 0 ; i < coreWindowStyle.length ; i++) {
		var currRule = coreWindowStyle[i];
		if (currRule.selectorText == ".a9os-main .window.maximized.maxl") maxLRule = currRule.style;
		if (currRule.selectorText == ".a9os-main .window.maximized.maxr") maxRRule = currRule.style;
		if (currRule.selectorText == ".a9os-main .window .max-preview.maxl") maxPreviewMaxLRule = currRule.style;
		if (currRule.selectorText == ".a9os-main .window .max-preview.maxr") maxPreviewMaxRRule = currRule.style;

		if (maxLRule && maxRRule && maxPreviewMaxLRule && maxPreviewMaxRRule) break;
	}

	if (newWidth == -1) {
		middlePercent = 50;
	} else {
		var middlePercent = 100 * newWidth / a9os_core_main.mainDiv.offsetWidth;
		if (middlePercent < 25) middlePercent = 25;
		if (middlePercent > 75) middlePercent = 75;
		if (middlePercent > 48 && middlePercent < 52) middlePercent = 50;
	}

	if (wind0w.classList.contains("maxr")) middlePercent = 100 - middlePercent;

	maxLRule.setProperty("width", middlePercent.toFixed(2)+"%", "important");
	
	maxRRule.setProperty("width", (100-middlePercent).toFixed(2)+"%", "important");
	maxRRule.setProperty("left", middlePercent.toFixed(2)+"%", "important");

	maxPreviewMaxLRule.setProperty("width", middlePercent.toFixed(2)+"%");
	maxPreviewMaxRRule.setProperty("width", (100-middlePercent).toFixed(2)+"%");

	var arrMaxLMaxRWind0ws = a9os_core_main.mainDiv.querySelectorAll(".window.maxl, .window.maxr");
	for (var i = 0 ; i < arrMaxLMaxRWind0ws.length ; i++) {
		self.checkResponsive(arrMaxLMaxRWind0ws[i]);
	}
}

a9os_core_window.attachControlsEvent = () => {
	
	a9os_core_main.addEventListener(self.component.querySelector(".window .window-bar .close-button"), "click", () => {
		self.close();
	});
	a9os_core_main.addEventListener(self.component.querySelector(".window .window-bar .max-button"), "click", self.maxmizeRestore);
	a9os_core_main.addEventListener(self.component.querySelector(".window .window-bar .min-button"), "click", self.minimize);
	self.component.querySelectorAll(".window .window-bar .right > *").forEach((elem) => { elem.addEventListener("mousedown", (event) => { event.stopPropagation(); })});
}

a9os_core_window.close = (event, preventBackWindowSelect) => {
	a9os_core_main.removeMenu();

	var baseComponent = self.component.querySelector(".main-content cmp");

	var componentName = baseComponent.getAttribute("data-component-name");
	if (window[componentName] 
	&& typeof window[componentName]._closeWindow !== "undefined"){
		a9os_core_main.selectWindow(baseComponent);

		if (!window[componentName]._closeWindow(event)) return false;
	}


	a9os_core_main.removeWindow(baseComponent, preventBackWindowSelect);

}

a9os_core_window.maxmizeRestore = (event, wind0wOrButton) => {
	
	a9os_core_main.removeMenu();

	var arrSides = ["max", "maxl", "maxr"];
	var wind0w = wind0wOrButton;
	if (!wind0w) return;
	if (!wind0w.classList.contains("window")){
		var wind0w = wind0wOrButton.goToParentClass("window");
	}
	if (!wind0w) return;
	var type = wind0w.maxType;
	if (wind0w.classList.contains("maximized")){
		wind0w.classList.remove("maximized");
		wind0w.classList.remove("max");
		wind0w.classList.remove("maxl");
		wind0w.classList.remove("maxr");
		self.checkResponsive(wind0w);
	} else {
		wind0w.classList.add("maximized");
		wind0w.classList.add(arrSides[type]);
		setTimeout((wind0w) => {
			self.checkResponsive(wind0w);
		}, 100, wind0w);
	}

	self.clearMaxSides(wind0w);
}

a9os_core_window.attachSelectionEvent = () => {
	
	var wind0w = self.component.querySelector(".window");

	a9os_core_main.addEventListener(wind0w, "mousedown", (event, wind0w) => {
		a9os_core_main.selectWindow(wind0w);
	});
}

a9os_core_window.createTaskbarItem = (data, arrMenuCustomActions) => {
	
	if (!window.a9os_core_taskbar_windowlist) return;

	if (!arrMenuCustomActions) arrMenuCustomActions = [];
	if (data.__onlyOne) arrMenuCustomActions.push("__onlyOne");

	var itemId = a9os_core_taskbar_windowlist.item.new(data.window, arrMenuCustomActions);
	a9os_core_window.component.querySelector(".window").setAttribute("data-taskbar-item-id", itemId);
}

a9os_core_window.minimize = (event) => {
	
	a9os_core_main.removeMenu();
	
	var wind0w = event.currentTarget.goToParentClass("window");
	a9os_core_window.minimizeWindow(wind0w);
}

a9os_core_window.minimizeWindow = (wind0w) => {
	
	var item = a9os_core_taskbar_windowlist.component.querySelector(".item[data-taskbar-item-id='"+wind0w.getAttribute("data-taskbar-item-id")+"']");
	a9os_core_window.setMinimizeCssVars(item, wind0w);
	wind0w.classList.add("minimized");
	item.classList.add("minimized");
	var prevWindow = a9os_core_main.getPrevWindow(wind0w);
	if (prevWindow) a9os_core_main.selectWindow(prevWindow);
	else a9os_app_vf_desktop.selectDesktop();
}

a9os_core_window.minimizeRestoreWindow = (wind0w) => {
	
	wind0w.classList.add("unminimized");
	wind0w.classList.remove("minimized");
	var item = a9os_core_taskbar_windowlist.component.querySelector(".item[data-taskbar-item-id='"+wind0w.getAttribute("data-taskbar-item-id")+"']");
	a9os_core_window.setMinimizeCssVars(item, wind0w);
	item.classList.remove("minimized");
	setTimeout((wind0w) => {
		wind0w.classList.remove("unminimized");
	}, 300, wind0w); // < efecto de minimizar
	a9os_core_main.selectWindow(wind0w);
	a9os_core_window.checkResponsive(wind0w);
}

a9os_core_window.setMinimizeCssVars = (item, wind0w) => {
	
	var newLeft = item.offsetLeft;
	var width = item.offsetWidth;
	var zoomFactor = (1/wind0w.offsetWidth*width);
	a9os_core_main.mainDiv.style.setProperty('--tmp-item-left', newLeft+"px");
	a9os_core_main.mainDiv.style.setProperty('--tmp-item-width', width+"px");
	a9os_core_main.mainDiv.style.setProperty('--tmp-item-zoom-factor', zoomFactor);
}

a9os_core_window.setMenuBar = (arrMenu) => {
	
	var wind0w = a9os_core_window.component.querySelector(".window");
	wind0w.classList.add("with-menu");

	for (var itemName in arrMenu){
		var currItemMenu = arrMenu[itemName];

		var newItem = document.createElement("div");
		newItem.classList.add("item");
		newItem.textContent = itemName;
		newItem.setAttribute("data-menu-bar", JSON.stringify(currItemMenu));
		a9os_core_main.addEventListener(newItem, "mousedown", (event, item) => {
			event.stopPropagation();
			var menuBar = newItem.parentElement;
			if (menuBar.getAttribute("data-active") == "false") {
				menuBar.setAttribute("data-active", "true");
				item.classList.add("active");
				a9os_core_main.removeMenu();
				a9os_core_main.showMenu(item, event, "data-menu-bar");
			} else {
				item.classList.remove("active");
				menuBar.setAttribute("data-active", "false");
				a9os_core_main.removeMenu();
			}
		});
		a9os_core_main.addEventListener(newItem, "mouseover", (event, item) => {
			if (item.parentElement.getAttribute("data-active") == "true" && !item.classList.contains("active")){
				item.parentElement.querySelectorAll(".item.active").forEach((i) => {
					i.classList.remove("active");
				});
				item.classList.add("active");
				a9os_core_main.removeMenu();
				a9os_core_main.showMenu(item, event, "data-menu-bar");
			}
		});

		wind0w.querySelector(".menu-bar").appendChild(newItem);
	}
}

a9os_core_window.unsetMenuBarUsed = () => {
	
	var arrMenuBars = a9os_core_main.mainDiv.querySelectorAll(".window .menu-bar");
	for (var i = 0 ; i < arrMenuBars.length ; i++){
		arrMenuBars[i].querySelectorAll(".item.active").forEach((i) => {
			i.classList.remove("active");
		});
		arrMenuBars[i].setAttribute("data-active", "false");
	}
}

a9os_core_window.checkResponsive = (wind0w) => {
	if (self.isMobile(wind0w)){
		wind0w.classList.add("is-mobile");
	} else {
		wind0w.classList.remove("is-mobile");
	}

	a9os_core_main.pushCustomEvent(wind0w, "wind0wrezise", {
		isMobile : self.isMobile(wind0w)
	});

	if (wind0w.boolMobileChange != self.isMobile(wind0w)) {
		wind0w.boolMobileChange = self.isMobile(wind0w);

		a9os_core_main.pushCustomEvent(wind0w, "wind0waltermobile", {
			isMobile : wind0w.boolMobileChange
		});
	}

}

a9os_core_window.isMobile = (wind0w) => {
	
	return wind0w.offsetWidth/wind0w.offsetHeight < 0.8 || wind0w.offsetWidth < 650;
}

a9os_core_window.attachDocumentResizeEvent = () => {
	
	var timeoutP = false;
	window.addEventListener("resize", (event) => {
		if (timeoutP) {
			clearTimeout(timeoutP);
		}
		timeoutP = setTimeout((event) => {
			var arrWindows = a9os_core_main.mainDiv.querySelectorAll("cmp.a9os_core_window > .window");
			for (var i = 0 ; i < arrWindows.length ; i++){
				var currWindow = arrWindows[i];
				a9os_core_window.checkResponsive(currWindow);
			}
			timeoutP = false;
		}, 200, event);
	});
}

a9os_core_window.setFullscreen = () => {
	
	var wind0w = self.component.querySelector(".window");
	wind0w.classList.add("fullscreen");
}
a9os_core_window.unsetFullscreen = () => {
	
	var wind0w = self.component.querySelector(".window");
	wind0w.classList.remove("fullscreen");
}

a9os_core_window.isFullscreen = () => {
	
	var wind0w = self.component.querySelector(".window");
	return wind0w.classList.contains("fullscreen");
}


a9os_core_window.highligthWindow = (hlWindow) => {
	
	var arrWindows = a9os_core_main.mainDiv.querySelectorAll(".window");
	for (var i = 0 ; i < arrWindows.length ; i++) {
		if (hlWindow && arrWindows[i] == hlWindow) {
			arrWindows[i].classList.remove("not-highligthed");
			continue;
		}
		arrWindows[i].classList.add("not-highligthed");
	}
}

a9os_core_window.removeHighligthWindows = () => {
	
	var arrWindows = a9os_core_main.mainDiv.querySelectorAll(".window");
	for (var i = 0 ; i < arrWindows.length ; i++) {
		arrWindows[i].classList.remove("not-highligthed");
	}
}