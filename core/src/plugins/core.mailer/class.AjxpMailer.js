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

Class.create("AjxpMailer", {

    _mailerPane: null,

    initialize : function(){
        var res = new ResourcesManager();
        res.loadCSSResource("plugins/core.mailer/AjxpMailer.css");
    },

    buildMailPane:function(subject, body, recipientsList, paneTitle){
        if(!$("mailer_message")){
            $(document.body).insert("<div id='mailer_message'></div>");
        }
        var recipientString = '';
        var hiddenRecipientString = [];
        var hiddenGroupsString = [];
        if(recipientsList){
            recipientsList.select("div.user_entry").each(function(el){
                recipientString += el.down("span.user_entry_label").innerHTML + ", ";
                if(el.hasClassName("group_entry")){
                    hiddenGroupsString.push(el.getAttribute("data-entry_id"));
                }else{
                    hiddenRecipientString.push(el.getAttribute("data-entry_id"));
                }
            });
            recipientString = recipientString.substring(0, recipientString.length-2);
            hiddenGroupsString = hiddenGroupsString.join(",");
            hiddenRecipientString = hiddenRecipientString.join(",");
        }
        var fromString = ajaxplorer.user.id;
        $("mailer_message").update("<div id='mailer_message'><div class='message_body'><form>" +
            "<div class='grey_gradient_light_inputs mailer_input_line'><span class='mailer_input_label'>From:</span><input class='mailer_input_field' type='text' name='from' value='"+fromString+"'/></div>" +
            "<div class='grey_gradient_light_inputs mailer_input_line'><span class='mailer_input_label'>To:</span><input class='mailer_input_field' type='text' name='to' value='"+recipientString+"'/></div>" +
            "<div class='grey_gradient_light_inputs mailer_input_line'><span class='mailer_input_label'>Subject:</span><input class='mailer_input_field' type='text' name='subject' value='"+subject+"'/></div>" +
            "<textarea name='message' class='grey_gradient_light_inputs'>"+body+"</textarea>" +
            "<input type='hidden' name='users_ids' value='"+ hiddenRecipientString +"'/> " +
            "<input type='hidden' name='groups_ids' value='"+ hiddenGroupsString +"'/> " +
            "</form></div></div>");
        if(paneTitle){
            $("mailer_message").insert({top:new Element("div", {className:"dialogContentMainTitle"}).update(paneTitle)});
        }

        this._mailerPane = $("mailer_message");
        return $("mailer_message");
    },

    postEmail : function(){
        var params = $H({get_action:"send_mail"});
        this._mailerPane.down("form").getElements().each(function(el){
            params.set(el.name, el.getValue());
        });
        var connexion = new Connexion();
        connexion.setMethod("post");
        connexion.setParameters(params);
        connexion.sendSync();
    }

});