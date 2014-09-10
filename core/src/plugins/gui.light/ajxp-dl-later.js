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
window.logAjxpEven = false;
function logAjxpBmAction(text){
	window.logAjxpEven = !window.logAjxpEven;
    if($('actions_log')){
    	$('actions_log').insert('<div class="ajxp_bm_log_action" style="background-color:#'+(window.logAjxpEven?'eee':'fff')+'">' + text + '<div>');
    }else if(window.console){
        console.log(text);
    }
}
function string_to_slug(str) {
  str = str.replace('https://', '').replace('http://', '');
  str = str.replace(/^\s+|\s+$/g, ''); // trim
  str = str.toLowerCase();

  // remove accents, swap ñ for n, etc
  var from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
  var to   = "aaaaeeeeiiiioooouuuunc------";
  for (var i=0, l=from.length ; i<l ; i++) {
    str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
  }

  str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
    .replace(/\s+/g, '-') // collapse whitespace and replace by -
    .replace(/-+/g, '-'); // collapse dashes

  return str;
}
window.ajxpActionRegisterd = false;
document.observe("ajaxplorer:gui_loaded", function(){
	document.observe("ajaxplorer:user_logged", function(){
		if(ajaxplorer.user && !window.ajxpActionRegistered){
			window.ajxpActionRegistered = true;
			var params = document.location.href.toQueryParams();
			logAjxpBmAction('Downloading '+getBaseName(params['dl_later']));
			var conn = new Connexion();
			//var filename = (new Date().getTime()) +".download";
            var filename = string_to_slug(params['dl_later']) + ".download";
			conn.setParameters({
				action:'mkfile',
				tmp_repository_id:params['tmp_repository_id'],
				dir:params['folder'] || '/',
				filename:filename,
				content:params['dl_later']
			});
			logAjxpBmAction('Creating download file');
			//logAjxpBmAction('Creating download file ' + filename + ' pointing to ' + params['dl_later']);
			conn.sendSync();

            if(params["dl_now"] && params["dl_now"].startsWith("true")){
                window.setTimeout(function(){
                    conn.setMethod('GET');
                    conn.setParameters({
                        action:'external_download',
                        tmp_repository_id:params['tmp_repository_id'],
                        dlfile:(params['folder']?params['folder']+'/':'/')+filename,
                        delete_dlfile:'true',
                        dir:params['folder'] || '/'
                    });
                    logAjxpBmAction('Triggering download in background. This window will close automatically.');
                    conn.onComplete = function(){
                        logAjxpBmAction('Download started');
                        document.location.href="plugins/gui.light/close.html";// Will trigger the onload event to close the frame!!
                    };
                    conn.sendAsync();
                }, 10);
            }
		}
	});	
});