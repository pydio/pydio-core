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
 *
 */
Class.create("WebDAVprefsEditor", AjxpPane, {

    initialize: function($super, element, options){
        $super(element, options);

        if(!ajaxplorer.user) return;
        attachMobileScroll(element.down('.fit_me_to_bottom'));
        var cont = element.down('#webdav_repo_list');
        cont.update('');
        var activator = element.down("#webdav_activator");
        element.down('#webdav_password').observe("focus", function(){ajaxplorer.disableAllKeyBindings()});
        element.down('#webdav_password').observe("blur", function(){ajaxplorer.enableAllKeyBindings()});

        var even = false;
        var conn = new Connexion();
        conn.setParameters(new Hash({get_action:'webdav_preferences'}));
        conn.onComplete = function(transport){
            ajaxplorer.webdavCurrentPreferences = transport.responseJSON;
            activator.checked = ajaxplorer.webdavCurrentPreferences.webdav_active;
            if(activator.checked && !ajaxplorer.webdavCurrentPreferences.digest_set
                && !ajaxplorer.webdavCurrentPreferences.webdav_force_basic) {
                element.down('#webdav_password_form').show();
            }
            ajaxplorer.user.getRepositoriesList().each(function(pair){
                if(ajaxplorer.webdavCurrentPreferences.webdav_repositories[pair.key]){
                    var div = new Element('div', {className:(even?'even':'')});
                    div.update('<span>'+pair.value.label+'</span><input readonly type="text" value="'+ ajaxplorer.webdavCurrentPreferences.webdav_repositories[pair.key] +'">' );
                    cont.insert(div);
                    even = !even;
                }
            });
            element.down('#webdav_main_access').setValue(ajaxplorer.webdavCurrentPreferences.webdav_base_url);
            element.down('#webdav_main_access').observe("click", function(){
                element.down('#webdav_main_access').select();
            });
            element.down('#perworkspace-urls-toggle').observe("click", function(event){
                element.down('#webdav_repo_list').toggle();
                var span = element.down('#perworkspace-urls-toggle').down("span");
                var open = span.hasClassName("icon-caret-right");
                span.removeClassName(open ? "icon-caret-right" : "icon-caret-down");
                span.addClassName(!open ? "icon-caret-right" : "icon-caret-down");
            }.bind(this));

            //element.down('input[name="ok"]').observe("click", hideLightBox);
            if(!activator.hasObserver){
                activator.observe("change", function(e){
                    var checked = activator.checked;
                    var conn = new Connexion();
                    conn.setParameters(new Hash({
                        get_action:'webdav_preferences',
                        activate:(checked?'true':'false')
                    }));
                    conn.onComplete = function(transport){
                        ajaxplorer.webdavCurrentPreferences = transport.responseJSON;
                        if(ajaxplorer.webdavCurrentPreferences.webdav_active){
                            if(!ajaxplorer.webdavCurrentPreferences.digest_set
                                && ajaxplorer.webdavCurrentPreferences.webdav_force_basic) {
                                element.down('#webdav_password_form').show();
                            }
                            ajaxplorer.displayMessage("SUCCESS", MessageHash[408]);
                        }else {
                            element.down('#webdav_password_form').hide();
                            ajaxplorer.displayMessage("SUCCESS", MessageHash[409]);
                        }
                    };
                    conn.sendAsync();
                });
                if(!ajaxplorer.webdavCurrentPreferences.digest_set){
                    element.down('#webdav_pass_saver').observe("click", function(){
                        var conn = new Connexion();
                        conn.setMethod('POST');
                        conn.setParameters(new Hash({
                            get_action:'webdav_preferences',
                            webdav_pass: element.down('#webdav_password').value
                        }));
                        conn.onComplete = function(transport){
                            ajaxplorer.displayMessage("SUCCESS", MessageHash[410]);
                        };
                        conn.sendAsync();
                    });
                }
                activator.hasObserver = true;
            }
        };
        conn.sendAsync();


    },

    resize: function($super){
        $super();
        fitHeightToBottom(this.htmlElement.down('.fit_me_to_bottom'));
    }


});