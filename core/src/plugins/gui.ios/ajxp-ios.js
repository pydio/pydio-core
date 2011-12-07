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
function cleanURL(url){
    split = url.split("#");
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
	
	$("ajxpserver-redir").href = cleanURL(currentHref).replace("http://", "ajxpserver://");
    if(currentHref.indexOf("#") > -1){
        currentHref = currentHref.substr(0, currentHref.indexOf("#"));
    }
	$("skipios-redir").href = currentHref + (currentHref.indexOf("?")>-1?"&":"?") + "skipIOS=true";
});