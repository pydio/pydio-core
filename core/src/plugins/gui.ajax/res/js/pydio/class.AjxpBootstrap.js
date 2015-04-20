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

/**
 * Main BootLoader.
 * Defaults params for constructor should be {} and content.php?get_action=get_boot_conf
 */
Class.create("AjxpBootstrap", {
	/**
	 * @var $H()
	 */
	parameters : $H({}),
	/**
	 * Constructor 
	 * @param startParameters Object The options
	 */
	initialize : function(startParameters){
		this.parameters = $H(startParameters);
		this.detectBaseParameters();
		if(this.parameters.get("ALERT")){
			window.setTimeout(function(){alert(this.parameters.get("ALERT"));}.bind(this),0);
		}		
		Event.observe(document, 'dom:loaded', function(){
			this.insertBasicSkeleton(this.parameters.get('MAIN_ELEMENT'));
            var startedFromOpener = false;
            try{
                if(window.opener && window.opener.ajxpBootstrap){
                    this.parameters = window.opener.ajxpBootstrap.parameters;
                    // Handle queryString case, as it's not passed via get_boot_conf
                    var qParams = document.location.href.toQueryParams();
                    if(qParams['external_selector_type']){
                        this.parameters.set('SELECTOR_DATA', {type:qParams['external_selector_type'], data:qParams});
                    }else{
                        if(this.parameters.get('SELECTOR_DATA')) this.parameters.unset('SELECTOR_DATA');
                    }
                    this.refreshContextVariablesAndInit(new Connexion());
                    startedFromOpener = true;
                }
            }catch(e){
                if(console && console.log) console.log(e);
            }
            if(!startedFromOpener){
                this.loadBootConfig();
            }
		}.bind(this));
		document.observe("ajaxplorer:before_gui_load", function(e){
			var desktop = $(this.parameters.get('MAIN_ELEMENT'));
			var options = desktop.getAttribute("ajxpOptions").evalJSON(false);
			if(options.fit && options.fit == 'height'){
				var marginBottom = 0;
				if(options.fitMarginBottom){
					try{marginBottom = parseInt(eval(options.fitMarginBottom));}catch(e){}
				}
				if(options.fitParent == 'window') options.fitParent = window;
				else options.fitParent = $(options.fitParent);
				fitHeightToBottom($(this.parameters.get('MAIN_ELEMENT')), options.fitParent, marginBottom, true);
			}
		}.bind(this));
		document.observe("ajaxplorer:actions_loaded", function(){
			if(!this.parameters.get("SELECTOR_DATA") && ajaxplorer.actionBar.actions.get("ext_select")){
				ajaxplorer.actionBar.actions.unset("ext_select");
				ajaxplorer.actionBar.fireContextChange();
				ajaxplorer.actionBar.fireSelectionChange();	
			}else if(this.parameters.get("SELECTOR_DATA")){
				ajaxplorer.actionBar.defaultActions.set("file", "ext_select");
			}
		}.bind(this));					
		document.observe("ajaxplorer:loaded", function(e){
			this.insertAnalytics();
			if(this.parameters.get("SELECTOR_DATA")){
	    		ajaxplorer.actionBar.defaultActions.set("file", "ext_select");
	    		ajaxplorer.actionBar.selectorData = new Hash(this.parameters.get("SELECTOR_DATA"));	    		
			}
		}.bind(this));
        window.pydioBootstrap = this;
	},
	/**
	 * Real loading action
	 */
	loadBootConfig : function(){
        if(this.parameters.get('PRELOADED_BOOT_CONF')){
            this.parameters.update(this.parameters.get('PRELOADED_BOOT_CONF'));
            if(this.parameters.get('SECURE_TOKEN')){
                Connexion.SECURE_TOKEN = this.parameters.get('SECURE_TOKEN');
            }
            if(this.parameters.get('SERVER_PREFIX_URI')){
                this.parameters.set('ajxpResourcesFolder', this.parameters.get('SERVER_PREFIX_URI') + this.parameters.get('ajxpResourcesFolder'));
                this.parameters.set('ajxpServerAccess', this.parameters.get('SERVER_PREFIX_URI') + this.parameters.get('ajxpServerAccess') + '?' + (Connexion.SECURE_TOKEN? 'secure_token='+Connexion.SECURE_TOKEN:''));
            }else{
                this.parameters.set('ajxpServerAccess', this.parameters.get('ajxpServerAccess') + '?' + (Connexion.SECURE_TOKEN? 'secure_token='+Connexion.SECURE_TOKEN:''));
            }
            if(this.parameters.get('SERVER_PERMANENT_PARAMS')){
                this.parameters.set('ajxpServerAccess', this.parameters.get('ajxpServerAccess') + '&' + this.parameters.get('SERVER_PERMANENT_PARAMS') + '&');
            }
            this.refreshContextVariablesAndInit(new Connexion());
            return;
        }

		var url = this.parameters.get('BOOTER_URL')+(this.parameters.get("debugMode")?'&debug=true':'');
		if(this.parameters.get('SERVER_PREFIX_URI')){
			url += '&server_prefix_uri=' + this.parameters.get('SERVER_PREFIX_URI').replace(/\.\.\//g, "_UP_/");
		}
		var connexion = new Connexion(url);
		connexion.onComplete = function(transport){			
			if(transport.responseXML && transport.responseXML.documentElement && transport.responseXML.documentElement.nodeName == "tree"){
				var alert = XPathSelectSingleNode(transport.responseXML.documentElement, "message");
				window.alert('Exception caught by application : ' + alert.firstChild.nodeValue);
				return;
			}
			var phpError;
			try{
				var data = transport.responseText.evalJSON();
			}catch(e){
				phpError = 'Error while parsing JSON response : ' + e.message;
			}
			if(!typeof data == "object"){
				phpError = 'Exception uncaught by application : ' + transport.responseText;
			}
			if(phpError){
				document.write(phpError);
				if(phpError.indexOf('<b>Notice</b>')>-1 || phpError.indexOf('<b>Strict Standards</b>')>-1){
					window.alert('Php errors detected, it seems that Notice or Strict are detected, you may consider changing the PHP Error Reporting level!');
				}
				return;
			}
			this.parameters.update(data);
			
			if(this.parameters.get('SECURE_TOKEN')){
				Connexion.SECURE_TOKEN = this.parameters.get('SECURE_TOKEN');
			}
			if(this.parameters.get('SERVER_PREFIX_URI')){
				this.parameters.set('ajxpResourcesFolder', this.parameters.get('SERVER_PREFIX_URI') + this.parameters.get('ajxpResourcesFolder'));
				this.parameters.set('ajxpServerAccess', this.parameters.get('SERVER_PREFIX_URI') + this.parameters.get('ajxpServerAccess') + '?' + (Connexion.SECURE_TOKEN? 'secure_token='+Connexion.SECURE_TOKEN:''));
			}else{
				this.parameters.set('ajxpServerAccess', this.parameters.get('ajxpServerAccess') + '?' + (Connexion.SECURE_TOKEN? 'secure_token='+Connexion.SECURE_TOKEN:''));
			}
            if(this.parameters.get('SERVER_PERMANENT_PARAMS')){
                this.parameters.set('ajxpServerAccess', this.parameters.get('ajxpServerAccess') + '&' + this.parameters.get('SERVER_PERMANENT_PARAMS') + '&');
            }
			
			this.refreshContextVariablesAndInit(connexion);
			
		}.bind(this);
		connexion.sendSync();
		
	},
	
	refreshContextVariablesAndInit: function(connexion){
		if(this.parameters.get('SECURE_TOKEN') && !Connexion.SECURE_TOKEN){
			Connexion.SECURE_TOKEN = this.parameters.get('SECURE_TOKEN');
		}

		// Refresh window variable
		window.ajxpServerAccessPath = this.parameters.get('ajxpServerAccess');
		var cssRes = this.parameters.get("cssResources");
		if(cssRes) cssRes.each(this.loadCSSResource.bind(this));
		if(this.parameters.get('ajxpResourcesFolder')){
            connexion._libUrl = this.parameters.get('ajxpResourcesFolder') + "/js";
			window.ajxpResourcesFolder = this.parameters.get('ajxpResourcesFolder') + "/themes/" + this.parameters.get("theme");
		}
		if(this.parameters.get('additional_js_resource')){
			connexion.loadLibrary(this.parameters.get('additional_js_resource?v='+this.parameters.get("ajxpVersion")), null, true);
		}
		this.insertLoaderProgress();
		window.MessageHash = this.parameters.get("i18nMessages");
        if(!Object.keys(MessageHash).length){
            alert('Ooups, this should not happen, your message file is empty!');
        }
		for(var key in MessageHash){
			MessageHash[key] = MessageHash[key].replace("\\n", "\n");
		}
		window.zipEnabled = this.parameters.get("zipEnabled");
		window.multipleFilesDownloadEnabled = this.parameters.get("multipleFilesDownloadEnabled");
        var masterClassLoaded = function(){
            document.fire("ajaxplorer:boot_loaded");
            window.pydio = window.ajaxplorer = new Ajaxplorer(this.parameters.get("EXT_REP")||"", this.parameters.get("usersEnabled"), this.parameters.get("loggedUser"));
            if(this.parameters.get("currentLanguage")){
                window.ajaxplorer.currentLanguage = this.parameters.get("currentLanguage");
            }
            $('version_span').update(' - Version '+this.parameters.get("ajxpVersion") + ' - '+ this.parameters.get("ajxpVersionDate"));
            window.ajaxplorer.init();
        }.bind(this);
        if(!this.parameters.get("debugMode")){
            connexion.loadLibrary("ajaxplorer.js?v="+this.parameters.get("ajxpVersion"), masterClassLoaded, true);
        }else{
            masterClassLoaded();
        }

    },
	
	/**
	 * Detect the base path of the javascripts based on the script tags
	 */
	detectBaseParameters : function(){
		$$('script').each(function(scriptTag){
			if(scriptTag.src.match("/js/ajaxplorer_boot") || scriptTag.src.match("/js/pydio/class.AjxpBootstrap.js")){
				if(scriptTag.src.match("/js/ajaxplorer_boot")){
					this.parameters.set("debugMode", false);
				}else{
					this.parameters.set("debugMode", true);
				}
                var src = scriptTag.src.replace('/js/pydio/class.AjxpBootstrap.js','').replace('/js/ajaxplorer_boot.js', '').replace('/js/ajaxplorer_boot_protolegacy.js', '');
                if(src.indexOf("?")!=-1) src = src.split("?")[0];
				this.parameters.set("ajxpResourcesFolder", src);
			}
		}.bind(this) );
		if(this.parameters.get("ajxpResourcesFolder")){
			window.ajxpResourcesFolder = this.parameters.get("ajxpResourcesFolder");		
		}else{
			alert("Cannot find resource folder");
		}
		var booterUrl = this.parameters.get("BOOTER_URL");
		if(booterUrl.indexOf("?") > -1){
			booterUrl = booterUrl.substring(0, booterUrl.indexOf("?"));
		}
		this.parameters.set('ajxpServerAccessPath', booterUrl);
		window.ajxpServerAccessPath = booterUrl;
	},
	/**
	 * Inserts a progress bar 
	 */
	insertLoaderProgress : function(targetContainer, passedParameters){
		var html = '<div id="loading_overlay" style="background-color:#555555;opacity: 0.2;"></div>';
		if(this.parameters.get('customWelcomeScreen')){
			try { this.parameters.set('customWelcomeScreen', customFuncDecode(this.parameters.get('customWelcomeScreen')));
			}catch(e){
				this.parameters.set('customWelcomeScreen','');
			}
		}		
		if(this.parameters.get('customWelcomeScreen')){
			html += this.parameters.get('customWelcomeScreen');
		}else{
            var customWording;
            if(passedParameters){
                customWording = passedParameters;
            }else{
                customWording = this.parameters.get("customWording");
            }
			html+='	<div id="progressBox" class="dialogBox" style="width: 320px;display:block;top:30%;z-index:2002;left:40%;position: absolute;background-color: #fff;padding: 0;">';
			html+='	<div align="left" class="dialogContent" style="background-image:none; font-size: 13px;line-height: 1.5em;border-radius: 3px;padding: 0; border-width:0">';
			var icon = customWording.icon || ajxpResourcesFolder+'/../../../PydioLogo250.png';
            if(customWording.icon_binary_url){
                icon = this.parameters.get("ajxpServerAccess") + "&" + customWording.icon_binary_url;
            }
			var title = customWording.title || "Pydio";
			var iconWidth = customWording.iconWidth || '35px';
			var fontSize = customWording.titleFontSize || '35px';
            var titleDivSize = (customWording.iconHeight ? 'height:' + customWording.iconHeight + ';' : '');
			html+=' <div class="dialogTitle" style="border-bottom:0; margin-bottom:0px; font-size:'+fontSize+';font-weight:bold; background-size: 100%; background-image:url(\''+ (this.parameters.get("SERVER_PREFIX_URI") || '') + icon+'\');background-position:left center;background-repeat:no-repeat;padding-left:'+iconWidth+';'+titleDivSize+'color:#0077b3;border-radius:3px 3px 0 0;">'+(customWording.iconOnly?'':title)+'</div>';
            if(customWording.welcomeMessage){
                html+= '<div id="progressCustomMessage" style="font-size: 20px;line-height: 1.3em;padding:10px;">' + customWording.welcomeMessage.replace(new RegExp("\n", "g"), "<br>") + '</div>';
            }else{
                html+= '';
            }
            var spinnerType = "double-bounce";
            if(this.parameters.get("spinner-type")) spinnerType = this.parameters.get("spinner-type");
            if(spinnerType == "planet-rotating"){
                html += '<div style="height: 85px;position: relative;" id="loader_round_progress"><div class="rotating" style="width: 0;height: 0;border: 24px solid rgb(0, 123, 219);border-radius: 50px;position: absolute;clip: rect(0px, 50px, 100px, 0px);left: 131px;top: 11px;color: white;font-size: 20px;">.</div></div>';
            }else if(spinnerType == "stretch-rectangles"){
                html += '<div style="position: relative;" id="loader_round_progress" class="rect_spinners"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>';
            }else if(spinnerType == "double-bounce"){
                html += '<div style="position: relative;" id="loader_round_progress" class="bounce-spinner"><div class="double-bounce1"></div><div class="double-bounce2"></div></div>';
            }
			html += '<div style="padding:5px;font-size: 11px;line-height: 1.5em;" class="dialogFooter" id="loader_dialog_footer">';
            if(customWording.title.toLowerCase() != "ajaxplorer"){
				html+='	<div style="padding:4px 7px;position: relative;"><div>Pydio Community Edition<span id="version_span"></span></div>';
			}else{
				html+='	<div style="padding:4px 7px;position: relative;"><div>Build your own box! <span id="version_span"></span></div>';
			}
			html+='	Copyright C. du Jeu 2008-2014 - AGPL License';
            html+='<div id="progressState" style="float:left; display: none;">Booting...</div>';
			html+='	<div id="progressBarContainer" style="margin-top:3px; margin-left: 126px;display: none;"><span id="loaderProgress"></span></div>';
            html+= '<div id="progressBarHeighter" style="height:10px;display: none;"></div>';
			html+='</div></div></div>';
		}
        var viewPort;
        if(!targetContainer){
            targetContainer = $$('body')[0];
            viewPort = document.viewport.getDimensions();
        }else{
            viewPort = targetContainer.getDimensions();
        }
        targetContainer.insert(html);
        var progressBox = targetContainer.down('#progressBox');
		progressBox.setStyle({
            left:parseInt(Math.max((viewPort.width-progressBox.getWidth())/2,0))+"px",
            top:parseInt(Math.max((viewPort.height-progressBox.getHeight())/3,0))+"px"
        });
        var loaderEndCallback = function(){
            document.stopObserving('ajaxplorer:loader_state_update');
            new Effect.Parallel([
                new Effect.Opacity($('loading_overlay'),{sync:true, from:1.0, to:0.0, duration:0.5}),
                new Effect.Opacity($('loader_round_progress'),{sync:true,from:1,to:0,duration:0.5}),
                new Effect.Opacity($('loader_dialog_footer'),{sync:true,from:1,to:0,duration:0.5})
            ],
                {afterFinish : function(){
                    if($('loading_overlay')) $('loading_overlay').remove();
                    if($('progressBox')) $('progressBox').hide();
                }});
        };
        if(!window.ajxpThemeSkipLoaderProgress){
            var options = {
                animate		: false,										// Animate the progress? - default: true
                showText	: false,									// show text with percentage in next to the progressbar? - default : true
                width		: 154,										// Width of the progressbar - don't forget to adjust your image too!!!
                boxImage	: window.ajxpResourcesFolder+'/images/progress_box.gif',			// boxImage : image around the progress bar
                barImage	: window.ajxpResourcesFolder+'/images/progress_bar.gif',	// Image to use in the progressbar. Can be an array of images too.
                height		: 11,										// Height of the progressbar - don't forget to adjust your image too!!!
                onTick		: function(pbObj) {
                    if(pbObj.getPercentage() >= 80){
                        loaderEndCallback();
                        return false;
                    }
                    return true ;
                }
            };
            this.loaderProgress = new JS_BRAMUS.jsProgressBar($('loaderProgress'), 0, options);
        }
        document.observe('ajaxplorer:loader_state_update', function(e){
            var p = e.memo.percent;
            if(this.loaderProgress){
                this.loaderProgress.setPercentage(p*100);
            }else if(p >= 0.8){
                loaderEndCallback();
            }
        }.bind(this));

	},
	/**
	 * Inserts Google Analytics Code
	 */
	insertAnalytics : function(){	
		if(!this.parameters.get("googleAnalyticsData")) return;
		var data = this.parameters.get("googleAnalyticsData");
		window._gaq = window._gaq || [];
		window._gaq.push(['_setAccount', data.id]);		
		if(data.domain) window._gaq.push(['_setDomainName', data.domain]);
		window._gaq.push(['_trackPageview']);
		window._gaTrackEvents = data.event;
		window.setTimeout(function(){
			var src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var ga = new Element("script", {type:'text/javascript', async:'true',src:src});
			($$('head')[0] || $$('body')[0]).insert(ga);
		}, 200);
	},
	/**
	 * Loads a CSS file
	 * @param fileName String
	 */
	loadCSSResource : function(fileName){
		var head = $$('head')[0];
		var cssNode = new Element('link', {
			type : 'text/css',
			rel  : 'stylesheet',
			href : this.parameters.get("ajxpResourcesFolder") + '/' + fileName,
			media : 'screen'
		});
		head.insert(cssNode);
	},
	/**
	 * Inserts the all_forms and generic dialog box if not alreay present.
	 * @param desktopNode String The id of the node to attach
	 */
	insertBasicSkeleton : function(desktopNode){
        var elem = new Element("div", {style:"position: absolute;z-index: 10000; bottom: 0; right: 0; color: #7a7a7a;text-align: right;padding: 4px; padding-right: 10px;font-size: 12px;border-radius: 3px 0 0 0;"});
        if(document.viewport.getWidth() < 500){
            elem.update('Pydio Community &copy; C. du Jeu 2008-2013');
        }else{
            elem.update('Pydio, open source file sharing - Free / Non supported edition - <a target="_blank" style="color: #7a7a7a;" href="https://pyd.io/">https://pyd.io/</a>');
        }
        $(desktopNode).insert({after:elem});
        disableTextSelection(elem);
		if($('all_forms')) return;
		$(desktopNode).insert({after:
			'<div id="all_forms">\
				<div id="generic_dialog_box" class="dialogBox"><div class="dialogTitle"></div><div class="dialogContent"></div></div>\
				<div id="hidden_frames" style="display:none;"></div>\
				<div id="hidden_forms" style="position:absolute;left:-1000px;"></div>\
			</div>'});
	}
});