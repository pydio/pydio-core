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
window.SM2_DEFER = true;
if(!window.soundManager && ajaxplorer.findEditorById("editor.soundmanager")){
    var conn = new Connexion();
    conn._libUrl = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+'plugins/editor.soundmanager/sm/';
    conn.loadLibrary('360-player/script/berniecode-animator.js');
    conn.loadLibrary('script/soundmanager2-nodebug-jsmin.js', function(){
        window.soundManager = new SoundManager('plugins/editor.soundmanager/sm/swf/');
        window.soundManager.url = (ajxpBootstrap.parameters.get('SERVER_PREFIX_URI')?ajxpBootstrap.parameters.get('SERVER_PREFIX_URI'):'')+'plugins/editor.soundmanager/sm/swf/';
        if(ajaxplorer && ajaxplorer.user && ajaxplorer.user.getPreference("soundmanager.volume") !== undefined){
            soundManager.defaultOptions.volume = ajaxplorer.user.getPreference("soundmanager.volume");
        }
        conn.loadLibrary('360-player/script/360player.js', function(){

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
            if(!row.ajxpNode || row.ajxpNode.getAjxpMime() != "mp3") return;
            addVolumeButton();
            var url = ajxpBootstrap.parameters.get('ajxpServerAccess')+'&get_action=audio_proxy&file='+base64_encode(row.ajxpNode.getPath())+ '&fake=extension.mp3';
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
    )
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
	
	initialize: function($super, oFormObject){
	},
		
	getPreview : function(ajxpNode, rich){
        if(!window.soundManager || !window.soundManager.enabled){
            var im = new Element('img', {src:resolveImageSource(ajxpNode.getIcon(),'/images/mimes/ICON_SIZE',64),align:"absmiddle"});
            return im;
        }
        addVolumeButton();
        var url = ajxpBootstrap.parameters.get('ajxpServerAccess')+'&get_action=audio_proxy&file='+base64_encode(ajxpNode.getPath());
        if(rich){
            url += '&rich_preview=true&fake=extension.mp3';
        }else{
            url += '&fake=extension.mp3';
        }
        var container = new Element("div", {className:"ui360container"+(rich?" nobackground":"")});
        var player = new Element("div", {className:"ui360"+(rich?" ui360-vis ui360-vis-retracted":"")}).update(new Element("a", {href:url}).update(""));
        container.update(player);
        container.resizePreviewElement = function(element){
            if(rich){
                player.setStyle({
                    marginLeft:parseInt((element.width-256)/2)+24+"px",
                    marginTop:'-15px'
                });
            }else{
                if(element.height >= 50)
                {
                    var mT = parseInt((element.height - 50)/2) + element.margin;
                    var mB = element.height+(element.margin*2)-50-mT-1;
                    container.removeClassName("nobackground");
                    container.setStyle({paddingTop:mT+'px', paddingBottom:mB+'px', marginBottom:'0px'});
                }else{
                    var mT = 0;
                    var mB = element.height-40;
                    container.addClassName("nobackground");
                    container.setStyle({paddingTop:mT+'px', paddingBottom:'0px', marginBottom:mB+'px'});
                }
                container.setStyle({
                    paddingLeft:Math.ceil((element.width-50)/2)+12+"px"
                });
            }
        };
        container.destroyElement = function(){
            var urlKey = container.down('a.sm2_link').href;
            if(threeSixtyPlayer.getSoundByURL(urlKey)){
                var theSound = threeSixtyPlayer.getSoundByURL(urlKey);
                threeSixtyPlayer.sounds = $A(threeSixtyPlayer.sounds).without(theSound);
                threeSixtyPlayer.soundsByURL[urlKey] = null;
                delete threeSixtyPlayer.soundsByURL[urlKey];
                soundManager.destroySound(theSound.sID);
            }
        }

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