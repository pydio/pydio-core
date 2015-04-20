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

Class.create("AjxpUsersCompleter", Ajax.Autocompleter, {

    createUserEntry : function(isGroup, isTemporary, entryId, entryLabel, skipObservers){
        var spanLabel = new Element("span", {className:"user_entry_label"}).update(entryLabel);
        var li = new Element("div", {className:"user_entry"}).update(spanLabel);
        if(isGroup){
            li.addClassName("group_entry");
        }else if(isTemporary){
            li.addClassName("user_entry_temp");
        }
        li.writeAttribute("data-entry_id", entryId);
        li.insert({bottom:'<span style="display: none;" class="delete_user_entry icon-remove-sign">&nbsp;</span>'});

        if(!skipObservers){
            /*li.setStyle({opacity:0});*/
            li.observe("mouseover", function(event){li.down('span.delete_user_entry').show();});
            li.observe("mouseout", function(event){li.down('span.delete_user_entry').hide();});
            li.down("span.delete_user_entry").observe("click", function(){
                Effect.RowFade(li, {duration:0.3, afterFinish:li.remove.bind(li)});
            });
            li.appendToList = function(htmlObject){
                htmlObject.insert({bottom:li});
                Effect.RowAppear(li, {duration:0.3});
            };
        }

        return li;

    },

    initialize: function(textElement, listElement, update, options) {

        var entryTplGenerator = this.createUserEntry.bind(this);

        if(!options.indicator){
            // ADD INDICATOR;
        }
        if(!options.minChars){
            options.minChars = 3;
        }
        if(options.tmpUsersPrefix){
            var pref = options.tmpUsersPrefix;
        }
        if(options.updateUserEntryAfterCreate){
            var entryTplUpdater = options.updateUserEntryAfterCreate;
        }
        if(options.createUserPanel){
            var createUserPanel = options.createUserPanel.panel;
            var createUserPass = options.createUserPanel.pass;
            var createUserConfirmPass = options.createUserPanel.confirmPass;
        }else{
            var createActionPanel = new Element('div', {id:'create_sub_user'}).update('<div class="dialogContentMainTitle">'+MessageHash[484]+'</div>');
        }

        if(listElement && ajaxplorer.actionBar.actions.get('user_team_create')){
            var butt = new Element('span', {className:'icon-save user_team_save', 'data-simpleTooltipTitle':MessageHash[509]});
            listElement.insert({after:butt});
            modal.simpleTooltip(butt, '', 'top center', 'down_arrow_tip', 'element');
            butt.observe('click', function(){
                var label = window.prompt(MessageHash[510]);
                if(!label) return;
                var params = $H({
                    'user_ids[]':[],
                    team_label:label,
                    get_action:'user_team_create'
                });
                listElement.select('div.user_entry').each(function(el){
                    params.get('user_ids[]').push(el.readAttribute('data-entry_id'));
                });
                var c = new Connexion();
                c.setParameters(params);
                c.sendAsync();
            });
        }

        options = Object.extend(
        {
            paramName:'value',
            tokens:[',', '\n'],
            frequency:0.7,
            tmpUsersPrefix:'',
            updateUserEntryAfterCreate:null,
            createUserPanel:null,
            usersOnly: false,
            existingOnly: false,
            afterUpdateElement: function(element, selectedLi){
                if(listElement){

                    var id = Math.random();
                    var label = selectedLi.getAttribute("data-label");
                    var entryId = selectedLi.getAttribute("data-entry_id");
                    if(selectedLi.getAttribute("data-temporary") && pref && ! label.startsWith(pref)){
                        label = pref + label;
                    }
                    var li = entryTplGenerator(selectedLi.getAttribute("data-group")?true:false,
                        selectedLi.getAttribute("data-temporary")?true:false,
                        selectedLi.getAttribute("data-group")?selectedLi.getAttribute("data-group"):(entryId ? entryId : label),
                        label
                    );

                    if(entryTplUpdater){
                        entryTplUpdater(li);
                    }

                    if(selectedLi.getAttribute("data-temporary") && createUserPanel != null){
                        element.readOnly = true;
                        createUserPass.setValue(""); createUserConfirmPass.setValue("");
                        element.setValue(MessageHash["449"].replace("%s", label));
                        createUserPanel.select('div.dialogButtons>input').invoke("addClassName", "dialogButtons");
                        createUserPanel.select('div.dialogButtons>input').invoke("stopObserving", "click");
                        createUserPanel.select('div.dialogButtons>input').invoke("observe", "click", function(event){
                            Event.stop(event);
                            var close = false;
                            if(event.target.name == "ok"){
                                if( !createUserPass.value || createUserPass.value.length < parseInt(window.ajaxplorer.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"))){
                                    alert(MessageHash[378]);
                                }else if(createUserPass.getValue() == createUserConfirmPass.getValue()){
                                    li.NEW_USER_PASSWORD = createUserPass.getValue();
                                    li.appendToList(listElement);
                                    close = true;
                                }
                            }else if(event.target.name.startsWith("can")){
                                close = true;
                            }
                            if(close) {
                                element.setValue("");
                                element.readOnly = false;
                                Effect.BlindUp('create_shared_user', {duration:0.4});
                                createUserPanel.select('div.dialogButtons>input').invoke("removeClassName", "dialogButtons");
                            }
                        });
                        Effect.BlindDown(createUserPanel, {duration:0.6, transition:Effect.Transitions.spring, afterFinish:function(){createUserPass.focus();}});

                    }else if(selectedLi.getAttribute("data-temporary") && createActionPanel){

                        element.readOnly = true;
                        createActionPanel.update('');
                        var params = $A(ajaxplorer.getPluginConfigs('conf').get('NEWUSERS_EDIT_PARAMETERS').split(','));
                        for(var i=0;i<params.length;i++){
                            params[i] = "user/preferences/pref[@exposed]|//param[@name='"+params[i]+"']";
                        }
                        var f = new FormManager();
                        var def1 = $A();
                        def1.push($H({
                            description: MessageHash['533'],
                            editable: false,
                            expose: "true",
                            label: MessageHash['522'],
                            name: "new_user_id",
                            scope: "user",
                            type: "string",
                            mandatory: "true"
                        }),$H({
                            description: MessageHash['534'],
                            editable: "true",
                            expose: "true",
                            label: MessageHash['523'],
                            name: "new_password",
                            scope: "user",
                            type: "password-create",
                            mandatory: "true"
                        }),$H({
                            description: MessageHash['536'],
                            editable: "true",
                            expose: "true",
                            label: MessageHash['535'],
                            name: "send_email",
                            scope: "user",
                            type: "boolean",
                            "default": "",
                            mandatory: true
                        }));
                        var definitions = f.parseParameters(ajaxplorer.getXmlRegistry(), params.join('|'));
                        definitions.each(function(el){ def1.push(el); });
                        var defaultValues = $H();
                        defaultValues.set('lang', ajaxplorer.currentLanguage);
                        defaultValues.set('new_user_id', label);
                        defaultValues.set('new_password', '');
                        f.createParametersInputs(createActionPanel, def1, true, defaultValues, false, true);

                        var parent = listElement.up('div.dialogContent') || listElement.up('div');
                        modal.showSimpleModal(parent, createActionPanel, function(){
                            var params = $H();
                            var f = new FormManager();
                            var missing = f.serializeParametersInputs(createActionPanel, params, 'NEW_');
                            if(missing){
                                return false;
                            }
                            var conn = new Connexion();
                            params.set("get_action", "user_create_user");
                            conn.setParameters(params);
                            conn.setMethod("POST");
                            var success = false;
                            conn.onComplete = function(transport){
                                if(transport.responseText == 'SUCCESS'){
                                    var id = createActionPanel.down('[name="new_user_id"]').getValue();
                                    var label = id;
                                    if(createActionPanel.down('[name="USER_DISPLAY_NAME"]')){
                                        label = createActionPanel.down('[name="USER_DISPLAY_NAME"]').getValue();
                                        if(!label) label = id;
                                    }
                                    element.setValue("");
                                    var li = entryTplGenerator(false,
                                        false,
                                        id,
                                        label
                                    );
                                    if(entryTplUpdater){
                                        entryTplUpdater(li);
                                    }
                                    li.appendToList(listElement);
                                    success = true;
                                }else{
                                    ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                                    success = false;
                                }
                            };
                            conn.sendSync();
                            if(success) {
                                element.readOnly = false;
                            }
                            return success;

                        }, function(){
                            element.setValue("");
                            element.readOnly = false;
                            return true;
                        });

                    }else{

                        element.setValue("");
                        li.appendToList(listElement);

                    }

                }
            }
        }, options);

        this.baseInitialize(textElement, update, options);
        this.options.asynchronous  = true;
        this.options.onComplete    = this.onComplete.bind(this);
        this.options.defaultParams = this.options.parameters || null;
        this.url                   = this.options.url || window.ajxpServerAccessPath + "&get_action=user_list_authorized_users";
        if(this.options.usersOnly){
            this.url += '&users_only=true';
        }
        if(this.options.existingOnly){
            this.url += '&existing_only=true';
        }

        if(listElement){
            this.options.onComplete  = function(transport){
                var tmpElement = new Element('div');
                tmpElement.update(transport.responseText);
                listElement.select("div.user_entry").each(function(li){
                    var found = tmpElement.down('[data-label="'+li.getAttribute("data-entry_id")+'"]');
                    if(found) {
                        found.remove();
                    }
                });
                this.updateChoices(tmpElement.innerHTML);
            }.bind(this);
        }
        if(Prototype.Browser.IE){
            $(document.body).insert(update);
        }
        textElement.observe("click", function(){
            this.activate();
        }.bind(this));
    }
});