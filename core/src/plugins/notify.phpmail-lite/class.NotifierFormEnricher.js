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
 *
 * This is the main configuration file for configuring the basic plugins the application
 * needs to run properly : an Authentication plugin, a Configuration plugin, and a Logger plugin.
 */
Class.create("NotifierFormEnricher", {

    initialize : function(){
        document.observe("ajaxplorer:beforeApply-share", function(){
            this.enrichShareFolderForm();
        }.bind(this));
    },

    enrichShareFolderForm : function(){
        if($("share_folder_form") && $("share_folder_form").down("fieldset#notification_fieldset")){
            return;
        }
        $("share_folder_form").down("fieldset").insert({after:'<fieldset id="notification_fieldset">\
							<legend ajxp_message_id="357">Notification</legend>\
							<div class="dialogLegend" ajxp_message_id="358">Check the box if you want to be notified when files are uploaded/downloaded, and add other emails if necessary</div>\
							<div class="SF_element">\
								<div class="SF_label" ajxp_message_id="359">Test Custom : </div>\
								<input type="text" value="" id="repo_label" name="PLUGINS_DATA_NOTIFICATION_EMAIL" class="SF_input"/>\
							</div>\
						</fieldset>\
        '});
    }

});

if(!window.notifierTool){
    window.notifierTool = new NotifierFormEnricher();
}