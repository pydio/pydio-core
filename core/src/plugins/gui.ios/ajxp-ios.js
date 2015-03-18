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
function cleanURL(url){
    var split = url.split("#");
   	url = split[0];
	split = url.split("?");
	url = split[0];
	if(url.charAt(url.length-1) == "/") {
		url = url.substring(0,url.length-1);
	}
	return url;
}

document.observe("ajaxplorer:gui_loaded", function(){
	document.addEventListener("touchmove", function(event){
		event.preventDefault();
	});
	var currentHref = document.location.href;
	
	$("ajxpserver-redir").href = cleanURL(currentHref).replace("http://", "ajxpserver://").replace("https://", "ajxpservers://");
    if(currentHref.indexOf("#") > -1){
        currentHref = currentHref.substr(0, currentHref.indexOf("#"));
    }
    var suffix;
    if(navigator.userAgent.match(/android/i)){
        $("appstore-redir").href = ajaxplorer.getPluginConfigs("guidriver").get("ANDROID_URL");
        //$("ajxpserver-redir").hide();
        suffix = "android";
    }else{
        $("appstore-redir").href = ajaxplorer.getPluginConfigs("guidriver").get("IOS_URL");
        suffix = "ios";
    }
    $("skipios-redir").href = currentHref + (currentHref.indexOf("?")>-1?"&":"?") + "skip"+suffix.toUpperCase()+"=true";
    $("message-id-1").update(MessageHash["ios_gui.1."+suffix].replace("%s", ajaxplorer.getPluginConfigs("ajaxplorer").get("APPLICATION_TITLE")));
    $("ajxpserver-redir").update(MessageHash["ios_gui.2."+suffix].replace("%s", ajaxplorer.getPluginConfigs("ajaxplorer").get("APPLICATION_TITLE")));
    $("appstore-redir").update(MessageHash["ios_gui.3."+suffix].replace("%s", ajaxplorer.getPluginConfigs("ajaxplorer").get("APPLICATION_TITLE")));
    $("skipios-redir").update(MessageHash["ios_gui.4."+suffix].replace("%s", ajaxplorer.getPluginConfigs("ajaxplorer").get("APPLICATION_TITLE")));
});