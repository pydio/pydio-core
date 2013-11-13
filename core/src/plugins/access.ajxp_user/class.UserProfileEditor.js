Class.create("UserProfileEditor", AjxpPane, {

    _formManager: null,

    initialize: function($super, oFormObject, editorOptions){

        $super(oFormObject, editorOptions);



        if(ajaxplorer.actionBar.getActionByName('custom_data_edit')){

            this._formManager = new FormManager();
            var definitions = this._formManager.parseParameters(ajaxplorer.getXmlRegistry(), "user/preferences/pref[@exposed='true']|//param[contains(@scope,'user') and @expose='true']");
            this._formManager.createParametersInputs(oFormObject, definitions, true, ajaxplorer.user.preferences, false, true);
            this._formManager.disableShortcutsOnForm(oFormObject);

            var saveButton = new Element('a', {}).update('<span class="icon-save"></span> <span>'+MessageHash[53]+'</span>');
            oFormObject.down('.toolbarGroup').insert({top: saveButton});


            saveButton.observe("click", function(){
                var params = $H();
                this._formManager.serializeParametersInputs(oFormObject, params, 'PREFERENCES_');
                var conn = new Connexion();
                params.set("get_action", "custom_data_edit");
                conn.setParameters(params);
                conn.setMethod("POST");
                conn.onComplete = function(transport){
                    ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);
                    document.observeOnce("ajaxplorer:registry_part_loaded", function(event){
                        if(event.memo != "user/preferences") return;
                        ajaxplorer.logXmlUser(ajaxplorer.getXmlRegistry());
                    });
                    ajaxplorer.loadXmlRegistry(false, "user/preferences");
                };
                conn.sendAsync();

            }.bind(this));

        }

        if(ajaxplorer.actionBar.getActionByName('pass_change')){

            var chPassButton = new Element('a', {className:''}).update('<span class="icon-key"></span> <span>'+MessageHash[194]+'</span>');
            oFormObject.down('.toolbarGroup').insert(chPassButton);
            chPassButton.observe("click", function(){
                ajaxplorer.actionBar.getActionByName('pass_change').apply();
            });

        }

    }


});