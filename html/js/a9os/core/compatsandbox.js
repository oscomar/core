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
a9os_core_compatsandbox.main = (data) => {
	self.basePath = data.basePath;
}

a9os_core_compatsandbox.compatList = {};
a9os_core_compatsandbox.use = (compatPath, postImportFn) => {
	if (!self.compatList[compatPath]) {
		var iframesContainer = self.component.querySelector(".compatsandbox-iframes");

		var newIframe = document.createElement("iframe");

		core.addEventListener(newIframe, "load", (event, newIframe, compatPath) => {
			self.resolveIframeLoad(compatPath);
		}, compatPath);

		newIframe.src = self.basePath+compatPath+"/sandbox.html";
		iframesContainer.appendChild(newIframe);

		self.compatList[compatPath] = {
			iframe : newIframe,
			postImportFn : [postImportFn],
			document : false,
			window : false
		};
	} else {
		if (!self.compatList[compatPath].document) {
			self.compatList[compatPath].postImportFn.push(postImportFn);
		} else {
			core.callCallback(postImportFn, {
				compat : self.compatList[compatPath]
			});
		}
	}
}

a9os_core_compatsandbox.resolveIframeLoad = (compatPath) => {
	var receptorObj = event.data;

	var currCompatList = self.compatList[compatPath];
	currCompatList.document = currCompatList.iframe.contentDocument;
	currCompatList.window = currCompatList.iframe.contentWindow;

	for (var i = 0 ; i < currCompatList.postImportFn.length ; i++) {
		core.callCallback(currCompatList.postImportFn[i], {
			compat : currCompatList
		});
	}
}