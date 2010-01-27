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
MultiUploader = Class.create({
	
	
	initialize : function( formObject, max ){

		formObject = $(formObject);
		// Main form
		this.mainForm = formObject;
		
		// Where to write the list
		this.list_target = formObject.select('div.uploadFilesList')[0];
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
		
		this.crtList = ajaxplorer.getFilesList();		
		this.addElement(formObject.select('.dialogFocus')[0]);
		var rep = new Element('input', {
			type:'hidden', 
			name:'dir', 
			value:this.crtList.getCurrentRep()
		});
		formObject.insert(rep);		
		
		this.currentFileUploading = null;
		this.nextToUpload = -1;
		$('hidden_forms').getElementsBySelector("form").each(function(element){
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
		
		// ATTACH LISTENERS ON BUTTONS (once only, that for the "observerSet")
		var sendButton = formObject.select('div[id="uploadSendButton"]')[0];
		if(sendButton.observerSet) return;		
		var optionsButton = formObject.select('div[id="uploadOptionsButton"]')[0];
		var closeButton = formObject.select('div[id="uploadCloseButton"]')[0];
		sendButton.observerSet = true;
		sendButton.observe("click", function(){
			this.submitMainForm();
		}.bind(this));
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
			if( this.max != -1 && this.count >= this.max ){
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
		};

	},

	/**
	 * Add a new row to the list of files
	 */
	addListRow : function( element ){

		// Row div
		var new_row = document.createElement( 'div' );		

		// Delete button
		var new_row_button = document.createElement( 'img' );
		//new_row_button.appendChild(document.createTextNode('remove'));
		new_row_button.src = ajxpResourcesFolder+'/images/crystal/actions/22/editdelete.png';
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
		
	},
	
	getFileNames : function(){
		
		var fileNames = new Array();
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
			alert('Error : row "' + multiIndex + '" not found!');
			return;
		}
		var stateImg = $(row).getElementsBySelector("img")[0];
		if(state == 'loading') stateImg.src = ajxpResourcesFolder+'/images/crystal/yellowled.png';
		else if(state == 'done') stateImg.src = ajxpResourcesFolder+'/images/crystal/greenled.png';
		else if(state == 'error') stateImg.src = ajxpResourcesFolder+'/images/crystal/redled.png';
	},
	
	
	submitMainForm : function(){

		this.currentFileUploading = null;
		this.nextToUpload = -1;
		var formsCount = 0;
		var i = 0;
		for(i=0;i<this.id + 1;i++)
		{

			//if(!$('userfile_'+i)) continue;
			var newForm = this.mainForm.cloneNode(false);
			newForm.id = 'pendingform_'+formsCount;
			var addUserFile = false;
			var inputs = $(this.mainForm).getElementsBySelector("input");
			for(j=0;j<inputs.length;j++)
			{
				element = inputs[j];
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
			if(this.crtList.fileNameExists(crtValue))
			{
				overwrite = confirm(MessageHash[124]);
				if(!overwrite){
					this.submitNext(true);
					return;
				}
			}			
			$(nextToSubmit).submit();
		}
		else
		{
			//modal.close();
			//hideLightBox();
			this.crtList.reload();
		}
		
	}
	
});