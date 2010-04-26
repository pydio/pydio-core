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
 * Description : Class for simple Ajax/HTML multiple download
 */
 Class.create("MultiDownloader", {
	
	initialize : function( list_target, downloadUrl ){

	// Where to write the list
	this.list_target = list_target;
	// How many elements?
	this.count = 0;
	// How many elements?
	this.id = 0;
	// Download Url
	this.downloadUrl = downloadUrl;

	},
	/**
	 * Add a new row to the list of files
	 */
	addListRow : function( fileName )
	{

		this.count ++;
		// Row div
		var new_row = new Element( 'div' );

		var new_row_button = new Element('a');
		new_row_button.href= this.downloadUrl + fileName;		
		new_row_button.insert('<img src="'+ajxpResourcesFolder+'/images/actions/16/download_manager.png" height="16" width="16" align="absmiddle" border="0"> '+getBaseName(fileName));

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
			gaTrackEvent("Data", "Download", fileName);
		};
		
		new_row.insert(new_row_button);
		
		// Add it to the list
		$(this.list_target).insert( new_row );
		
	},
	
	emptyList : function()
	{
		
	}

});