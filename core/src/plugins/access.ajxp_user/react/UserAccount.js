(function(global){

    var ProfilePane = React.createClass({

        componentDidMount: function(){
            this.props.pydio.UI.disableAllKeyBindings();
        },
        componentWillUnmount: function(){
            this.props.pydio.UI.enableAllKeyBindings();
        },

        getInitialState: function(){
            let objValues = {};
            let pydio = this.props.pydio;
            pydio.user.preferences.forEach(function(v, k){
                if(k === 'gui_preferences') return;
                objValues[k] = v;
            });
            return {
                definitions:PydioForm.Manager.parseParameters(pydio.getXmlRegistry(), "user/preferences/pref[@exposed='true']|//param[contains(@scope,'user') and @expose='true']"),
                values:objValues,
                originalValues:LangUtils.deepCopy(objValues),
                dirty: false
            };
        },

        onFormChange: function(newValues, dirty, removeValues){
            this.setState({dirty: dirty, values: newValues});
        },

        getButton: function(actionName, messageId){
            let pydio = this.props.pydio;
            if(!pydio.Controller.getActionByName(actionName)){
                return null;
            }
            let func = function(){
                pydio.Controller.fireAction(actionName);
            };
            return (
                <ReactMUI.RaisedButton label={pydio.MessageHash[messageId]} onClick={func}/>
            );
        },

        revert: function(){
            this.setState({
                values: LangUtils.deepCopy(this.state.originalValues)
            });
        },

        saveForm: function(){
            if(!this.state.dirty){
                this.setState({dirty: false});
                return;
            }
            let pydio = this.props.pydio;
            let postValues = PydioForm.Manager.getValuesForPOST(this.state.definitions, this.state.values, 'PREFERENCES_');
            postValues['get_action'] = 'custom_data_edit';
            PydioApi.getClient().request(postValues, function(transport){
                PydioApi.getClient().parseXmlMessage(transport.responseXML);
                global.document.observeOnce("ajaxplorer:registry_part_loaded", function(event){
                    if(event.memo != "user/preferences") return;
                    pydio.Registry.logXmlUser(false);
                });
                pydio.loadXmlRegistry(false, "user/preferences");
                this.setState({dirty: false});
            });
        },

        render: function(){
            let pydio = this.props.pydio;

            let saveButton = <ReactMUI.RaisedButton disabled={!this.state.dirty} label={pydio.MessageHash[53]} onClick={this.saveForm}/>;
            return (
                <div className="react-mui-context">
                    <div className="title-flex">
                        <h3 style={{paddingLeft:20}}>{pydio.MessageHash['user_dash.43']}</h3>
                        <div className="actionBar">
                            {saveButton}&nbsp;&nbsp;
                            {this.getButton('pass_change', 194)}
                        </div>
                    </div>
                    <PydioForm.FormPanel
                        parameters={this.state.definitions}
                        values={this.state.values}
                        depth={-1}
                        binary_context={"user_id="+pydio.user.id}
                        onChange={this.onFormChange}
                    />
                </div>
            );
        }

    });

    let ns = global.UserAccount || {};
    ns.ProfilePane = ProfilePane;
    global.UserAccount = ns;


})(window);