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
window.SM2_DEFER = true;
if(!$$("html")[0].hasClassName("no-canvas") && !window.soundManager && ajaxplorer.findEditorById("editor.soundmanager")){
    var conn = new Connexion();
    conn._libUrl = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+'plugins/editor.soundmanager/sm/';
    conn.loadLibrary('360-player/script/berniecode-animator.js');
    conn.loadLibrary('script/soundmanager2-nodebug-jsmin.js', function(){
        window.soundManager = new SoundManager('plugins/editor.soundmanager/sm/swf/');
        window.soundManager.url = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+'plugins/editor.soundmanager/sm/swf/';
        if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("soundmanager.volume") !== undefined){
            soundManager.defaultOptions.volume = ajaxplorer.user.getPreference("soundmanager.volume");
        }
        var conn2 = new Connexion();
        conn2._libUrl = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+'plugins/editor.soundmanager/sm/';
        conn2.loadLibrary('360-player/script/360player.js', function(){

            if(!window.threeSixtyPlayer) return;

            window.threeSixtyPlayer.config.scaleFont = (navigator.userAgent.match(/msie/i)?false:true);
            window.threeSixtyPlayer.config.showHMSTime = true;
            window.threeSixtyPlayer.config.useWaveformData = true;
            window.threeSixtyPlayer.config.useEQData = true;
            // enable this in SM2 as well, as needed
            if (window.threeSixtyPlayer.config.useWaveformData) {
              window.soundManager.flash9Options.useWaveformData = true;
            }
            if (window.threeSixtyPlayer.config.useEQData) {
              window.soundManager.flash9Options.useEQData = true;
            }
            if (window.threeSixtyPlayer.config.usePeakData) {
              window.soundManager.flash9Options.usePeakData = true;
            }
            if (window.threeSixtyPlayer.config.useWaveformData || window.threeSixtyPlayer.flash9Options.useEQData || window.threeSixtyPlayer.flash9Options.usePeakData) {
              // even if HTML5 supports MP3, prefer flash so the visualization features can be used.
              window.soundManager.preferFlash = true;
            }

            window.soundManager.useFastPolling = true; // increased JS callback frequency, combined with useHighPerformance = true
            window.threeSixtyPlayer.config.onfinish = function(smPlayer){
                try{
                    var finishingPlayer = smPlayer._360data.oUI360;
                    if(finishingPlayer.hasClassName("ui360-vis")) {
                        window.setTimeout(function(){
                            finishingPlayer.addClassName("ui360-vis-retracted");
                        }, 1000);
                    }else{
                        var links = $$("div.ui360").reject(function(el){
                            return el.hasClassName("ui360-vis");
                        });
                        var index = links.indexOf(finishingPlayer);
                        if(index < links.length-1 ){
                            window.threeSixtyPlayer.handleClick({'target':links[index+1].down("a.sm2_link")});
                        }
                        if(finishingPlayer.up('.ajxpNodeProvider')){
                            finishingPlayer.up('.ajxpNodeProvider').removeClassName("SMNodePlaying");
                        }
                    }
                }catch(e){}
            };

            window.threeSixtyPlayer.config.onplay = function(smPlayer){
                try{
                    var playerDiv = smPlayer._360data.oUI360;
                    if(!playerDiv.hasClassName("ui360-vis")) {
                        if(playerDiv.up('.ajxpNodeProvider')){
                            playerDiv.up('.ajxpNodeProvider').addClassName("SMNodePlaying");
                        }
                    }else{
                        playerDiv.removeClassName("ui360-vis-retracted");
                    }
                }catch(e){}
            };

            window.threeSixtyPlayer.config.onstop = function(smPlayer){
                try{
                    var playerDiv = smPlayer._360data.oUI360;
                    if(!playerDiv.hasClassName("ui360-vis")) {
                        if(playerDiv.up('.ajxpNodeProvider')){
                            playerDiv.up('.ajxpNodeProvider').removeClassName("SMNodePlaying");
                        }
                    }else{
                        window.setTimeout(function(){
                            playerDiv.addClassName("ui360-vis-retracted");
                        }, 1000);
                    }
                }catch(e){}
            };

            window.soundManager.beginDelayedInit();
        });
    });
    hookToFilesList();
}

function hookToFilesList(){
    var fLists = ajaxplorer.guiCompRegistry.select(function(guiComponent){
        return (guiComponent.__className == "FilesList");
    });
    if(!fLists.length){
        return;
    }
    var fList = fLists[0];
    fList.observe("rows:didInitialize", function(){
        if(fList.getDisplayMode() != "list" || !window.soundManager || !window.soundManager.enabled) return;
        var resManager = ajaxplorer.findEditorById("editor.soundmanager").resourcesManager;
        if(!resManager.loaded){
            resManager.load();
        }
        $A(fList.getItems()).each(function(row){
            if(!row.ajxpNode || (row.ajxpNode.getAjxpMime() != "mp3" && row.ajxpNode.getAjxpMime() != "wav")) return;
            addVolumeButton();
            var url = ajxpBootstrap.parameters.get('ajxpServerAccess')+'&get_action=audio_proxy&file='+encodeURIComponent(base64_encode(row.ajxpNode.getPath()))+ '&fake=extension.'+row.ajxpNode.getAjxpMime();
            var player = new Element("div", {className:"ui360 ui360-micro"}).update(new Element("a", {href:url}).update(""));
            row.down("span#ajxp_label").setStyle({backgroundImage:'none'}).insert({top:player});
            threeSixtyPlayer.config.items = [player];
            threeSixtyPlayer.init();
        });
    });
    fList.observe("rows:willClear", function(){
        fList._htmlElement.select("div.ui360-micro").each( function(container){
            if(!container.down('a.sm2_link')) return;
            var urlKey = container.down('a.sm2_link').href;
            if(threeSixtyPlayer.getSoundByURL(urlKey)){
                var theSound = threeSixtyPlayer.getSoundByURL(urlKey);
                threeSixtyPlayer.sounds = $A(threeSixtyPlayer.sounds).without(theSound);
                threeSixtyPlayer.soundsByURL[urlKey] = null;
                delete threeSixtyPlayer.soundsByURL[urlKey];
                soundManager.destroySound(theSound.sID);
            }
        });
    });

}

function addVolumeButton(){
    if($("sm_volume_button")) return;
    var locBars = ajaxplorer.guiCompRegistry.select(function(guiComponent){
        return (guiComponent.__className == "LocationBar");
    });
    if(!locBars.length){
        return;
    }
    var locBar = locBars[0];
    var volumeButton = simpleButton(
        'sm_volume_button',
        'inlineBarButtonLeft',
        'sm_editor.1', 'sm_editor.1',
        'bookmark.png',
        16,
        'inline_hover',
        null,
        false,
        false
    );
    volumeButton.down("img").src = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+"plugins/editor.soundmanager/kmixdocked.png";
    locBar.bmButton.insert({before:volumeButton});
    new SliderInput(volumeButton, {
        range : $R(0, 100),
        sliderValue : soundManager.defaultOptions.volume,
        leftOffset:-1,
        topOffset:-1,
        anchorActiveClass: 'volume_slider_active',
        onSlide : function(value){
            volumeButton.down("img").src = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+"plugins/editor.soundmanager/kmixdocked"+(parseInt(value)==0?"-muted":"")+".png";
            soundManager.defaultOptions.volume = parseInt(value);
            soundManager.soundIDs.each(function(el){ soundManager.setVolume(el,parseInt(value)); });
        }.bind(this),
        onChange : function(value){
            if(!ajaxplorer || !ajaxplorer.user) return;
            ajaxplorer.user.setPreference("soundmanager.volume", parseInt(value));
            ajaxplorer.user.savePreference("soundmanager.volume");
        }.bind(this)
    });
    locBar.resize();
}

Class.create("SMPlayer", AbstractEditor, {

	fullscreenMode: false,
	
	initialize: function($super, oFormObject, options){
        this.element = oFormObject;
        this.editorOptions = options;
	},

    open : function($super, ajxpNode){
        this.currentRichPreview = this.getPreview(ajxpNode, true);
        this.element.down(".smplayer_title").update(ajxpNode.getLabel());
        this.element.down(".smplayer_preview_element").insert(this.currentRichPreview);
        window.setTimeout(function(){
            try{this.currentRichPreview.down('span.sm2-360btn').click();}catch(e){}
        }.bind(this), 400);
        modal.setCloseValidation(function(){
            this.currentRichPreview.destroyElement();
        }.bind(this));
    },

    /**
   	 * Closes the editor
   	 * @returns Boolean
   	 */
   	close : function($super){
        this.currentRichPreview.destroyElement();
   		return $super.close();
   	},

    getSharedPreviewTemplate: function(node){

        var crtRoot = document.location.href.split("#").shift().split("?").shift();
        var rgxtrim = new RegExp('\/+$');
        crtRoot = crtRoot.replace(rgxtrim, '');

        return new Template('<link rel="stylesheet" type="text/css" href="'+crtRoot+'/plugins/editor.soundmanager/sm/shared/mp3-player-button.css" />\n\
&lt;script type="text/javascript" src="'+crtRoot+'/plugins/editor.soundmanager/sm/shared/soundmanager2.js"&gt;&lt;/script&gt;\n\
&lt;script type="text/javascript" src="'+crtRoot+'/plugins/editor.soundmanager/sm/shared/mp3-player-button.js"&gt;&lt;/script&gt;\n\
&lt;script&gt;\n \
soundManager.setup({\n\
      url: "'+crtRoot+'/plugins/editor.soundmanager/sm/swf/",\n\
      debugMode : false\n\
});\n\
&lt;/script&gt;\n\
<a href="#{DL_CT_LINK}&fake=ext.'+getAjxpMimeType(node)+'" class="sm2_button">'+node.getLabel()+'</a> '+node.getLabel());

    },

    getRESTPreviewLinks:function(node){
        return {"MP3 Stream": "&file=" + encodeURIComponent(node.getPath())};
    },

	getPreview : function(ajxpNode, rich){
        if(!window.soundManager || !window.soundManager.enabled){
            return new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64),align:"absmiddle"});
        }
        addVolumeButton();
        var url = ajxpBootstrap.parameters.get('ajxpServerAccess')+'&get_action=audio_proxy&file='+encodeURIComponent(base64_encode(ajxpNode.getPath()));
        if(rich){
            url += '&rich_preview=true&fake=extension.'+ajxpNode.getAjxpMime();
        }else{
            url += '&fake=extension.'+ajxpNode.getAjxpMime();
        }
        var container = new Element("div", {className:"ui360container"+(rich?" nobackground":"")});
        var player = new Element("div", {className:"ui360"+(rich?" ui360-vis ui360-vis-retracted":"")}).update(new Element("a", {href:url}).update(""));
        container.update(player);
        container.resizePreviewElement = function(element){
            if(rich){
                player.setStyle({
                    marginLeft:parseInt((element.width-256)/2)+9+"px",
                    marginTop:'-15px'
                });
                if(Prototype.Browser.IE) {
                    try{
                        player.up("div").up("div").next("div.panelHeader").setStyle({width:'100%'});
                    }catch (e){}
                }
            }else{
                var addLeft = 12;
                if(container.up('.thumbnail_selectable_cell.detailed')) addLeft = 2;
                var mT, mB;
                if(element.height >= 50)
                {
                    mT = parseInt((element.height - 50)/2) + element.margin;
                    mB = element.height+(element.margin*2)-50-mT-1;
                    container.removeClassName("nobackground");
                    container.setStyle({paddingTop:mT+'px', paddingBottom:mB+'px', marginBottom:'0px'});
                }else{
                    mT = 0;
                    mB = element.height-40;
                    container.addClassName("nobackground");
                    if(mB + addLeft < 0) {
                        container.setStyle({marginTop:(mB/2)+'px', paddingBottom:'0px', marginLeft:((mB/2)-2)+'px'});
                    }else{
                        container.setStyle({paddingTop:mT+'px', paddingBottom:'0px', marginBottom:mB+'px'});
                    }
                }
                container.setStyle({
                    paddingLeft:Math.ceil((element.width-50)/2)+addLeft+"px"
                });
            }
        };
        container.destroyElement = function(){
            if(container.down('a.sm2_link')) {
                var urlKey = container.down('a.sm2_link').href;
                if(threeSixtyPlayer.getSoundByURL(urlKey)){
                    var theSound = threeSixtyPlayer.getSoundByURL(urlKey);
                    threeSixtyPlayer.sounds = $A(threeSixtyPlayer.sounds).without(theSound);
                    threeSixtyPlayer.soundsByURL[urlKey] = null;
                    delete threeSixtyPlayer.soundsByURL[urlKey];
                    soundManager.destroySound(theSound.sID);
                }
            }
        };

        threeSixtyPlayer.config.items = [player];
        threeSixtyPlayer.init();
        return container;

    },

    getThumbnailSource : function(ajxpNode){
        return resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64);
    },

    filterElement : function(element, ajxpNode){
        
    }

});
