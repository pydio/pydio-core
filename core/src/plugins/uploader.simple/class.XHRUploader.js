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
	
	
	initialize : function( formObject, max ){

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
		if( max ){
			this.max = max;
		} else {
			this.max = -1;
		};
		if(window.htmlMultiUploaderOptions && window.htmlMultiUploaderOptions['284']){
			this.max = parseInt(window.htmlMultiUploaderOptions['284']);
		}
		
		this.crtContext = ajaxplorer.getUserSelection();
		
		this.clearList();
		
		// INITIALIZE GUI, IF NOT ALREADY!
		var sendButton = formObject.select('div[id="uploadSendButton"]')[0];
		if(sendButton.observerSet){
			this.totalProgressBar = this.mainForm.PROGRESSBAR;
			this.totalStrings = $('totalStrings');
			this.uploadedString = $('uploadedString');			
			this.updateTotalData();
			if(window.UploaderDroppedFiles){
				var files = window.UploaderDroppedFiles;
				for(var i=0;i<files.length;i++){
					this.addListRow(files[i]);
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
			if(window.htmlMultiUploaderOptions){
				var message = MessageHash[281] + '\n';
				for(var key in window.htmlMultiUploaderOptions){
					message += '. '+ MessageHash[key] + ' : ' + window.htmlMultiUploaderOptions[key] + '\n';
				}
				alert(message);
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
		dropzone.addEventListener("drop", function(event) {
			event.preventDefault();
			var files = event.dataTransfer.files;
			for(var i=0;i<files.length;i++){
				this.addListRow(files[i]);
			}
		}.bind(this) , true);
		
		
		this.mainForm.down('#uploadFilesListContainer').setAttribute("rowspan", "1");
		var totalDiv = new Element('div', {id:'total_files_list'});
		this.mainForm.down('#optClosButtonsContainer').insert({after:new Element('td', {style:'vertical-align:bottom'}).update(totalDiv)});
		totalDiv.insert('<img src="'+ajxpResourcesFolder+'/images/actions/22/trashcan_empty.png" class="fakeUploadButton fakeOptionButton" id="clear_list_button"\
			width="22" height="22" style="float:right;margin-top:3px;padding:4px;width:22px;" title="Clear"/>\
			<span id="totalStrings">File Count : 0 Total Size : 0Kb</span>\
			<div style="padding-top:3px;">\
			<div id="pgBar_total" style="width:154px; height: 4px;border: 1px solid #ccc;float:left;margin-top: 6px;"></div>\
			<span style="float:left;margin-left:10px;" id="uploadedString">Uploaded : 0%</span>\
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
		this.totalProgressBar = new JS_BRAMUS.jsProgressBar($('pgBar_total'), 0, options);
		this.mainForm.PROGRESSBAR = this.totalProgressBar;
		this.totalStrings = $('totalStrings');
		this.uploadedString = $('uploadedString');
		
		if(window.UploaderDroppedFiles){
			var files = window.UploaderDroppedFiles;
			for(var i=0;i<files.length;i++){
				this.addListRow(files[i]);
			}
			window.UploaderDroppedFiles = null;
		}
		
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
			alert('Sorry, you cannot drop folders, drop only files!');
			return;
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
			title : 'Remove'
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
		//item.pgBar.setPercentage(50);
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
		
		var options = {
			animate		: false,									// Animate the progress? - default: true
			showText	: false,									// show text with percentage in next to the progressbar? - default : true
			width		: 154,										// Width of the progressbar - don't forget to adjust your image too!!!
			boxImage	: ajxpResourcesFolder+'/images/progress_box.gif',			// boxImage : image around the progress bar
			barImage	: ajxpResourcesFolder+'/images/progress_bar.gif',	// Image to use in the progressbar. Can be an array of images too.
			height		: 4,										// Height of the progressbar - don't forget to adjust your image too!!!
			onTick		: function(pbObj) { 
				percentText.update(pbObj.getPercentage() + '%');
				if(pbObj.getPercentage() == 100){
					return false;
				}
				return true ;
			}
		};
		item.pgBar = new JS_BRAMUS.jsProgressBar(div, 0, options);
		item.statusText = statusText;
	},
	
	getFileNames : function(){
		
		var fileNames = new Array();
		for(var i=0; i<this.listTarget.childNodes.length;i++)
		{
			fileNames.push(this.listTarget.childNodes[i].element.value);
		}
		return fileNames;
		
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
		this.totalStrings.update('File Count : ' + count + ' Total Size : ' +roundSize(size, 'b'));
		this.uploadedString.update('Uploaded : ' + percentage + '%');
		
	},
	
	submit : function(){
		this.submitNext();
	},
	
	submitNext : function(){

		if(item = this.getNextItem()){
			this.sendFile(item);
		}else{
			ajaxplorer.fireContextRefresh();
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
		
        var xhr = new XMLHttpRequest;
		var upload = xhr.upload;
		/*
        for(var
            xhr = new XMLHttpRequest,
            upload = xhr.upload,
            i = 0;
            i < length;
            i++
        )
            upload[split[i]] = (function(event){
                return  function(rpe){
                    if(isFunction(handler[event]))
                        handler[event].call(handler, rpe, xhr);
                };
            })(split[i]);
        */		
		upload.addEventListener("progress", function(e){
			if (e.lengthComputable) {  
				var percentage = Math.round((e.loaded * 100) / e.total);  
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
        
        xhr.open("post", "content.php?get_action=upload&xhr_uploader=true&dir="+this.crtContext.getContextNode().getPath(), true);
        xhr.setRequestHeader("If-Modified-Since", "Mon, 26 Jul 1997 05:00:00 GMT");
        xhr.setRequestHeader("Cache-Control", "no-cache");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("X-File-Name", item.file.fileName);
        xhr.setRequestHeader("X-File-Size", item.file.fileSize);
        xhr.setRequestHeader("Content-Type", "multipart/form-data");
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
			var uri = "content.php?get_action=upload&dir="+this.crtContext.getContextNode().getPath();
			  
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