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

Class.create("AjxpMailer", {

    _mailerPane: null,

    initialize : function(){
        var res = new ResourcesManager();
        res.loadCSSResource("plugins/core.mailer/AjxpMailer.css");
    },

    selectedLoginToSpan: function(loginLabel, entryId, isGroup){
        var field = this._mailerPane.down('#tofield');
        var fieldParent = this._mailerPane.down('.mailer_tofield_line');
        var el = new Element('span', {
            className:'user',
            'data-entry_id':entryId,
            'data-is_group':isGroup
        }).update(loginLabel+'<span class="icon-remove"></span>');

        field.insert({before: el});
        el.down('.icon-remove').observe('click', el.remove.bind(el));
        field.setValue('');
        var offset = Element.positionedOffset(field);
        var height = fieldParent.getHeight() - parseInt(fieldParent.getStyle('marginBottom')) - parseInt(fieldParent.getStyle('marginTop')) - 5;
        if(!fieldParent.ORIGINAL_HEIGHT) fieldParent.ORIGINAL_HEIGHT = fieldParent.getHeight();
        if(offset.top >= height ){
            fieldParent.setStyle({height: (parseInt(fieldParent.getHeight()) + fieldParent.ORIGINAL_HEIGHT ) + 'px'});
        }else if(offset.top < (height - fieldParent.ORIGINAL_HEIGHT) ){
            fieldParent.setStyle({height: (parseInt(fieldParent.getHeight()) - fieldParent.ORIGINAL_HEIGHT ) + 'px'});
        }
        field.focus();
    },

    buildMailPane:function(subject, body, recipientsList, paneTitle, downloadLink){
        if(!$("mailer_message")){
            $(document.body).insert("<div id='mailer_message'></div>");
        }
        var fromString = ajaxplorer.user.id;
        $("mailer_message").update("<div id='mailer_message' style='position: relative;'><div id='emails_autocomplete' style='position:absolute;z-index:1200;'></div><div class='message_body'><form>" +
            "<div class='grey_gradient_light_inputs mailer_input_line' style='display: none;'><span class='mailer_input_label'>"+MessageHash['core.mailer.4']+":</span><input class='mailer_input_field' type='text' name='from' value='"+fromString+"'/></div>" +
            "<div class='grey_gradient_light_inputs mailer_input_line mailer_tofield_line'><span class='mailer_input_label' style='float: left;'>"+MessageHash['core.mailer.5']+":</span><input placeholder='"+MessageHash['core.mailer.8']+"' class='mailer_input_field' type='text' name='to' id='tofield' value=''/></div>" +
            "<div class='grey_gradient_light_inputs mailer_input_line'><span class='mailer_input_label'>"+MessageHash['core.mailer.6']+":</span><input class='mailer_input_field' type='text' name='subject' value='"+subject+"'/></div>" +
            "<textarea name='message' class='grey_gradient_light_inputs'>"+body+"</textarea>" +
            "</form></div></div>");
        if(paneTitle){
            $("mailer_message").insert({top:new Element("div", {className:"dialogContentMainTitle"}).update(paneTitle)});
        }

        this._mailerPane = $("mailer_message");
        this.downloadLink = downloadLink;

        if(recipientsList){
            recipientsList.select("div.user_entry").each(function(el){
                var loginLabel = el.down("span.user_entry_label").innerHTML;
                this.selectedLoginToSpan(
                    loginLabel,
                    el.getAttribute("data-entry_id") || el.getAttribute('data-group') ,
                    el.hasClassName("complete_group_entry")
                );
            }.bind(this));
        }

        this._autocompleter = new AjxpUsersCompleter(
            this._mailerPane.down('#tofield'),
            null,
            this._mailerPane.down('#emails_autocomplete'),
            {
                tmpUsersPrefix:'',
                usersOnly: true,
                existingOnly: true,
                updateUserEntryAfterCreate:null,
                createUserPanel:null,
                indicator: null,
                minChars:parseInt(ajaxplorer.getPluginConfigs("conf").get("USERS_LIST_COMPLETE_MIN_CHARS")),
                afterUpdateElement: function(elem, selectedLi){
                    this.selectedLoginToSpan(
                        selectedLi.readAttribute('data-label'),
                        selectedLi.readAttribute('data-entry_id') || selectedLi.readAttribute('data-group') ,
                        selectedLi.hasClassName('complete_group_entry'));
                }.bind(this)
            }
        );

        return $("mailer_message");
    },

    postEmail : function(){
        var toField = this._mailerPane.down('#tofield');
        if(toField.getValue()){
            this.selectedLoginToSpan( toField.getValue(), toField.getValue() , false);
        }
        var params = $H({get_action:"send_mail"});
        this._mailerPane.down("form").getElements().each(function(el){
            params.set(el.name, el.getValue());
        });
        var aa = [];
        this._mailerPane.down(".mailer_tofield_line").select('span.user').each(function(el){
            aa.push(el.readAttribute('data-entry_id'));
        });
        params.set('emails[]', aa);
        if(this.downloadLink){
            params.set('link', this.downloadLink);
        }
        var connexion = new Connexion();
        connexion.setMethod("post");
        connexion.setParameters(params);
        connexion.onComplete = function(transport){
            ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
        };
        connexion.sendSync();
    }

});