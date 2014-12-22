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
 */
Class.create("VideoPreviewer", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject, options){
        this.editorOptions = options;
        this.element = oFormObject;
    },

    open : function($super, ajxpNode){
        this.currentRichPreview = this.getPreview(ajxpNode, true);
        if(this.element.id == "videojs_previewer"){
            fitHeightToBottom(this.element);
            this.element.insert(this.currentRichPreview);
        }else{
            this.element.down("#videojs_previewer").setStyle({height:'297px'});
            this.element.down("#videojs_previewer").insert(this.currentRichPreview);
            this.currentRichPreview.resizePreviewElement({width:380, height:260, maxHeight:260}, true);
            if(this.element.down('.vjs-flash-fallback')){
                fitHeightToBottom(this.element.down('.vjs-flash-fallback'));
            }
            if(this.element.down('object')){
                this.element.down('object').setAttribute('height', this.element.getHeight());
            }
        }
        modal.setCloseValidation(function(){
            this.currentRichPreview.destroyElement();
            return true;
        }.bind(this));
        this.element.fire("editor:updateTitle", ajxpNode.getLabel());
    },

    resize: function($super, size){

        $super(size);
        fitHeightToBottom(this.element);
        try{
            this.currentRichPreview.resizePreviewElement({
                width:this.element.getWidth(),
                height:this.element.getHeight(),
                maxHeight:this.element.getHeight()
            }, true);
        }catch(e){}
        if(this.element.down('.vjs-flash-fallback')){
            fitHeightToBottom(this.element.down('.vjs-flash-fallback'));
        }
        if(this.element.down('object')){
            this.element.down('object').setAttribute('height', this.element.getHeight());
        }

    },

    destroy: function(){

        this.currentRichPreview.destroyElement();

    },

    getSharedPreviewTemplate : function(node){

        var mime = getAjxpMimeType(node);
        var cType;
        if(mime == "mp4" || mime == "m4v") cType = "video/mp4";
        else if(mime == "ogv") cType = "video/ogg";
        else if(mime == "webm") cType = "video/webm";
        return new Template('<link href="http://vjs.zencdn.net/c/video-js.css" rel="stylesheet">\n\
&lt;script src="http://vjs.zencdn.net/c/video.js"&gt;&lt;/script&gt;\n\
<video id="my_video_1" class="video-js vjs-default-skin" controls\n\
preload="auto" width="#{WIDTH}" height="#{HEIGHT}" data-setup="{}">\n\
<source src="#{DL_CT_LINK}" type="'+cType+'">\n\
</video>');

    },

    getRESTPreviewLinks:function(node){
        return {"Video Stream": "&file=" + encodeURIComponent(node.getPath())};
    },


    getPreview : function(ajxpNode, rich){
		if(rich){
			var url = document.location.href.split('#').shift().split('?').shift();
			if(url[(url.length-1)] == '/'){
				url = url.substr(0, url.length-1);
			}else if(url.lastIndexOf('/') > -1){
				url = url.substr(0, url.lastIndexOf('/'));
			}
            if($$('base').length){
                url = $$("base")[0].getAttribute("href");
                if(!url.startsWith('http') && !url.startsWith('https')){
                    url = document.location.origin + url;
                }
            }

            var html5proxies = $H({});
			var mime = ajxpNode.getAjxpMime();
            if(mime == "mp4" || mime == "m4v" || mime == "webm" || mime == "ogv"){
                html5proxies.set(mime, ajxpNode.getPath());
            }
            var meta = ajxpNode.getMetadata();
            if(meta.get("video_altversion_mp4")){
                html5proxies.set('mp4', meta.get("video_altversion_mp4"));
            }
            if(meta.get("video_altversion_webm")){
                html5proxies.set('webm', meta.get("video_altversion_webm"));
            }
            if(meta.get("video_altversion_ogv")){
                html5proxies.set('ogv', meta.get("video_altversion_ogv"));
            }

			//if(mime == "mp4" || mime == "webm" || mime == "ogv"){
            var div, content;
            if(html5proxies.keys().length){
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
				var sessidPart = '&ajxp_sessid='+window.crtAjxpSessid;

				var types = {
					mp4:'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
					m4v:'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
					webm:'video/webm; codecs="vp8, vorbis"',
					ogv:'video/ogg; codecs="theora, vorbis"'
				};
				var poster = resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
				div = new Element("div", {className:"video-js-box"});
				content = '';
				content +='	<video class="video-js" controls preload="auto" height="200">';
                var flashName;
                html5proxies.each(function(pair){
                    var fname = url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=read_video_data'+sessidPart+'&file='+encodeURIComponent(pair.value);
                    if(!flashName){
                        flashName = encodeURIComponent(url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=read_video_data'+sessidPart+'&file='+pair.value);
                    }
                    content +='		<source src="'+fname+'" type=\''+types[pair.key]+'\' />';
                });
				content += '<object type="application/x-shockwave-flash" class="vjs-flash-fallback" data="plugins/editor.video/player_flv_maxi.swf" width="100%" height="200">';
				content += '	<param name="movie" value="plugins/editor.video/player_flv_maxi.swf" />';
				content += '	<param name="quality" value="high">';
				content += '	<param name="allowFullScreen" value="true" />';
				content += '	<param name="FlashVars" value="flv='+flashName+'&showstop=1&showvolume=1&showtime=1&showfullscreen=1&playercolor=676965&bgcolor1=f1f1ef&bgcolor2=f1f1ef&buttonovercolor=000000&sliderovercolor=000000" />';
				content += '</object>';
								
				content +='	</video>';
				content += '<p align="center"> <img src="'+poster+'" width="64" height="64"></p>';
				
				div.update(content);
				div.resizePreviewElement = function(dimensionObject, innerInstance){
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
							playerFallbackOrder : (mime == "mp4" || mime=="m4v"?["html5", "flash", "links"]:["html5", "links"])
						});
					}
                    var height = Math.min(dimensionObject.height, dimensionObject.maxHeight);
                    var width = dimensionObject.width;
                    var styleObject = {height: height + 'px', width : width + 'px'};
					div.setStyle(styleObject);
					div.down('.vjs-flash-fallback').setAttribute('width', width);
                    if(innerInstance) div.down('.vjs-flash-fallback').setAttribute('height', height);
					if(videoObject) {
                        videoObject.setAttribute('width', width);
                        if(innerInstance) videoObject.setAttribute('height', height);
                        videoObject.setStyle(styleObject);
                    }
                    if(div.ajxpPlayer) {
                        div.ajxpPlayer.height(height);
                        div.ajxpPlayer.width(width);
                    }
				};
                div.destroyElement = function(){
                    if(div.ajxpPlayer){
                        div.ajxpPlayer.pause();
                        try{
                            $A(div.children).invoke("remove");
                            div.down("video").destroy();
                        }catch(e){}
                        div.update('');
                    }
                };
				
			}else{
                var f = encodeURIComponent(url+'/'+ajxpBootstrap.parameters.get('ajxpServerAccess')+'&action=read_video_data&file='+ajxpNode.getPath());
				div = new Element('div', {id:"video_container", style:"text-align:center; margin-bottom: 5px;"});
				content = '<object type="application/x-shockwave-flash" data="plugins/editor.video/player_flv_maxi.swf" width="100%" height="200">';
				content += '	<param name="movie" value="plugins/editor.video/player_flv_maxi.swf" />';
				content += '	<param name="quality" value="high">';
                content += '    <param name="wmode" value="opaque" />';
				content += '	<param name="allowFullScreen" value="true" />';
				content += '	<param name="FlashVars" value="flv='+f+'&showstop=1&showvolume=1&showtime=1&showfullscreen=1&playercolor=676965&bgcolor1=f1f1ef&bgcolor2=f1f1ef&buttonovercolor=000000&sliderovercolor=000000" />';
				content += '</object>';
				div.update(content);
				div.resizePreviewElement = function(dimensionObject){
					// do nothing;
                    var h =dimensionObject.height;
                    if(h > 400) div.down('object').setAttribute('height', 400);
                    else if(h > 300) div.down('object').setAttribute('height', 300);
                    else if(h > 200) div.down('object').setAttribute('height', 200);
				};
                div.destroyElement = function(){
                    div.update('');
                };
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