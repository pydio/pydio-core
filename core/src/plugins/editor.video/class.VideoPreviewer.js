/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
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