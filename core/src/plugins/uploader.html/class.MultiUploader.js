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
 * Credit:
 *   Original class by Stickman -- http://www.the-stickman.com
 *      with thanks to:
 *      [for Safari fixes]
 *         Luis Torrefranca -- http://www.law.pitt.edu
 *         and
 *         Shawn Parker & John Pennypacker -- http://www.fuzzycoconut.com
 *      [for duplicate name bug]
 *         'neal'
 * 
 * Description : Class for simple Ajax/HTML multiple upload
 */
Class.create("MultiUploader", {
	
	
	initialize : function( formObject, mask ){

		formObject = $(formObject);
		// Main form
		this.mainForm = formObject;
		
		// Where to write the list
		this.list_target = formObject.select('div.uploadFilesList')[0];
		// How many elements?
		this.count = 0;
		// Current index
		this.id = 0;
        this.mask = false;
        mask = ajaxplorer.getPluginConfigs("uploader").get("ALLOWED_EXTENSIONS");
		if(mask && mask.trim() != ""){
			this.mask = $A(mask.split(","));
            this.maskLabel = ajaxplorer.getPluginConfigs("uploader").get("ALLOWED_EXTENSIONS_READABLE");
		}

        this.max  = parseInt(ajaxplorer.getPluginConfigs("uploader").get("UPLOAD_MAX_NUMBER"));
        this.maxUploadSize  = parseInt(ajaxplorer.getPluginConfigs("uploader").get("UPLOAD_MAX_SIZE"));
		this.namesMaxLength = ajaxplorer.getPluginConfigs("ajaxplorer").get("NODENAME_MAX_LENGTH");
		
		this.crtContext = ajaxplorer.getUserSelection();
		this.addElement(formObject.select('.dialogFocus')[0]);
		formObject.insert(new Element('input', {
            type:'hidden',
            name:'dir',
            value:this.crtContext.getContextNode().getPath()
        }));
		formObject.insert(new Element('input', {
            type:'hidden',
            name:'secure_token',
            value:window.Connexion.SECURE_TOKEN
        }));
		
		this.currentFileUploading = null;
		this.nextToUpload = -1;
		$('hidden_forms').select("form").each(function(element){
			element.remove();
		});		
		$('hidden_frames').innerHTML = '<iframe name="hidden_iframe" id="hidden_iframe"></iframe>';
		
		
		// Clear list_target
		if(this.list_target.childNodes.length){
			$A(this.list_target.childNodes).each(function(node){
				this.removeChild(node);
			}.bind(this.list_target) );
		}
		if(formObject.select('input[type="file"]').length > 1){
			var index = 0;
			$(formObject).select('input[type="file"]').each(function(element){
				if(Prototype.Browser.Gecko) element.setStyle({left:'-100px'});				
				if(index > 0) element.remove();
				index++;
			});
		}
		
		// FIX IE DISPLAY BUG
		if(Prototype.Browser.IE){
			$('fileInputContainerDiv').insert($('uploadBrowseButton'));
			//$('fileInputContainerDiv').insert($('uploadSendButton'));
			$('uploadBrowseButton').show();
			//$('uploadSendButton').show();
		}
        if(Prototype.Browser.IE){
            modal.closeValidation = function(){
                $(document.body).insert($('uploadBrowseButton'));
                //$(document.body).insert($('uploadSendButton'));
                $('uploadBrowseButton').hide();
                //$('uploadSendButton').hide();
                return true;
            };
        }
		// ATTACH LISTENERS ON BUTTONS (once only, that for the "observerSet")
		this.sendButton = formObject.down('#uploadSendButton');
		if(this.sendButton.observerSet) return;
		var optionsButton = formObject.down('#uploadOptionsButton');
		var closeButton = formObject.down('#uploadCloseButton');
        if(formObject.down('#uploader_options_pane')){
            formObject.down('#uploader_options_pane').hide();
        }
        if(formObject.down('#clear_list_button')){
            formObject.down('#clear_list_button').hide();
        }
		this.sendButton.observerSet = true;
		this.sendButton.observe("click", function(){
			ajaxplorer.actionBar.multi_selector.submitMainForm();
		});
		optionsButton.observe("click", function(){
            var message = MessageHash[281] + '\n';
            message += '   '+ MessageHash[282] + ':' + roundSize(this.maxUploadSize, '') + '\n';
            message += '   '+ MessageHash[284] + ':' + this.max;
            alert(message);
		}.bind(this));
        if(closeButton){
            closeButton.observe("click", function(){
                hideLightBox();
            }.bind(this));
        }

	},
	
	/**
	 * Add a new file input element
	 */
	addElement : function( element ){

		// Make sure it's a file input element
		if( element.tagName == 'INPUT' && element.type == 'file' ){
			// Element name -- what number am I?
			element.name = 'userfile_' + this.id++;
			element.multi_index = this.id;
			element.id = element.name;
			$(element).addClassName("dialogFocus");
			if(Prototype.Browser.Gecko) $(element).setStyle({left:'-100px'});
			// Add reference to this object
			element.multi_selector = this;

			// What to do when a file is selected
			element.onchange = function(){

				// New file input
				var new_element = document.createElement( 'input' );
				new_element.type = 'file';
				new_element.name = 'toto';

				// Add new element
				this.parentNode.insertBefore( new_element, this );
				//this.multi_selector.mainForm.appendChild( element );

				// Apply 'update' to element
				this.multi_selector.addElement( new_element );

				// Update list
				this.multi_selector.addListRow( this );

				// Hide this: we can't use display:none because Safari doesn't like it
				this.style.position = 'absolute';
				this.style.left = '-1000px';
				if(Prototype.Browser.IE){
					this.onchange = null;
				}

			};
			// If we've reached maximum number, disable input element
			if( this.max > 0 && this.count >= this.max ){
				element.disabled = true;
			}else{
				element.disabled = false;
			}

			// File element counter
			this.count++;
			// Most recent element
			this.current_element = element;

		} else {
			// This can only be applied to file input elements!
			alert( 'Error: not a file input element' );
		}

	},

	/**
	 * Add a new row to the list of files
	 */
	addListRow : function( element ){
		
		if(this.mask){
			var ext = getFileExtension(element.value);
			if(!this.mask.include(ext)){
                alert(MessageHash[367] + this.mask.join(', ') + (this.maskLabel? " ("+ this.maskLabel +")":"" ) );
				return;
			}
		}		

		// Row div
		var new_row = document.createElement( 'div' );		

		// Delete button
		var new_row_button = document.createElement( 'img' );
		//new_row_button.appendChild(document.createTextNode('remove'));
		new_row_button.src = ajxpResourcesFolder+'/images/actions/22/editdelete.png';
		new_row_button.align = 'absmiddle';
		new_row_button.setAttribute("style", "border:0px;cursor:pointer;");

		// References
		new_row.element = element;
		new_row_button.element = element;
		new_row.multi_index = element.multi_index;		
		// Delete function
		new_row_button.onclick= function(){

			// Remove element from form
			this.element.parentNode.removeChild( this.parentNode.element );

			// Remove this row from the list
			this.parentNode.parentNode.removeChild( this.parentNode );

			// Decrement counter
			this.element.multi_selector.count--;

			// Re-enable input element (if it's disabled)
			this.element.multi_selector.current_element.disabled = false;

			// Appease Safari
			//    without it Safari wants to reload the browser window
			//    which nixes your already queued uploads
			return false;
		};

		// Set row value
		//new_row.innerHTML = element.value;


		var value = element.value;
		var basename = getBaseName(value);
		if(basename.length > this.namesMaxLength){
			alert(MessageHash[393].replace("%s", this.namesMaxLength));
		}
		
		var maxLength = 63;
		if(value.length > maxLength)
		{
			value = value.substr(0,20) + '[...]' + value.substr(value.length-(maxLength-20), value.length);
		} 
		
		// Add button
		new_row.appendChild( new_row_button );
		// Add Text
		new_row.appendChild(document.createTextNode(value));
		// Add it to the list
		this.list_target.appendChild( new_row );
        this.sendButton.removeClassName("disabled");
    },
	
	getFileNames : function(){
		
		var fileNames = $A();
		for(var i=0; i<this.list_target.childNodes.length;i++)
		{
			fileNames.push(this.list_target.childNodes[i].element.value);
		}
		return fileNames;
		
	},

	updateRowByIndex : function(multiIndex, state){
		var row;
		for(var i=0; i<this.list_target.childNodes.length;i++)
		{
			if(this.list_target.childNodes[i].element.multi_index == multiIndex)
			{
				row = this.list_target.childNodes[i];
				break;
			}
		}
		if(!row){
			//alert('Error : row "' + multiIndex + '" not found!');
			return;
		}
		var stateImg = $(row).select("img")[0];
		if(state == 'loading') stateImg.src = ajxpResourcesFolder+'/images/yellowled.png';
		else if(state == 'done') stateImg.src = ajxpResourcesFolder+'/images/greenled.png';
		else if(state == 'error') stateImg.src = ajxpResourcesFolder+'/images/redled.png';
	},
	
	
	submitMainForm : function(){

		this.currentFileUploading = null;
		this.nextToUpload = -1;
		var formsCount = 0;
		var i;
		for(i=0;i<this.id + 1;i++)
		{

			//if(!$('userfile_'+i)) continue;
			var newForm = this.mainForm.cloneNode(false);
			newForm.id = 'pendingform_'+formsCount;
			var addUserFile = false;
			var inputs = $(this.mainForm).select("input");
			for(var j=0;j<inputs.length;j++)
			{
				var element = inputs[j];
				if((element.type == 'file' && element.multi_index == i && element.value != '') || element.type=='hidden' || element.type=='submit'){
					//var nodeCopy = element.cloneNode(true);
					if(element.type == 'file') {
						addUserFile = true;
						newForm.multi_index = i;
						newForm.appendChild($(element));						
					}
					else{
						var nodeCopy = element.cloneNode(true);
						nodeCopy.name = element.name;
						nodeCopy.value = element.value;
						newForm.appendChild(nodeCopy);
					}
					
				}
			}
			if(addUserFile){				
				$('hidden_forms').appendChild(newForm);
				formsCount++;
			}
		}
		this.submitNext();		
	},

	submitNext : function(error)
	{
		this.nextToUpload ++;
		if(this.currentFileUploading){
			if(error)this.updateRowByIndex(this.currentFileUploading, 'error');
			else this.updateRowByIndex(this.currentFileUploading, 'done');
		}
		if(error && typeof(error) == "string") alert(error);
		var nextToSubmit = $('pendingform_'+this.nextToUpload);
		if(nextToSubmit)
		{

			this.currentFileUploading = nextToSubmit.multi_index;
			this.updateRowByIndex(this.currentFileUploading, 'loading');
			var crtValue = $(nextToSubmit).getElementsBySelector('input[type="file"]')[0].value;
            if(this.crtContext.fileNameExists(crtValue))
			{
				if(!confirm(MessageHash[124])){
					this.submitNext(true);
					return;
				}
			}
            document.fire("ajaxplorer:longtask_starting");
            $(nextToSubmit).submit();
		}
		else
		{
            document.fire("ajaxplorer:longtask_finished");
		}
		
	}
	
});