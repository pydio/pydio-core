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
 * Description : Simple Boot Loader.
 * Defaults params for constructor should be {} and content.php?get_action=get_boot_conf
 */
Class.create("AjxpBootstrap", {
	parameters : $H({}),
	initialize : function(startParameters, booterUrl){
		this.parameters = $H(startParameters);
		if(this.parameters.get("ALERT")){
			window.setTimeout(function(){alert(this.parameters.get("ALERT"));}.bind(this),0);
		}
		this.parameters.set("booterUrl",booterUrl);
		this.detectBaseParameters();
		this.insertLoaderProgress();
		Event.observe(window, 'load', function(){
			this.loadBootConfig();		
		}.bind(this));
		document.observe("ajaxplorer:before_gui_load", function(e){
			var marginBottom = 0;
			if($('optional_bottom_div') && $('optional_bottom_div').getHeight()>15 ){
				marginBottom = $('optional_bottom_div').getHeight();
			}
			fitHeightToBottom($("ajxp_desktop"), window, marginBottom, true);
		});
		document.observe("ajaxplorer:loaded", function(e){
			this.insertAnalytics();
			if(this.parameters.get("SELECTOR_DATA")){
	    		ajaxplorer.actionBar.defaultActions.set("select", "ext_select");
	    		ajaxplorer.actionBar.selectorData = new Hash(this.parameters.get("SELECTOR_DATA"));
			}
		}.bind(this));
	},
	loadBootConfig : function(){
		var connexion = new Connexion(this.parameters.get('booterUrl')+(this.parameters.get("debugMode")?'&debug=true':''));
		connexion.onComplete = function(transport){
			var data = transport.responseText.evalJSON();
			this.parameters.update(data);
			this.parameters.get("cssResources").each(this.loadCSSResource.bind(this));
			if(!this.parameters.get("debugMode")){
				connexion.loadLibrary("ajaxplorer.js");
			}
			window.MessageHash = this.parameters.get("i18nMessages");
			window.zipEnabled = this.parameters.get("zipEnabled");
			window.multipleFilesDownloadEnabled = this.parameters.get("multipleFilesDownloadEnabled");
			window.flashUploaderEnabled = this.parameters.get("flashUploaderEnabled");
			window.ajaxplorer = new Ajaxplorer(this.parameters.get("EXT_REP")||"", this.parameters.get("usersEnabled"), this.parameters.get("loggedUser"));
			if(this.parameters.get("currentLanguage")){
				window.ajaxplorer.currentLanguage = this.parameters.get("currentLanguage");
			}
			if(this.parameters.get("htmlMultiUploaderOptions")){
				window.htmlMultiUploaderOptions = this.parameters.get("htmlMultiUploaderOptions");
			}
			$('version_span').update(' - Version '+this.parameters.get("ajxpVersion") + ' - '+ this.parameters.get("ajxpVersionDate"));
		}.bind(this);
		connexion.sendSync();
		
	},
	detectBaseParameters : function(){
		$$('script').each(function(scriptTag){
			if(scriptTag.src.match("/js/ajaxplorer_boot.js") || scriptTag.src.match("/js/ajaxplorer/class.AjxpBootstrap.js")){
				if(scriptTag.src.match("/js/ajaxplorer_boot.js")){
					this.parameters.set("debugMode", false);
				}else{
					this.parameters.set("debugMode", true);
				}
				this.parameters.set("ajxpResourcesFolder", scriptTag.src.replace('/js/ajaxplorer/class.AjxpBootstrap.js','').replace('/js/ajaxplorer_boot.js', ''));
				return;
			}
		}.bind(this) );
		if(this.parameters.get("ajxpResourcesFolder")){
			window.ajxpResourcesFolder = this.parameters.get("ajxpResourcesFolder");		
		}else{
			alert("Cannot find resource folder");
		}
		var booterUrl = this.parameters.get("booterUrl");
		if(booterUrl.indexOf("?") > -1){
			booterUrl = booterUrl.substring(0, booterUrl.indexOf("?"));
		}
		this.parameters.set('ajxpServerAccessPath', booterUrl);
		window.ajxpServerAccessPath = booterUrl;
	},
	insertLoaderProgress : function(){
		var html = '<div id="loading_overlay" style="background-color:#b1cae8;"></div>';
		html+='	<div id="progressBox" style="background-color:#b1cae8;">';
		html+='		<div id="loaderContent" style="text-align:left; width:416px; height: 321px; background-image:url(\''+this.parameters.get('ajxpResourcesFolder')+'/images/SplashGradBG.png\');background-repeat:no-repeat; padding:0px;">';
		html+='				<div style="padding:5px;">';
		html+='					<div align="left" style="font-size:12px;font-family:Trebuchet MS, sans-serif; font-weight:normal;color:#f1f1ef;position: relative;top:210px; left: 10px;">';
		html+='					Written by Charles du Jeu - LGPL License. <br>';
		html+='					AjaXplorer web browser <span id="version_span"></span>';
		html+='					<div style="margin-top: 10px;" id="progressState">Booting...</div>';
		html+='					<div id="progressBarBorder" style="font-size: 0.5em;" align="left"><div id="progressBar" style="width:0px;"></div></div>';
		html+='				</div>';
		html+='		</div>';
		html+='	</div>';
		$$('body')[0].insert({top:html});
	},
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
	loadCSSResource : function(fileName){
		var head = $$('head')[0];
		var cssNode = new Element('link', {
			type : 'text/css',
			rel  : 'stylesheet',
			href : this.parameters.get("ajxpResourcesFolder") + '/' + fileName,
			media : 'screen'
		});
		head.insert(cssNode);
	}	
});