/**
 * @package info.ajaxplorer.plugins
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
 * Description : The image gallery manager.
 */
Class.create("PixlrEditor", AbstractEditor, {

	fullscreenMode: false,
	_minZoom : 10,
	_maxZoom : 500,
	
	initialize: function($super, oFormObject)
	{
		//this.editorOptions.floatingToolbar = true;
		$super(oFormObject);
		this.container = $(oFormObject).select('div[id="pixlrContainer"]')[0];
	},
	
	resize : function(size){
		this.element.fire("editor:resize", size);
	},
	
	save : function(origFName, pixlrUrl){
		var conn = new Connexion();
		conn.addParameter("get_action", "retrieve_pixlr_image");
		conn.addParameter("original_file", origFName);
		conn.addParameter("new_url", pixlrUrl);
		conn.onComplete = function(transp){
			hideLightBox(true);
		};
		conn.sendAsync();
	},
	
	open : function($super, userSelection)
	{
		$super(userSelection);		
		var fName = userSelection.getUniqueFileName();
		var src = "content.php?get_action=post_to_server&file=" + fName;
		var iFrame = new Element("iframe", {src:src, style:"width:"+this.container.getWidth()+"px;height:500px"});
		this.container.update(iFrame);
		var pe = new PeriodicalExecuter(function(){
			var href;
			try{
				href = iFrame.contentDocument.location.href;
			}catch(e){}
			if(href && href.indexOf('image=') > -1){
	        	pe.stop();
	        	this.save(fName, href);
			}
		}.bind(this) , 0.7);

		return;		
	},
	
	getPreview : function(ajxpNode){
		var img = new Element('img', {src:Diaporama.prototype.getThumbnailSource(ajxpNode), border:0});
		img.resizePreviewElement = function(dimensionObject){			
			var imgDim = {
				width:parseInt(ajxpNode.getMetadata().get("image_width")), 
				height:parseInt(ajxpNode.getMetadata().get("image_height"))
			};
			var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
			img.setStyle(styleObj);
		}
		return img;
	},
	
	getThumbnailSource : function(ajxpNode){
		return ajxpServerAccessPath+"?get_action=preview_data_proxy&get_thumb=true&file="+encodeURIComponent(ajxpNode.getPath());
	}
	
});