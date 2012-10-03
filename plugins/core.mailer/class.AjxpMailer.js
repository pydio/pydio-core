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

    initialize : function(){
        var res = new ResourcesManager();
        res.loadCSSResource("plugins/core.mailer/AjxpMailer.css");
    },

    buildMailPane:function(subject, body, recipientsList){
        if(!$("mailer_message")){
            var recipientString = '';
            if(recipientsList){
                recipientsList.select("span.user_entry_label").each(function(el){
                    recipientString += el.innerHTML + ", ";
                });
            }
            var fromString = ajaxplorer.user.id;
            $(document.body).insert("<div id='mailer_message'><div class='message_body'>" +
                "<input type='text' name='from' value='From :"+fromString+"'/>" +
                "<input type='text' name='to' value='To      :"+recipientString+"'/>" +
                "<textarea>"+body+"</textarea>" +
                "</div></div>");
        }
        return $("mailer_message");
    }

});