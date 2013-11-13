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
Class.create("UserDashboardHome", AjxpPane, {

    _formManager: null,

    initialize: function($super, oFormObject, editorOptions){

        $super(oFormObject, editorOptions);

        oFormObject.down("#welcome").update( MessageHash['user_dash.40'].replace('%s', ajaxplorer.user.getPreference("USER_DISPLAY_NAME")));

        var wsElement = oFormObject.down('#workspaces_list');

        ajaxplorer.user.repositories.each(function(pair){
            var repoId = pair.key;
            var repoObject = pair.value;
            if(repoObject.getAccessType() == 'ajxp_user') return;

            var repoEl = new Element('li').update("<h3>"+repoObject.getLabel() + "</h3><h4>" + repoObject.getDescription()+"</h4>");
            wsElement.insert(repoEl);
            repoEl.observe("click", function(e){
                var target = Event.findElement(e, "li");
                target.nextSiblings().invoke('removeClassName', 'selected');
                target.previousSiblings().invoke('removeClassName', 'selected');
                target.addClassName('selected');
                oFormObject.down('#go_to_ws').removeClassName("disabled");
                oFormObject.down('#save_ws_choice').removeClassName("disabled").disabled = false;
                oFormObject.down('#go_to_ws').CURRENT_REPO_ID = repoId;
            });
        });

        oFormObject.down('#go_to_ws').observe("click", function(e){
            var target = e.target;
            if(!target.CURRENT_REPO_ID) return;
            if(oFormObject.down('#save_ws_choice').checked){
                // Save this preference now!
                var params = $H({
                    'PREFERENCES_DEFAULT_START_REPOSITORY':target.CURRENT_REPO_ID,
                    'get_action':'custom_data_edit'
                });
                var conn = new Connexion();
                conn.setParameters(params);
                conn.setMethod("POST");
                conn.onComplete = function(transport){
                    ajaxplorer.user.setPreference('DEFAULT_START_REPOSITORY', target.CURRENT_REPO_ID, false);
                };
                conn.sendAsync();
            }
            ajaxplorer.triggerRepositoryChange(target.CURRENT_REPO_ID);
        });

        var notifCenter = ajaxplorer.NotificationLoaderInstance;
        var notificationElement = oFormObject.down("#notifications_center");

        if(notifCenter){
            notifCenter.ajxpNode.observe("loaded", function(){
                notifCenter.childrenToMenuItems(function(obj){
                    var a = new Element('li', {title:obj['alt']}).update(obj['name']);
                    notificationElement.insert(a);
                    var img = obj.pFactory.generateBasePreview(obj.ajxpNode);
                    a.IMAGE_ELEMENT = img;
                    a.insert({top:img});
                    obj.pFactory.enrichBasePreview(obj.ajxpNode, a);
                });
            });

            var clicker = function(){
                if(oFormObject.down("#notifications_center").hasClassName('folded')){
                    oFormObject.down("#workspaces_center").setStyle({marginLeft: '15%'});
                    oFormObject.down("#notifications_center").setStyle({width: '30%'});
                }else{
                    oFormObject.down("#workspaces_center").setStyle({marginLeft: '30%'});
                    oFormObject.down("#notifications_center").setStyle({width: '0'});
                }
                oFormObject.down("#notifications_center").toggleClassName('folded');
            };
            oFormObject.down("#close-icon").observe("click", clicker);

            window.setTimeout(clicker, 4000);
        }else{
            oFormObject.down("#workspaces_center").setStyle({marginLeft: '30%'});
            notificationElement.hide();
        }
    },

    resize: function($super, size){

        $super(size);

        fitHeightToBottom(this.htmlElement.down('#workspaces_list'), this.htmlElement, 90);
    }


});