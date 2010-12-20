/**
 * @package info.ajaxplorer.js
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * Description : Class for simple XHR multiple upload, HTML5 only
 */
Class.create("XHRUploader", {
	
	
	initialize : function( formObject, mask ){

		formObject = $(formObject);
		// Main form
		this.mainForm = formObject;
		
		// Where to write the list
		this.listTarget = formObject.select('div.uploadFilesList')[0];
		// How many elements?
		this.count = 0;
		// Current index
		this.id = 0;
		// Is there a maximum?
		if(window.htmlMultiUploaderOptions && window.htmlMultiUploaderOptions['284']){
			this.max = parseInt(window.htmlMultiUploaderOptions['284']);
		}
		if(window.htmlMultiUploaderOptions && window.htmlMultiUploaderOptions['282']){
			this.maxUploadSize = parseInt(window.htmlMultiUploaderOptions['282']);
		}
		if(mask){
			this.mask = $A(mask);
		}
		this.crtContext = ajaxplorer.getUserSelection();
		
		this.clearList();
		
		// INITIALIZE GUI, IF NOT ALREADY!
		var sendButton = formObject.select('div[id="uploadSendButton"]')[0];
		if(sendButton.observerSet){
			this.totalProgressBar = this.mainForm.PROGRESSBAR;
			this.totalStrings = $('totalStrings');
			this.uploadedString = $('uploadedString');			
			this.optionPane = this.mainForm.down('#uploader_options_pane');
			this.optionPane.loadData();
			this.updateTotalData();
			if(window.UploaderDroppedFiles){
				var files = window.UploaderDroppedFiles;
				for(var i=0;i<files.length;i++){
					this.addListRow(files[i]);
				}
				if(this.optionPane.autoSendCheck.checked){
					this.submit();
				}
				window.UploaderDroppedFiles = null;
			}
			return;		
		}
		
		var optionsButton = formObject.select('div[id="uploadOptionsButton"]')[0];
		var closeButton = formObject.select('div[id="uploadCloseButton"]')[0];
		sendButton.observerSet = true;
		sendButton.observe("click", function(){
			ajaxplorer.actionBar.multi_selector.submit();
		});
		optionsButton.observe("click", function(){
			var optionPane = this.mainForm.down('#uploader_options_pane');
			var closeSpan = optionsButton.down('span');
			if(optionPane.visible()) {
				optionPane.hidePane();
				closeSpan.hide();
			}
			else {
				optionPane.showPane();
				closeSpan.show();
			}
		}.bind(this));
		closeButton.observe("click", function(){
			hideLightBox();
		}.bind(this));

		this.initElement(formObject.select('.dialogFocus')[0]);		
		
		var dropzone = this.listTarget;
		dropzone.addClassName('droparea');
		dropzone.addEventListener("dragover", function(event) {
				event.preventDefault();
		}, true);
		dropzone.addEventListener("dragenter", function(){
			dropzone.addClassName("dropareaHover");
		}, true);
		dropzone.addEventListener("dragleave", function(){
			dropzone.removeClassName("dropareaHover");
		}, true);
		
		dropzone.addEventListener("drop", function(event) {
			event.preventDefault();
			var files = event.dataTransfer.files;
			for(var i=0;i<files.length;i++){
				this.addListRow(files[i]);
			}
			if(this.optionPane.autoSendCheck.checked){
				this.submit();
			}
		}.bind(this) , true);
		
		
		this.mainForm.down('#uploadFilesListContainer').setAttribute("rowspan", "1");
		var totalDiv = new Element('div', {id:'total_files_list'});
		this.mainForm.down('#optClosButtonsContainer').insert({after:new Element('td', {style:'vertical-align:bottom'}).update(totalDiv)});
		totalDiv.insert('<img src="'+ajxpResourcesFolder+'/images/actions/22/trashcan_empty.png" class="fakeUploadButton fakeOptionButton" id="clear_list_button"\
			width="22" height="22" style="float:right;margin-top:3px;padding:4px;width:22px;" title="'+MessageHash[216]+'"/>\
			<span id="totalStrings">'+MessageHash[258]+' : 0 '+MessageHash[259]+' : 0Kb</span>\
			<div style="padding-top:3px;">\
			<div id="pgBar_total" style="width:154px; height: 4px;border: 1px solid #ccc;float:left;margin-top: 6px;"></div>\
			<span style="float:left;margin-left:10px;" id="uploadedString">'+MessageHash[256]+' : 0%</span>\
			</div>');
		var options = {
			animate		: false,									// Animate the progress? - default: true
			showText	: false,									// show text with percentage in next to the progressbar? - default : true
			width		: 154,										// Width of the progressbar - don't forget to adjust your image too!!!
			boxImage	: ajxpResourcesFolder+'/images/progress_box.gif',			// boxImage : image around the progress bar
			barImage	: ajxpResourcesFolder+'/images/progress_bar.gif',	// Image to use in the progressbar. Can be an array of images too.
			height		: 4										// Height of the progressbar - don't forget to adjust your image too!!!
		};
		$('clear_list_button').observe("click", function(e){
			ajaxplorer.actionBar.multi_selector.clearList();
			ajaxplorer.actionBar.multi_selector.updateTotalData();			
		});
		this.optionPane = this.createOptionsPane();
		this.optionPane.loadData();
		
		this.totalProgressBar = new JS_BRAMUS.jsProgressBar($('pgBar_total'), 0, options);
		this.mainForm.PROGRESSBAR = this.totalProgressBar;
		this.totalStrings = $('totalStrings');
		this.uploadedString = $('uploadedString');
		
		if(window.UploaderDroppedFiles){
			var files = window.UploaderDroppedFiles;
			for(var i=0;i<files.length;i++){
				this.addListRow(files[i]);
			}
			if(this.optionPane.autoSendCheck.checked){
				this.submit();
			}
			window.UploaderDroppedFiles = null;
		}
		
	},
	
	createOptionsPane : function(){
		var optionPane = new Element('div', {id:'uploader_options_pane'});
		optionPane.update('<div id="uploader_options_strings"></div>');
		optionPane.insert('<div id="uploader_options_checks">\
			<b>'+MessageHash[339]+'</b> <input type="radio" name="uploader_existing" id="uploader_existing_overwrite" value="overwrite"> '+MessageHash[263]+' \
			<input type="radio" name="uploader_existing" id="uploader_existing_rename" value="rename"> '+MessageHash[6]+' \
			<input type="radio" name="uploader_existing" id="uploader_existing_alert" value="alert"> '+MessageHash[340]+' <br>\
			<b>Options</b> <input type="checkbox" style="width:20px;" id="uploader_auto_send"> '+MessageHash[337]+'&nbsp; &nbsp; \
			<input type="checkbox" style="width:20px;" id="uploader_auto_close"> '+MessageHash[338]+'\
			</div>');
		optionPane.hide();
		optionPane.autoSendCheck = optionPane.down('#uploader_auto_send');
		optionPane.autoCloseCheck = optionPane.down('#uploader_auto_close');
		optionPane.optionsStrings = optionPane.down('#uploader_options_strings');
		optionPane.existingRadio = optionPane.select('input[name="uploader_existing"]');
		var totalPane = this.mainForm.down('#total_files_list');
		totalPane.insert({after:optionPane});
		optionPane.showPane = function(){
			totalPane.hide();optionPane.show();
			modal.refreshDialogAppearance();
		}
		optionPane.hidePane = function(){
			totalPane.show();optionPane.hide();
			modal.refreshDialogAppearance();
		}
		optionPane.autoSendCheck.observe("click", function(e){				
			var autoSendOpt = optionPane.autoSendCheck.checked;
			if(ajaxplorer.user){
				ajaxplorer.user.setPreference('upload_auto_send', (autoSendOpt?'true':'false'));
				ajaxplorer.user.savePreference('upload_auto_send');
			}else{
				 setAjxpCookie('upload_auto_send', (autoSendOpt?'true':'false'));
			}			
		});
		optionPane.autoCloseCheck.observe("click", function(e){				
			var autoCloseOpt = optionPane.autoCloseCheck.checked;
			if(ajaxplorer.user){
				ajaxplorer.user.setPreference('upload_auto_close', (autoCloseOpt?'true':'false'));
				ajaxplorer.user.savePreference('upload_auto_close');
			}else{
				 setAjxpCookie('upload_auto_close', (autoCloseOpt?'true':'false'));
			}			
		});
		optionPane.existingRadio.each(function(el){
			el.observe("click", function(e){
				var value = el.value;
				if(ajaxplorer.user){
					ajaxplorer.user.setPreference('upload_existing', value);
					ajaxplorer.user.savePreference('upload_existing');
				}else{
					 setAjxpCookie('upload_existing', value);
				}							
			});
		});
		optionPane.getExistingBehaviour = function(){
			var value;
			optionPane.existingRadio.each(function(el){
				if(el.checked) value = el.value;
			});
			return value;
		};
		optionPane.loadData = function(){
			if(window.htmlMultiUploaderOptions){
				var message = '<b>' + MessageHash[281] + '</b> ';
				for(var key in window.htmlMultiUploaderOptions){
					message += '&nbsp;&nbsp;'+ MessageHash[key] + ':' + roundSize(window.htmlMultiUploaderOptions[key], '');
				}
				optionPane.optionsStrings.update(message);
			}	
			var autoSendValue = false;			
			if(ajaxplorer.user && ajaxplorer.user.getPreference('upload_auto_send')){
				autoSendValue = ajaxplorer.user.getPreference('upload_auto_send');
				autoSendValue = (autoSendValue =="true" ? true:false);
			}else{
				var value = getAjxpCookie('upload_auto_send');
				autoSendValue = ((value && value == "true")?true:false);				
			}
			optionPane.autoSendCheck.checked = autoSendValue;
			
			var autoCloseValue = false;			
			if(ajaxplorer.user && ajaxplorer.user.getPreference('upload_auto_close')){
				autoCloseValue = ajaxplorer.user.getPreference('upload_auto_close');
				autoCloseValue = (autoCloseValue =="true" ? true:false);
			}else{
				var value = getAjxpCookie('upload_auto_close');
				autoCloseValue = ((value && value == "true")?true:false);				
			}
			optionPane.autoCloseCheck.checked = autoCloseValue;
			
			var existingValue = 'overwrite';
			if(ajaxplorer.user && ajaxplorer.user.getPreference('upload_existing')){
				existingValue = ajaxplorer.user.getPreference('upload_existing');
			}else if(getAjxpCookie('upload_existing')){
				var value = getAjxpCookie('upload_existing');				
			}
			optionPane.down('#uploader_existing_' + existingValue).checked = true;
			
		}
		return optionPane;
	},
	
	/**
	 * Add a new file input element
	 */
	initElement : function(  ){
		var element = this.mainForm.down('.dialogFocus');
		element.setAttribute("multiple", "true");
		if(Prototype.Browser.Gecko) element.setStyle({left:'-100px'});
		element.observe("change", function(event){
			var files = element.files;
			for(var i=0;i<files.length;i++){
				this.addListRow(files[i]);
			}
			this.clearElement();
		}.bind(this) );
	},
	
	clearElement : function (){
		this.mainForm.reset();
	},
	
	clearList : function(){
		if(this.listTarget.childNodes.length){
			$A(this.listTarget.childNodes).each(function(node){
				this.removeChild(node);
			}.bind(this.listTarget) );
		}		
	},

	/**
	 * Add a new row to the list of files
	 */
	addListRow : function( file ){

		if(file.size==0 && file.type == ""){
			// FOLDER!
			alert(MessageHash[336]);
			return;
		}
		if(this.maxUploadSize && file.fileSize > this.maxUploadSize){
			alert(MessageHash[211]);
			return;
		}
		if(this.max && this.listTarget.childNodes.length == this.max){
			alert(MessageHash[365].replace("%s", this.max));
			return;
		}
		if(this.mask){
			var ext = getFileExtension(file.fileName);
			if(!this.mask.include(ext)){
				alert(MessageHash[367] + this.mask.join(', '));
				return;
			}
		}
		// GET VALUE FROM FILE OBJECT
		var label = file.fileName;		
		var maxLength = 63;
		if(label.length > maxLength){
			label = label.substr(0,20) + '[...]' + label.substr(label.length-(maxLength-20), label.length);
		} 
		
		var item = new Element( 'div' );		
		// Delete button
		var delButton = new Element( 'img', {
			src:ajxpResourcesFolder+'/images/actions/16/editdelete.png',
			className : 'fakeUploadButton',
			align : 'absmiddle',
			style : '-moz-border-radius:3px;border-radius:3px;float:left;margin:1px 7px 2px 0px;padding:3px;width:16px;background-position:center top;',
			title : MessageHash[257]
		});
		delButton.observe("click", function(e){
			if(item.xhr){
				try{
					item.xhr.abort();
				}catch(e){}
			}
			item.remove();
			this.updateTotalData();
		}.bind(this));

		// Add button & text
		item.insert( delButton );
		item.insert( label );
		// Add it to the list
		this.listTarget.insert( item );
		
		var id = 'pgBar_' + (this.listTarget.childNodes.length + 1);
		this.createProgressBar(item, id);
		item.file = file;
		item.status = 'new';
		item.statusText.update('[new]');
		this.updateTotalData();
	},
	
	createProgressBar : function(item, id){
		var div = new Element('div', {id:id, style:'-moz-border-radius:2px;border-radius:2px;'});
		div.setStyle({
			border:'1px solid #ccc', 
			backgroundColor: 'white',
			marginTop: '7px',
			height:'4px',
			padding:0,
			width:'150px'
		});
		var percentText = new Element('span', {style:"float:right;display:block;width:30px;text-align:center;"});
		var statusText = new Element('span', {style:"float:right;display:block;width:90px;overflow:hidden;text-align:right;"});
		var container = new Element('div', {style:'border:medium none;margin-top:-5px;padding:0;width:280px;color: #777;'});
		container.insert(statusText);		
		container.insert(percentText);		
		container.insert(div);
		item.insert(container);
		item.percentText = percentText;
		var options = {
			animate		: false,
			showText	: false,
			width		: 154,
			boxImage	: ajxpResourcesFolder+'/images/progress_box.gif',
			barImage	: ajxpResourcesFolder+'/images/progress_bar.gif',
			height		: 4
		};
		item.pgBar = new JS_BRAMUS.jsProgressBar(div, 0, options);
		item.statusText = statusText;
	},
	
	updateTotalData : function(){
		var count = 0;
		var size = 0;
		var uploaded = 0;
		var percentage = 0;
		if(this.listTarget.childNodes.length){
			$A(this.listTarget.childNodes).each(function(item){
				if(item.status == 'new'){
					size += item.file.fileSize;
					count ++;
				}else if(item.status == 'loading'){
					size += item.file.fileSize;
					count ++;
					uploaded += item.bytesLoaded;
				}else if(item.status == 'loaded'){
					size += item.file.fileSize;
					count ++;
					uploaded += item.file.fileSize;
				}			
			});
		}
		if(size){
			var percentage = Math.round(100*uploaded/size);
		}
		this.totalProgressBar.setPercentage(percentage, true);
		this.totalStrings.update(MessageHash[258]+' ' + count + ' '+MessageHash[259]+' ' +roundSize(size, 'b'));
		this.uploadedString.update(MessageHash[256]+' : ' + percentage + '%');
		
	},
	
	submit : function(){
		this.submitNext();
	},
	
	submitNext : function(){

		if(item = this.getNextItem()){
			this.sendFile(item);
		}else{
			ajaxplorer.fireContextRefresh();
			if(this.optionPane.autoCloseCheck.checked){
				hideLightBox(true);
			}
		}

	},
	
	getNextItem : function(){
		for(var i=0;i<this.listTarget.childNodes.length;i++){
			if(this.listTarget.childNodes[i].status == 'new'){
				return this.listTarget.childNodes[i];
			}
		}
		return false;
	},
	
	sendFile : function(item){
		
    	item.status = 'loading';
    	item.statusText.update('[loading]');
		
    	var auto_rename = false;
		if(this.crtContext.fileNameExists(item.file.fileName))
		{
			var behaviour = this.optionPane.getExistingBehaviour();
			if(behaviour == 'rename'){
				auto_rename = true;
			}else if(behaviour == 'alert'){
				if(!confirm(MessageHash[124])){
					item.remove();
					return;
				}
			}else{
				// 'overwrite' : do nothing!
			}
		}

        var xhr = new XMLHttpRequest;
		var upload = xhr.upload;
		upload.addEventListener("progress", function(e){
			if (e.lengthComputable) {  
				var percentage = Math.round((e.loaded * 100) / e.total);  
				item.percentText.innerHTML = percentage + '%';
	        	item.pgBar.setPercentage(percentage);
	        	item.bytesLoaded = e.loaded;
	        	this.updateTotalData();
			}
		}.bind(this), false);
		
        upload.onload  = function(rpe){
            setTimeout(function(){
                if(xhr.readyState === 4){
                	item.pgBar.setPercentage(100);
                	item.status = 'loaded';
                	item.statusText.update('[loaded]');
                }
                if(xhr.responseText && xhr.responseText != 'OK'){
                	alert(xhr.responseText);
		        	item.status = 'error';
		        	item.statusText.update('[error]');
                }
                this.updateTotalData();
                this.submitNext();
            }.bind(this), 15);
        }.bind(this);
        
        upload.onerror = function(){
        	item.status = 'error';
        	item.statusText.update('[error]');        	
        };
        
        var url = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=upload&xhr_uploader=true&dir="+encodeURIComponent(this.crtContext.getContextNode().getPath());
        if(auto_rename){
        	url += '&auto_rename=true';
        }
        
        xhr.open("post", url, true);
        xhr.setRequestHeader("If-Modified-Since", "Mon, 26 Jul 1997 05:00:00 GMT");
        xhr.setRequestHeader("Cache-Control", "no-cache");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("X-File-Name", item.file.fileName);
        xhr.setRequestHeader("X-File-Size", item.file.fileSize);
        //xhr.setRequestHeader("Content-Type", "multipart/form-data");
        xhr.send(item.file);		
        
        item.xhr = xhr;
	},
	
	sendFileMultipart : function(item){
		
		var file = item.file;
		var fileName = file.name;  
		var fileSize = file.size;  
		var reader = new FileReader();
		
	
		//var fileData = file.getAsBinary(); // works on TEXT data ONLY.  
		
		          
		reader.onload = function(){

			var fileData = reader.result;

			var boundary = "xxxxxxxxx";  
			var uri = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=upload&dir="+encodeURIComponent(this.crtContext.getContextNode().getPath());
			  
			var xhr = new XMLHttpRequest();  
			  
			xhr.open("POST", uri, true);  
			xhr.setRequestHeader("Content-Type", "multipart/form-data, boundary="+boundary); // simulate a file MIME POST request.  
			xhr.setRequestHeader("Content-Length", fileSize);  
			  
			xhr.upload.addEventListener("progress", function(e){
				if (e.lengthComputable) {  
					var percentage = Math.round((e.loaded * 100) / e.total);  
		        	item.pgBar.setPercentage(percentage);
				}
			}, false);
			
			xhr.onreadystatechange = function() {  
				if (xhr.readyState == 4) {
					item.pgBar.setPercentage(100);
					item.status = 'loaded';
					item.statusText.update('[loaded]');
	
					if ((xhr.status >= 200 && xhr.status <= 200) || xhr.status == 304) {
						if (xhr.responseText != "") {
							alert(xhr.responseText); // display response.
						}
					}
				}
			}
			  
			var body = "--" + boundary + "\r\n";  
			body += "Content-Disposition: form-data; name='userfile_0'; filename='" + fileName + "'\r\n";  
			body += "Content-Type: application/octet-stream\r\n\r\n";  
			body += fileData + "\r\n";  
			body += "--" + boundary + "--";  
			  
			xhr.sendAsBinary(body);  

		}.bind(this);
		
		reader.readAsBinaryString(file);
		
		return true;  		
		
	}
	
});