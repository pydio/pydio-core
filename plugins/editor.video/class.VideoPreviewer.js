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
Class.create("VideoPreviewer", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject){
	},
		
	getPreview : function(ajxpNode, rich){
		if(rich){
			var escapedFilename = escape(encodeURIComponent(ajxpNode.getPath()));
			var url = document.location.href;
			if(url[(url.length-1)] == '/'){
				url = url.substr(0, url.length-1);
			}else if(url.lastIndexOf('/') > -1){
				url = url.substr(0, url.lastIndexOf('/'));
			}
			var fileName = url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=read_video_data&file='+ajxpNode.getPath();
			
			var mime = ajxpNode.getAjxpMime();
			if(mime == "mp4" || mime == "webm" || mime == "ogv"){
				// Problem : some embedded HTML5 readers do not send the cookies!
				if(!window.crtAjxpSessid){
					var connexion = new Connexion();
					connexion.addParameter("get_action", "get_sess_id");
					connexion.onComplete = function(transport){
						window.crtAjxpSessid = transport.responseText.trim();
						window.setTimeout(function(){window.crtAjxpSessid = null}, 1000 * 60 * 5);
					};
					connexion.sendSync();
				}
				fileName += '&ajxp_sessid='+window.crtAjxpSessid; 

				var types = {
					mp4:'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
					webm:'video/webm; codecs="vp8, vorbis"',
					ogv:'video/ogg; codecs="theora, vorbis"'
				};
				var poster = resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
				var div = new Element("div", {className:"video-js-box"});
				var content = '';
				content +='<!-- Using the Video for Everybody Embed Code http://camendesign.com/code/video_for_everybody -->';
				content +='	<video class="video-js" controls preload="auto" height="200">';
				content +='		<source src="'+fileName+'" type=\''+types[mime]+'\' />';
				content +='      <!-- Flash Fallback. Use any flash video player here. Make sure to keep the vjs-flash-fallback class. -->';
				
				content += '<object type="application/x-shockwave-flash" class="vjs-flash-fallback" data="plugins/editor.video/player_flv_maxi.swf" width="100%" height="200">';
				content += '	<param name="movie" value="plugins/editor.video/player_flv_maxi.swf" />';
				content += '	<param name="quality" value="high">';
				content += '	<param name="allowFullScreen" value="true" />';
				content += '	<param name="FlashVars" value="flv='+url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'%26action=download%26file='+escapedFilename+'&showstop=1&showvolume=1&showtime=1&showfullscreen=1&playercolor=676965&bgcolor1=f1f1ef&bgcolor2=f1f1ef&buttonovercolor=000000&sliderovercolor=000000" />';
				content += '</object>';
								
				content +='	</video>';
				content += '<p align="center"> <img src="'+poster+'" width="64" height="64"></p>';
				
				div.update(content);
				div.resizePreviewElement = function(dimensionObject){
					var videoObject = div.down('.video-js');
					if(!div.ajxpPlayer && div.parentNode && videoObject){						
						$(div.parentNode).setStyle({paddingLeft:10,paddingRight:10});
						div.ajxpPlayer = VideoJS.setup(videoObject, {
							preload:true,
							controlsBelow: false, // Display control bar below video instead of in front of
							controlsHiding: true, // Hide controls when mouse is not over the video
							defaultVolume: 0.85, // Will be overridden by user's last volume if available
							flashVersion: 9, // Required flash version for fallback
							linksHiding: true, // Hide download links when video is supported,
							playerFallbackOrder : (mime == "mp4"?["html5", "flash", "links"]:["html5", "links"])
						});
					}
					div.setStyle({width:dimensionObject.width});
					div.down('.vjs-flash-fallback').setAttribute('width', dimensionObject.width);
					if(videoObject) videoObject.setAttribute('width', dimensionObject.width);
					if(div.ajxpPlayer) div.ajxpPlayer.triggerResizeListeners();
				}
				
			}else{
				var div = new Element('div', {id:"video_container", style:"text-align:center; margin-bottom: 5px;"});
				var content = '<object type="application/x-shockwave-flash" data="plugins/editor.video/player_flv_maxi.swf" width="100%" height="200">';
				content += '	<param name="movie" value="plugins/editor.video/player_flv_maxi.swf" />';
				content += '	<param name="quality" value="high">';
				content += '	<param name="allowFullScreen" value="true" />';
				content += '	<param name="FlashVars" value="flv='+url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'%26action=download%26file='+escapedFilename+'&showstop=1&showvolume=1&showtime=1&showfullscreen=1&playercolor=676965&bgcolor1=f1f1ef&bgcolor2=f1f1ef&buttonovercolor=000000&sliderovercolor=000000" />';
				content += '</object>';
				div.update(content);
				div.resizePreviewElement = function(dimensionObject){
					// do nothing;
				}
			}
			return div;
		}else{
			return new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64)});
		}
	},
	
	getThumbnailSource : function(ajxpNode){
		return resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
	}
	
});