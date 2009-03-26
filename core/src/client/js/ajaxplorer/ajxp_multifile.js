/**
 * Convert a single file-input element into a 'multiple' input list
 *
 * Usage:
 *
 *   1. Create a file input element (no name)
 *      eg. <input type="file" id="first_file_element">
 *
 *   2. Create a DIV for the output to be written to
 *      eg. <div id="files_list"></div>
 *
 *   3. Instantiate a MultiSelector object, passing in the DIV and an (optional) maximum number of files
 *      eg. var multi_selector = new MultiSelector( document.getElementById( 'files_list' ), 3 );
 *
 *   4. Add the first element
 *      eg. multi_selector.addElement( document.getElementById( 'first_file_element' ) );
 *
 *   5. That's it.
 *
 *   You might (will) want to play around with the addListRow() method to make the output prettier.
 *
 *   You might also want to change the line 
 *       element.name = 'file_' + this.count;
 *   ...to a naming convention that makes more sense to you.
 * 
 * Licence:
 *   Use this however/wherever you like, just don't blame me if it breaks anything.
 *
 * Credit:
 *   If you're nice, you'll leave this bit:
 *  
 *   Class by Stickman -- http://www.the-stickman.com
 *      with thanks to:
 *      [for Safari fixes]
 *         Luis Torrefranca -- http://www.law.pitt.edu
 *         and
 *         Shawn Parker & John Pennypacker -- http://www.fuzzycoconut.com
 *      [for duplicate name bug]
 *         'neal'
 */
function MultiSelector( formObject, list_target, max ){

	// Main form
	this.mainForm = formObject;
	
	// Where to write the list
	this.list_target = list_target;
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
	
	this.crtList = ajaxplorer.getFilesList();
	
	// Clear list_target
	if(list_target.childNodes.length){
		$A(list_target.childNodes).each(function(node){
			list_target.removeChild(node);
		});
	}
	if($(formObject).getElementsBySelector('input[type="file"]').length > 1){
		var index = 0;
		$(formObject).getElementsBySelector('input[type="file"]').each(function(element){
			if(index > 0) formObject.removeChild(element);
			index++;
		});
	}
	/**
	 * Add a new file input element
	 */
	this.addElement = function( element ){

		// Make sure it's a file input element
		if( element.tagName == 'INPUT' && element.type == 'file' ){
			// Element name -- what number am I?
			element.name = 'userfile_' + this.id++;
			element.multi_index = this.id;
			element.id = element.name;
			$(element).addClassName("dialogFocus");

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

	};

	/**
	 * Add a new row to the list of files
	 */
	this.addListRow = function( element ){

		// Row div
		var new_row = document.createElement( 'div' );		

		// Delete button
		var new_row_button = document.createElement( 'img' );
		//new_row_button.appendChild(document.createTextNode('remove'));
		new_row_button.src = ajxpResourcesFolder+'/images/recyclebin.png';
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
		
	};
	
	this.getFileNames = function(){
		
		var fileNames = new Array();
		for(var i=0; i<this.list_target.childNodes.length;i++)
		{
			fileNames.push(this.list_target.childNodes[i].element.value);
		}
		return fileNames;
		
	};

	this.updateRowByIndex = function(multiIndex, state){
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
	};
	
	
	this.submitMainForm = function(){

		currentFileUploading = null;
		nextToUpload = -1;
		$('hidden_forms').getElementsBySelector("form").each(function(element){
			element.remove();
		});
		var formsCount = 0;
		var i = 0;
		//alert('Submitting'+this.mainForm.action);
		//this.mainForm.submit();
		//return;
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
	};
	
	this.submitNext = function(error)
	{
		nextToUpload ++;
		if(currentFileUploading){
			if(error)this.updateRowByIndex(currentFileUploading, 'error');
			else this.updateRowByIndex(currentFileUploading, 'done');
		}
		if(error && typeof(error) == "string") alert(error);
		var nextToSubmit = $('pendingform_'+nextToUpload);
		if(nextToSubmit)
		{			
			currentFileUploading = nextToSubmit.multi_index;
			this.updateRowByIndex(currentFileUploading, 'loading');
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
			hideLightBox();
		}
		
	};
	
};
var currentFileUploading, nextToUpload;


function MultiDownloader( list_target, downloadUrl ){

	// Where to write the list
	this.list_target = list_target;
	// How many elements?
	this.count = 0;
	// How many elements?
	this.id = 0;
	// Download Url
	this.downloadUrl = downloadUrl;

	/**
	 * Add a new row to the list of files
	 */
	this.addListRow = function( fileName )
	{

		this.count ++;
		// Row div
		var new_row = new Element( 'div' );

		var new_row_button = new Element('a');
		new_row_button.href= this.downloadUrl + fileName;		
		new_row_button.insert('<img src="'+ajxpResourcesFolder+'/images/crystal/actions/16/download_manager.png" height="16" width="16" align="absmiddle" border="0"> '+getBaseName(fileName));

		new_row_button.multidownloader = this;
		
		// Delete function
		new_row_button.onclick= function()
		{
			// Remove this row from the list
			this.parentNode.parentNode.removeChild( this.parentNode );
			this.multidownloader.count --;
			if(this.multidownloader.count == 0 && this.multidownloader.triggerEnd)
			{
				this.multidownloader.triggerEnd();
			}
		};
		
		new_row.insert(new_row_button);
		
		// Add it to the list
		$(this.list_target).insert( new_row );
		
	};
	
	this.emptyList = function()
	{
		
	};

};
