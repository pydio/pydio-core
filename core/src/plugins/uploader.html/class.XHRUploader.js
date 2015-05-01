/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 * Description : Class for simple XHR multiple upload, HTML5 only
 */
Class.create("XHRUploader", {
	
	_globalConfigs:null,
    listTarget : null,
    mainForm: null,
    id : null,
    rowAsProgressBar: false,
    dataModel: null,
    contextNode: null,
    currentBackgroundPanel: null,
    // Copy get/setUserPrefs from AjxpPane
    getUserPreference: AjxpPane.prototype.getUserPreference,
    setUserPreference: AjxpPane.prototype.setUserPreference,


	initialize : function( formObject, mask ){

        window.UploaderInstanceRunning = true;

		formObject = $(formObject);
		// Main form
		this.htmlElement = this.mainForm = formObject;
		
		// Where to write the list
		this.listTarget = formObject.down('div.uploadFilesList');

        this.rowAsProgressBar = this.listTarget.hasClassName('rowAsProgressBar');

		// How many elements?
		this.count = 0;
		// Current index
		this.id = 0;

        var confs = ajaxplorer.getPluginConfigs("uploader");
        if(confs) this._globalConfigs = confs;
        else this._globalConfigs = $H();

        this.max = parseInt(this._globalConfigs.get("UPLOAD_MAX_NUMBER")) || 0;
        this.maxUploadSize = this._globalConfigs.get("UPLOAD_MAX_SIZE") || 0;
		this.namesMaxLength = ajaxplorer.getPluginConfigs("ajaxplorer").get("NODENAME_MAX_LENGTH");
        this.mask = false;
        mask = this._globalConfigs.get("ALLOWED_EXTENSIONS");
		if(mask && mask.trim() != ""){
			this.mask = $A(mask.split(","));
            this.maskLabel = this._globalConfigs.get("ALLOWED_EXTENSIONS_READABLE");
		}
		this.dataModel = ajaxplorer.getContextHolder();
        this.contextNode = this.dataModel.getContextNode();

        if(window.UploaderDroppedTarget && window.UploaderDroppedTarget.ajxpNode){
            //console.log(this.contextNode);
            this.contextNode = window.UploaderDroppedTarget.ajxpNode;
        }

		this.clearList();
		
		// INITIALIZE GUI, IF NOT ALREADY!
		this.sendButton = formObject.down('#uploadSendButton');
        this.sendButton.addClassName("disabled");
        modal.setCloseValidation(function(){
            if(this.hasLoadingItem()){
                var panels = $$('div.backgroundPanel');
                var panel;
                if(!panels.length){
                    panel = new Element('div', {className:'backgroundPanel'});
                    ajxpBootstrap.parameters.get("MAIN_ELEMENT").insert(panel);
                }else{
                    panel = panels[0];
                }
                this.attachToBackgroundPanel(panel);
            }else{
                window.UploaderInstanceRunning = false;
            }
            return true;
        }.bind(this));

		if(this.sendButton.observerSet){
            if(this.mainForm.PROGRESSBAR){
                this.totalProgressBar = this.mainForm.PROGRESSBAR;
            }
			this.totalStrings = $('totalStrings');
			this.uploadedString = $('uploadedString');			
			this.optionPane = this.mainForm.down('#uploader_options_pane');
			this.optionPane.loadData();
			this.updateTotalData();
		}else{
            this.initGUI();
        }

        if(window.UploaderDroppedFiles || window.UploaderDroppedItems){
             this.handleDropEventResults(window.UploaderDroppedItems, window.UploaderDroppedFiles);
             window.setTimeout(function(){
                 window.UploaderDroppedItems = window.UploaderDroppedFiles = null;
             }, 2000);
        }

	},

    attachToBackgroundPanel: function(panel){
        panel.show();
        panel.update('Upload running...');
        this.currentBackgroundPanel = panel;
    },

    handleDropEventResults: function(items, files){

        var isMac = navigator.platform.toUpperCase().indexOf("MAC") >= 0;
        if ( !isMac && items && items.length && (items[0].getAsEntry || items[0].webkitGetAsEntry)) {
            var callback = this.addListRow.bind(this);
            var error = (console ? console.log : function(err){window.alert(err); }) ;
            var length = items.length;
            for (var i = 0; i < length; i++) {
                var entry;
                if(items[i].kind && items[i].kind != 'file') continue;
                if(items[0].getAsEntry){
                    entry = items[i].getAsEntry();
                }else{
                    entry = items[i].webkitGetAsEntry();
                }
                if (entry.isFile) {
                    entry.file(function(File) {
                        if(File.size == 0) return;
                        callback(File);
                    }, error );
                } else if (entry.isDirectory) {
                    this.recurseDirectory(entry, function(fileEntry){
                        var relativePath = fileEntry.fullPath;
                        fileEntry.file(function(File) {
                            if(File.size == 0) return;
                            callback(File, relativePath);
                        }, error );
                    }, error );
                }
            }
        }else{
            for(var j=0;j<files.length;j++){
                this.addListRow(files[j]);
            }
        }
        this.createdDirs = $A();
        if(this.optionPane.autoSendCheck.checked){
             window.setTimeout(this.submit.bind(this), 1000);
        }
    },

    recurseDirectory: function(item, completeHandler, errorHandler) {

        var recurseDir = this.recurseDirectory.bind(this);
        var dirReader = item.createReader();
        var entries = [];

        var toArray = function(list){
            return Array.prototype.slice.call(list || [], 0);
        };

        // Call the reader.readEntries() until no more results are returned.
        var readEntries = function() {
            dirReader.readEntries (function(results) {
                if (!results.length) {

                    $A(entries).each(function(e){
                        if(e.isDirectory){
                            recurseDir(e, completeHandler, errorHandler);
                        }else{
                            completeHandler(e);
                        }
                    });
                } else {
                    entries = entries.concat(toArray(results));
                    readEntries();
                }
            }, errorHandler);
        };

        readEntries(); // Start reading dirs.

    },

    initGUI: function(){
        var optionsButton = this.mainForm.down('#uploadOptionsButton');
        var closeButton = this.mainForm.down('#uploadCloseButton');
        this.sendButton.observe("click", function(){
            if(!this.hasClassName("disabled")){
                ajaxplorer.actionBar.multi_selector.submit();
            }
        }.bind(this.sendButton) );
        this.sendButton.observerSet = true;
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
        if(closeButton){
            closeButton.observe("click", function(){
                if(this.hasLoadingItem()) return;
                window.UploaderInstanceRunning = false;
                hideLightBox();
            }.bind(this));
        }

        this.initElement();

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
            var items = event.dataTransfer.items || [];
            var files = event.dataTransfer.files;
            this.handleDropEventResults(items, files);
        }.bind(this) , true);


        if(this.mainForm.down('#uploadFilesListContainer')) {
            this.mainForm.down('#uploadFilesListContainer').setAttribute("rowspan", "1");
        }
        if(this.mainForm.down('#optClosButtonsContainer')){
            var totalDiv = new Element('div', {id:'total_files_list'});
            this.mainForm.down('#optClosButtonsContainer').insert({after:new Element('td', {style:'vertical-align:bottom'}).update(totalDiv)});
            totalDiv.insert('<img src="'+ajxpResourcesFolder+'/images/actions/22/trashcan_empty.png" class="fakeUploadButton fakeOptionButton" id="clear_list_button"\
            width="22" height="22" style="float:right;margin-top:3px;padding:4px;width:22px;" title="'+MessageHash[216]+'"/>\
            <span id="totalStrings">'+MessageHash[258]+' : 0 '+MessageHash[259]+' : 0Kb</span>\
            <div style="padding-top:3px;">\
            <div id="pgBar_total" style="width:154px; height: 4px;border: 1px solid #ccc;float:left;margin-top: 6px;"></div>\
            <span style="float:left;margin-left:10px;" id="uploadedString">'+MessageHash[256]+' : 0%</span>\
            </div>');
        }
        var options = {
            animate		: false,									// Animate the progress? - default: true
            showText	: false,									// show text with percentage in next to the progressbar? - default : true
            width		: 154,										// Width of the progressbar - don't forget to adjust your image too!!!
            boxImage	: ajxpResourcesFolder+'/images/progress_box.gif',			// boxImage : image around the progress bar
            barImage	: ajxpResourcesFolder+'/images/progress_bar.gif',	// Image to use in the progressbar. Can be an array of images too.
            height		: 4										// Height of the progressbar - don't forget to adjust your image too!!!
        };
        this.mainForm.down('#clear_list_button').observe("click", function(e){
            ajaxplorer.actionBar.multi_selector.clearList();
            ajaxplorer.actionBar.multi_selector.updateTotalData();
        });
        this.optionPane = this.createOptionsPane();
        this.optionPane.loadData();

        if(this.mainForm.down('#phBar_total')){
            this.totalProgressBar = new JS_BRAMUS.jsProgressBar($('pgBar_total'), 0, options);
            this.mainForm.PROGRESSBAR = this.totalProgressBar;
        }
        this.totalStrings = $('totalStrings');
        this.uploadedString = $('uploadedString');

    },

	createOptionsPane : function(){
        var optionPane = this.mainForm.down("#uploader_options_pane");
        var totalPane = this.mainForm.down('#total_files_list');
        if(!optionPane){
            optionPane = new Element('div', {id:'uploader_options_pane'});
            totalPane.insert({after:optionPane});
        }
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
		optionPane.showPane = function(){
			totalPane.hide();optionPane.show();
			modal.refreshDialogAppearance();
		};
		optionPane.hidePane = function(){
			totalPane.show();optionPane.hide();
			modal.refreshDialogAppearance();
		};
		optionPane.autoSendCheck.observe("click", function(e){				
			var autoSendOpt = optionPane.autoSendCheck.checked;
			if(ajaxplorer.user){
				this.setUserPreference('upload_auto_send', (autoSendOpt?'true':'false'));
			}else{
				 setAjxpCookie('upload_auto_send', (autoSendOpt?'true':'false'));
			}			
        }.bind(this));
		optionPane.autoCloseCheck.observe("click", function(e){				
			var autoCloseOpt = optionPane.autoCloseCheck.checked;
			if(ajaxplorer.user){
                this.setUserPreference('upload_auto_close', (autoCloseOpt?'true':'false'));
			}else{
				 setAjxpCookie('upload_auto_close', (autoCloseOpt?'true':'false'));
			}			
		}.bind(this));
		optionPane.existingRadio.each(function(el){
			el.observe("click", function(e){
				var value = el.value;
				if(ajaxplorer.user){
                    this.setUserPreference('upload_existing', value);
				}else{
					 setAjxpCookie('upload_existing', value);
				}							
			}.bind(this));
		}.bind(this));
		optionPane.getExistingBehaviour = function(){
			var value;
			optionPane.existingRadio.each(function(el){
				if(el.checked) value = el.value;
			});
			return value;
		};
		optionPane.loadData = function(){
            var value;
            var message = '<b>' + MessageHash[281] + '</b> ';
            message += '&nbsp;&nbsp;'+ MessageHash[282] + ':' + roundSize(this.maxUploadSize, '');
            message += '&nbsp;&nbsp;'+ MessageHash[284] + ':' + this.max;
            optionPane.optionsStrings.update(message);
			var autoSendValue = false;
			if(this.getUserPreference('upload_auto_send')){
				autoSendValue = this.getUserPreference('upload_auto_send');
				autoSendValue = (autoSendValue =="true" ? true:false);
            }else if(this._globalConfigs.get('DEFAULT_AUTO_START')){
                autoSendValue = this._globalConfigs.get('DEFAULT_AUTO_START');
			}else{
				value = getAjxpCookie('upload_auto_send');
				autoSendValue = ((value && value == "true")?true:false);				
			}
			optionPane.autoSendCheck.checked = autoSendValue;
			
			var autoCloseValue = false;			
			if(this.getUserPreference('upload_auto_close')){
				autoCloseValue = this.getUserPreference('upload_auto_close');
				autoCloseValue = (autoCloseValue =="true" ? true:false);
            }else if(this._globalConfigs.get('DEFAULT_AUTO_CLOSE')){
                autoCloseValue = this._globalConfigs.get('DEFAULT_AUTO_CLOSE');
			}else{
				value = getAjxpCookie('upload_auto_close');
				autoCloseValue = ((value && value == "true")?true:false);				
			}
			optionPane.autoCloseCheck.checked = autoCloseValue;
			
			var existingValue = 'overwrite';
			if(this.getUserPreference('upload_existing')){
				existingValue = this.getUserPreference('upload_existing');
            }else if(this._globalConfigs.get('DEFAULT_EXISTING')){
                existingValue = this._globalConfigs.get('DEFAULT_EXISTING');
			}else if(getAjxpCookie('upload_existing')){
				value = getAjxpCookie('upload_existing');
			}
			optionPane.down('#uploader_existing_' + existingValue).checked = true;
			
		}.bind(this);
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
            if(this.optionPane.autoSendCheck.checked){
                this.submit();
            }
			this.clearElement();
		}.bind(this) );
	},
	
	clearElement : function (){
		this.mainForm.reset();
		if(this.optionPane && this.optionPane.loadData){
			this.optionPane.loadData();
		}
	},
	
	clearList : function(){
		if(this.listTarget.childNodes.length){
			$A(this.listTarget.childNodes).each(function(node){
				this.removeChild(node);
			}.bind(this.listTarget) );
		}
        if(this.sendButton) this.sendButton.addClassName("disabled");
	},

    /*
    pathToIndent: function( item,  itemPath ){
        var length = itemPath.split("/").length - 1;
        if(!length) return;
        for(var i=0;i<length;i++){
            item.insert({top: '<span class="item-indent">&nbsp;</span>'});
        }
    },

    addFolderRow: function ( folderPath ){

        var row = new Element('div').update('<span class="icon-folder-close"></span> ' + getBaseName(folderPath));
        this.pathToIndent(row, folderPath);
        row.FOLDER = true;
        this.listTarget.insert(row);

    },
    */

	/**
	 * Add a new row to the list of files
	 */
	addListRow : function( file , relativePath){

        this.listTarget.removeClassName('dropareaHover');

        if(getBaseName(file.name)==".DS_Store"){
            return;
        }
		if(file.size==0 && file.type == ""){
			// FOLDER!
			alert(MessageHash[336]);
			return;
		}else if(!file.size && Prototype.Browser.WebKit && getBaseName(file.name).indexOf(".") !== 0){
			var res = confirm(MessageHash[395]);
			if(!res){
				return;
			}
		}
		if(this.maxUploadSize && file.size > this.maxUploadSize){
			alert(MessageHash[211]);
			return;
		}
		if(this.max && this.listTarget.childNodes.length == this.max){
			alert(MessageHash[365].replace("%s", this.max));
			return;
		}

		var basename = getBaseName(file.name);
		if(basename.length > this.namesMaxLength){
			alert(MessageHash[393].replace("%s", this.namesMaxLength));
		}
		
		
		if(this.mask){
			var ext = getFileExtension(file.name);
			if(!this.mask.include(ext)){
				alert(MessageHash[367] + this.mask.join(', ') + (this.maskLabel? " ("+ this.maskLabel +")":"" ) );
				return;
			}
		}
		// GET VALUE FROM FILE OBJECT
		var label = file.name;		
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
        delButton = new Element("span", {className:"icon-remove-sign"}).update(delButton);
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
		item.insert( label.stripTags() );
        if(relativePath){
            item.relativePath = relativePath;
            item.insert( '<span class="item_relative_path">'+getRepName(relativePath)+'</span>' );
        }

		// Add it to the list
		this.listTarget.insert( item );
		
		var id = 'pgBar_' + (this.listTarget.childNodes.length + 1);
        if(this.rowAsProgressBar){
            this.createInnerProgressBar(item, id);
        }else{
            this.createProgressBar(item, id);
        }
		item.file = file;
		item.updateStatus('new');
		this.updateTotalData();
        this.sendButton.removeClassName("disabled");
	},
	
	createProgressBar : function(item, id){
		var div = new Element('div', {id:id, style:'-moz-border-radius:2px;border-radius:2px;'});
		div.setStyle({
			border:'1px solid #ccc', 
			backgroundColor: 'white',
			marginTop: '7px',
			height:'4px',
			padding:0,
			width:'154px'
		});
		var percentText = new Element('span', {style:"float:right;display:block;width:30px;text-align:center;"});
		var statusText = new Element('span', {style:"float:right;display:block;width:66px;overflow:hidden;text-align:right;"});
		var container = new Element('div', {style:'border:none;padding:0;padding-right:5px;color: #777;'});
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
		item.updateProgress = function(computableEvent, percentage){
			if(percentage == null){
				percentage = Math.round((computableEvent.loaded * 100) / computableEvent.total);  
	        	this.bytesLoaded = computableEvent.loaded;				
			}
			if(!this.percentValue || this.percentValue != percentage){
				this.percentText.innerHTML = percentage + '%';
				this.pgBar.setPercentage(percentage);
			}
			this.percentValue = percentage;
		}.bind(item);
        var oThis = this;
		item.updateStatus = function(status){
			this.status = status;
            var messageIds = {
                "new" : 433,
                "loading":434,
                "loaded":435,
                "error":436
            };
            try{
                status = window.MessageHash[messageIds[status]];
            }catch(e){}
            if(oThis.currentBackgroundPanel){
                oThis.currentBackgroundPanel.update(item.file.name.stripTags() + ' ['+status+']');
            }
            this.statusText.innerHTML = "["+status+"]";
		}.bind(item);
	},
	
	createInnerProgressBar : function(item, id){
		var statusText = new Element('span', {className:"statusText"});
		var percentText = new Element('span', {className:"percentText"});
		item.insert(statusText);
		item.insert(percentText);
		item.statusText = statusText;
		item.percentText = percentText;
		item.updateProgress = function(computableEvent, percentage){
			if(percentage == null){
				percentage = Math.round((computableEvent.loaded * 100) / computableEvent.total);
	        	this.bytesLoaded = computableEvent.loaded;
			}
			if(!this.percentValue || this.percentValue != percentage){
				this.percentText.innerHTML = percentage + '%';
                this.setStyle({backgroundSize:percentage+'% 100%'});
			}
			this.percentValue = percentage;
		}.bind(item);
        var oThis = this;
		item.updateStatus = function(status){
			this.status = status;
            var messageIds = {
                "new" : 433,
                "loading":434,
                "loaded":435,
                "error":436
            };
            try{
                status = window.MessageHash[messageIds[status]];
            }catch(e){}
			this.statusText.innerHTML = "["+status+"]";
            this.statusText.removeClassName('new');
            this.statusText.removeClassName('loading');
            this.statusText.removeClassName('loaded');
            this.statusText.removeClassName('error');
            this.statusText.addClassName(this.status);
            if(oThis.currentBackgroundPanel){
                oThis.currentBackgroundPanel.update(item.file.name.stripTags() + ' ['+status+']');
            }
		}.bind(item);
	},

	updateTotalData : function(){
		var count = 0;
		var size = 0;
		var uploaded = 0;
		var percentage = 0;
		if(this.listTarget.childNodes.length){
			$A(this.listTarget.childNodes).each(function(item){
				if(item.status == 'new'){
					size += item.file.size;
					count ++;
				}else if(item.status == 'loading'){
					size += item.file.size;
					count ++;
					uploaded += item.bytesLoaded;
				}else if(item.status == 'loaded'){
					size += item.file.size;
					count ++;
					uploaded += item.file.size;
				}			
			});
		}else{
            if(this.sendButton) this.sendButton.addClassName("disabled");
        }
		if(size){
			percentage = Math.round(100*uploaded/size);
		}
        if(this.totalProgressBar){
            this.totalProgressBar.setPercentage(percentage, true);
        }
		if(this.totalStrings) this.totalStrings.update(MessageHash[258]+' ' + count + ' '+MessageHash[259]+' ' +roundSize(size, 'b'));
		if(this.uploadedString) this.uploadedString.update(MessageHash[256]+' : ' + percentage + '%');
		
	},
	
	submit : function(){
		this.submitNext();
	},
	
	submitNext : function(){

        var item;
        if(item = this.getNextItem()){
            document.fire("ajaxplorer:longtask_starting");
			this.sendFileMultipart(item);
		}else{
            if(this.hasLoadingItem()) return;
			//ajaxplorer.fireContextRefresh();
			if(this.optionPane.autoCloseCheck.checked || this.currentBackgroundPanel){
                window.UploaderInstanceRunning = false;
                if(this.currentBackgroundPanel){
                    this.currentBackgroundPanel.hide();
                }else{
                    hideLightBox(true);
                }
			}
            document.fire("ajaxplorer:longtask_finished");
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

    hasLoadingItem : function(){
        for(var i=0;i<this.listTarget.childNodes.length;i++){
            if(this.listTarget.childNodes[i].status == 'loading'){
                return true;
            }
        }
        return false;
    },
	
	initializeXHR : function(item, queryStringParam, forceDir){

        var currentDir = this.contextNode.getPath();
        if(forceDir) currentDir = forceDir;

		var xhr = new XMLHttpRequest();
		var uri = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=upload&xhr_uploader=true&dir="+encodeURIComponent(currentDir);
		if(queryStringParam){
			uri += '&' + queryStringParam;
		}

		var upload = xhr.upload;
		upload.addEventListener("progress", function(e){
			if (!e.lengthComputable) return;
			item.updateProgress(e);
        	this.updateTotalData();
		}.bind(this), false);
		xhr.onreadystatechange = function() {  
			if (xhr.readyState == 4) {
				item.updateProgress(null, 100);
				item.updateStatus('loaded');

                if (xhr.responseXML){
                    var result = ajaxplorer.actionBar.parseXmlMessage(xhr.responseXML);
                    if(!result) item.updateStatus("error");
                }else if (xhr.responseText && xhr.responseText != 'OK') {
					alert(xhr.responseText); // display response.
					item.updateStatus('error');
				}
                this.updateTotalData();
                this.submitNext();				
			}
		}.bind(this);        
        
        upload.onerror = function(){
			item.updateStatus('error');
        };		

		xhr.open("POST", uri, true);
        try {if(Prototype.Browser.IE10) xhr.responseType =  'msxml-document'; } catch(e){}
        return xhr;
		
	},
	
	sendFileMultipart : function(item){
    	var auto_rename = false;

        var currentDir = this.contextNode.getPath();
        if(item.relativePath){
            if(!this.createdDirs) this.createdDirs = $A();
            // Create the folder directly!
            var createConn = new Connexion();
            var dirPath = getRepName(item.relativePath);
            var parts = dirPath.split("/");
            var localDir = "";
            for(var i=0;i<parts.length;i++){
                localDir += "/" + parts[i];

                var fullPath = currentDir+localDir;
                if(this.createdDirs.indexOf(localDir) == -1){
                    item.down('span.item_relative_path').update('Creating '+localDir + '...');
                    createConn.setParameters(new Hash({
                        get_action: 'mkdir',
                        dir: getRepName(fullPath),
                        ignore_exists:true,
                        dirname:getBaseName(fullPath)
                    }));
                    createConn.sendSync();
                    item.down('span.item_relative_path').update(localDir);
                    this.createdDirs.push(localDir);
                }
            }
            currentDir = fullPath;
        }

        var parentNode = new AjxpNode(currentDir);
        var newNode = new AjxpNode(currentDir+"/"+getBaseName(item.file.name));
        if(item.file.size){
            newNode.getMetadata().set("filesize", item.file.size);
        }
        try{
            this.dataModel.applyCheckHook(newNode);
        }catch(e){
            item.updateStatus('error');
            this.submitNext();
            return;
        }

		var behaviour = this.optionPane.getExistingBehaviour();
		if(behaviour == 'rename'){
			auto_rename = true;
		}else if(behaviour == 'alert'){
			if(this.dataModel.fileNameExists(item.file.name, false, parentNode))
			{
				if(!confirm(MessageHash[124])){
					item.remove();
					item.submitNext();
					return;
				}
			}
		}else{
			// 'overwrite' : do nothing!
		}
		
		var xhr = this.initializeXHR(item, (auto_rename?"auto_rename=true":""), currentDir);
		var file = item.file;
        item.updateProgress(null, 0);
		item.updateStatus('loading');		
		if(window.FormData){
			this.sendFileUsingFormData(xhr, file);
		}else if(window.FileReader){
			var fileReader = new FileReader();
			fileReader.onload = function(e){
				this.xhrSendAsBinary(xhr, file.name, e.target.result, item)
			}.bind(this);
			fileReader.readAsBinaryString(file);
		}else if(file.getAsBinary){
			window.testFile = file;
			var data = file.getAsBinary();
			this.xhrSendAsBinary(xhr, file.name, data, item)
		}
	},
	
	sendFileUsingFormData : function(xhr, file){
        var formData = new FormData();
        formData.append("userfile_0", file, file.name);
        xhr.send(formData);
	},	

	xhrSendAsBinary : function(xhr, fileName, fileData, item, completeCallback){
		var boundary = '----MultiPartFormBoundary' + (new Date()).getTime();  
		xhr.setRequestHeader("Content-Type", "multipart/form-data, boundary="+boundary);  

		var body = "--" + boundary + "\r\n";  
		body += "Content-Disposition: form-data; name='userfile_0'; filename='" + unescape(encodeURIComponent(fileName)) + "'\r\n";  
		body += "Content-Type: application/octet-stream\r\n\r\n";  
		body += fileData + "\r\n";  
		body += "--" + boundary + "--\r\n";  

		xhr.sendAsBinary(body);  		
	},
	
	sendFileAsInputStream : function(item){
		
		item.updateStatus('loading');
		
    	var auto_rename = false;
		if(this.dataModel.fileNameExists(item.file.name))
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

        var xhr = new XMLHttpRequest();
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
        
		xhr.onreadystatechange = function() {  
			if (xhr.readyState == 4) {
				item.pgBar.setPercentage(100);
				item.status = 'loaded';
				item.statusText.update('[loaded]');

				if (xhr.responseText && xhr.responseText != 'OK') {
					alert(xhr.responseText); // display response.
		        	item.status = 'error';
		        	item.statusText.update('[error]');					
				}
                this.updateTotalData();
                this.submitNext();				
			}
		}.bind(this);
        
        
        upload.onerror = function(){
        	item.status = 'error';
        	item.statusText.update('[error]');        	
        };
        
        var url = ajxpBootstrap.parameters.get('ajxpServerAccess')+"&get_action=upload&xhr_uploader=true&input_stream=true&dir="+encodeURIComponent(this.contextNode.getPath());
        if(auto_rename){
        	url += '&auto_rename=true';
        }
        
        xhr.open("post", url, true);
        try {if(Prototype.Browser.IE10) xhr.responseType =  'msxml-document'; } catch(e){}
        xhr.setRequestHeader("If-Modified-Since", "Mon, 26 Jul 1997 05:00:00 GMT");
        xhr.setRequestHeader("Cache-Control", "no-cache");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("X-File-Name", item.file.name);
        xhr.setRequestHeader("X-File-Size", item.file.size);
        xhr.setRequestHeader("Content-Type", item.file.type);
        xhr.send(item.file);		
        
        item.xhr = xhr;
	},
	
	
	sendFileChunked : 
		(window.Blob? 
		function(item){
			
			var file = item.file;

			this.cursor = 0;
			this.fIndex = 0;
			this.chunkLength = 10 * 1024 * 1024;		
			this.item = item;		
			this.fileName = file.name;
			this.fileSize  = file.size;
			
			this.sendNextBlob();
			
			return true;  		
			
			
		}:function(item){
			
			var file = item.file;
			var fileName = file.name;  
			var reader = new FileReader();
			this.item = item;
			
			reader.onload = function(){

				item.statusText.update('[building query]');
				this.fileData = reader.result;
				this.fileName = fileName;
				this.cursor = 0;
				this.fIndex = 0;
				this.chunkLength = 5 * 1024 * 1024;
				this.sendNextChunk();

			}.bind(this);
			
			reader.onprogress = function(evt){
			    if (evt.lengthComputable) {
			      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
			      this.item.statusText.update('[reading '+percentLoaded+'%]');
			    }				
			}.bind(this);
			
			item.statusText.update('[reading data]');
			reader.readAsBinaryString(file);
			
			return true;  		
			
		}),	
		
	sendNextChunk : function(){
		if(this.fileData && this.cursor < this.fileData.length){
			var chunk = this.fileData.substring(this.cursor, this.cursor+this.chunkLength);
			this.cursor += chunk.length;
			var filename = this.fileName+"_part_"+this.fIndex;
			this.fIndex ++;
			this.xhrSendAsBinary(filename, chunk, chunk.length, this.item, this.sendNextChunk.bind(this));						
		}else{
			this.sendUnifyQuery(this.fileName, this.fIndex);
			if(this.fileData) delete this.fileData;
		}
	},
	
	sendNextBlob : function(){
		if(this.cursor < this.fileSize){
			var reader = new FileReader();
			var chunkLength = this.chunkLength;
			if(this.cursor + this.chunkLength > this.fileSize){
				chunkLength  = this.fileSize - this.cursor + 1;
			}
			var blob = this.item.file.slice(this.cursor, chunkLength);
			this.cursor += chunkLength;
			var filename = this.fileName+"_part_"+this.fIndex;
			this.fIndex ++;
			reader.onloadend = function(evt){
				if (evt.target.readyState == FileReader.DONE) {
					this.statusText.update('[building query]');
					this.fileData = evt.target.result;
					this.xhrSendAsBinary(
							filename, 
							evt.target.result, 
							evt.target.result.length, 
							this.item, 
							this.sendNextBlob.bind(this)
						);
				}
	
			}.bind(this);
			reader.onprogress = function(evt){
			    if (evt.lengthComputable) {
			      var percentLoaded = Math.round((evt.loaded / evt.total) * 100);
			      this.item.statusText.update('[reading '+percentLoaded+'%]');
			    }				
			}.bind(this);
			this.item.statusText.update('[reading data]');
			reader.readAsBinaryString(blob);
		}else{
			this.sendUnifyQuery(this.fileName, this.fIndex);
			delete this.item;
		}
	},
	
	sendUnifyQuery : function(fileName, lastIndex){
		var conn = new Connexion();
		conn.setParameters({
			"get_action" : "upload_chunks_unify",
			"file_name" : fileName,
			"dir" : this.contextNode.getPath()
		});
		for(var i=0;i<lastIndex;i++){
			conn.addParameter("chunk_"+i, fileName+"_part_"+i);
		}
		conn.onComplete = function(transport){
			ajaxplorer.fireContextRefresh();
		};
		conn.sendAsync();
	}
			
	
});
		
if(!XMLHttpRequest.prototype.sendAsBinary){		
	XMLHttpRequest.prototype.sendAsBinary = function(datastr) {
	       var bb = new BlobBuilder();
	       var data = new ArrayBuffer(1);
	       var ui8a = new Uint8Array(data, 0);
	       for (var i in datastr) {
	               if (datastr.hasOwnProperty(i)) {
	                       var chr = datastr[i];
	                       var charcode = chr.charCodeAt(0);
	                       ui8a[0] = (charcode & 0xff);
	                       bb.append(data);
	               }
	       }
	       var blob = bb.getBlob();
	       this.send(blob);
	};
}