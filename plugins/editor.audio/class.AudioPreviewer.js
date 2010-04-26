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
			var escapedFilename = base64_encode(ajxpNode.getPath());
			var player = 'dewplayer-bubble.swf';
			var flashVars = 'mp3=content.php?get_action=audio_proxy%26file='+escapedFilename+'&amp;showtime=1';
			var playerWidth = '250';
			var playerHeight = '65';
			var containerStyle = 'padding:5 0px; margin-bottom: 5px;';
			if(!rich){
				player = 'dewplayer.swf';
				flashVars = 'mp3=content.php?get_action=audio_proxy%26file='+escapedFilename+'&amp;nopointer=1';
				playerWidth = '40';
				playerHeight = '20';
				containerStyle = '';				
			}
			var div = new Element('div', {id:"mp3_container", style:"text-align:center;"+containerStyle});
			var content = '<object type="application/x-shockwave-flash"';
			content += 'data="plugins/editor.audio/'+player+'" width="'+playerWidth+'" height="'+playerHeight+'" id="dewplayer" name="dewplayer">';
			content += '<param name="wmode" value="transparent"/>';
			content += '<param name="flashvars" value="'+flashVars+'"/>';
			content += '<param name="movie" value="plugins/editor.audio/'+player+'" />';
			content += '</object>';
			div.update(content);
			if(rich){
				div.resizePreviewElement = function(dimensionObject){};
			}else{
				div.resizePreviewElement = function(dimensionObject){					
					var imgDim = {width:800, height:400};
					var styleObj = fitRectangleToDimension(imgDim, dimensionObject);
					// fix width artificially
					if(div.getOffsetParent()){
						styleObj.width = div.getOffsetParent().getWidth()-2 + 'px';
					}
					div.setStyle(styleObj);
				};
			}			
			return div;
		}else{
			return new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64)});
		}
	},
	
	getThumbnailSource : function(ajxpNode){
		return resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
	},
	
	createFolderPlayer : function(ajxpNode){
		var template = new Template('<head><title>#{window_title}</title></head><body style="margin:0px; padding:10px;"><div style=\"font-family:Trebuchet MS, sans-serif; color:#79f; font-size:15px; font-weight:bold;\">#{window_title}</div><div style="font-family:Trebuchet MS, sans-serif; color:#666; font-size:10px; padding-bottom: 10px;">#{reading_folder}: #{current_folder}</div><object type="application/x-shockwave-flash" data="plugins/editor.audio/dewplayer-playlist.swf" width="240" height="200"><param name="wmode" value="transparent"><param name="movie" value="plugins/editor.audio/dewplayer-playlist.swf"/><param name="flashvars" value="xml=#{playlist_url}&amp;showtime=true&amp;autoreplay=true&amp;autoplay=true"/></object></body>');
		newWin = window.open('#', '_blank', 'width=260,height=270,directories=no,location=no,menubar=no,resizeable=yes,scrollbars=no,status=no,toolbar=no');
		try{
			var playlist_url = 'content.php?get_action=ls%26skip_history=true%26playlist=true%26dir='+base64_encode(ajxpNode.getPath());
			newWin.document.write(template.evaluate({
				window_title : "AjaXplorer MP3 Player",
				reading_folder : MessageHash[141],
				playlist_url:playlist_url, 
				current_folder:ajxpNode.getLabel()
			}));
			newWin.document.close();
		}catch(e){
			alert(e);
		}		
	}
	
});