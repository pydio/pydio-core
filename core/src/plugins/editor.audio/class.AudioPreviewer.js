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
 * Description : The Audio previewer : mp3 player only for the moment.
 */
Class.create("AudioPreviewer", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject){
	},
		
	getPreview : function(ajxpNode, rich){
		if(rich){
			var escapedFilename = escape(encodeURIComponent(ajxpNode.getPath()));
			
			var div = new Element('div', {id:"mp3_container", style:"text-align:center; padding:5px; margin-bottom: 5px;"});
			var content = '<object type="application/x-shockwave-flash"';
			content += 'data="'+ajxpResourcesFolder+'/flash/dewplayer-mini.swf?mp3=content.php?action=audio_proxy%26file='+escapedFilename+'&amp;bgcolor=FFFFFF&amp;showtime=1" width="150" height="20">';
			content += '<param name="wmode" value="transparent">';
			content += '<param name="movie" value="'+ajxpResourcesFolder+'/flash/dewplayer-mini.swf?mp3=content.php?action=audio_proxy%26file='+escapedFilename+'&amp;bgcolor=FFFFFF&amp;showtime=1" />';
			content += '</object>';
			div.update(content);
			div.resizePreviewElement = function(dimensionObject){
				// do nothing;
				/*
				var imgDim = {width:21, height:29};
				var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
				img.setStyle(styleObj);
				*/
			}
			return div;
		}else{
			return new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/crystal/mimes/ICON_SIZE',64)});
		}
	},
	
	getThumbnailSource : function(ajxpNode){
		return resolveImageSource(ajxpNode.getIcon(),'/images/crystal/mimes/ICON_SIZE',64);
	}
	
});