(function(global){

    let ProfilePane = React.createClass({

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
                        <h3 style={{paddingLeft:20}}>
                            {pydio.MessageHash['user_dash.43']}
                            <div className="legend">{pydio.MessageHash['user_dash.43t']}</div>
                        </h3>
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

    let WebDAVURL = React.createClass({

        propTypes:{
            url: React.PropTypes.string,
            label: React.PropTypes.string,
            getMessage: React.PropTypes.func
        },

        componentDidUpdate: function(prevProps, prevState){
            this.attachClipboard();
        },

        componentDidMount: function(){
            this.attachClipboard();
        },

        componentWillUnmount: function(){
            this.detachClipboard();
        },

        detachClipboard: function(){
            if(this._clip){
                this._clip.destroy();
            }
        },

        clearCopyMessage:function(){
            global.setTimeout(function(){
                this.setState({copyMessage:''});
            }.bind(this), 5000);
        },

        attachClipboard: function(){
            this.detachClipboard();
            if(this.refs['copy-button']){
                this._clip = new Clipboard(this.refs['copy-button'].getDOMNode(), {
                    text: function(trigger) {
                        return this.props.url;
                    }.bind(this)
                });
                this._clip.on('success', function(){
                    this.setState({copyMessage:this.props.getMessage('share_center.192')}, this.clearCopyMessage);
                }.bind(this));
                this._clip.on('error', function(){
                    var copyMessage;
                    if( global.navigator.platform.indexOf("Mac") === 0 ){
                        copyMessage = this.props.getMessage('share_center.144');
                    }else{
                        copyMessage = this.props.getMessage('share_center.143');
                    }
                    this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
                }.bind(this));
            }
        },

        render: function(){
            let copy;
            if(this.state && this.state.copyMessage){
                copy = <div className="copy_legend">{this.state.copyMessage}</div>;
            }
            return (
                <div>
                    <div className="dav-url">
                        <ReactMUI.TextField floatingLabelText={this.props.label} value={this.props.url}/>
                        <ReactMUI.IconButton iconClassName="icon-paste" tooltip={this.props.getMessage('share_center.191')} ref="copy-button"/>
                    </div>
                    {copy}
                </div>
            );
        }

    });

    let WebDAVPane = React.createClass({

        componentDidMount: function(){
            this.props.pydio.UI.disableAllKeyBindings();
            this.loadPrefs();
        },
        componentWillUnmount: function(){
            this.props.pydio.UI.enableAllKeyBindings();
        },
        getMessage: function(id){
            return this.props.pydio.MessageHash[id];
        },
        onToggleChange: function(event, newValue){
            PydioApi.getClient().request({
                get_action : 'webdav_preferences',
                activate   : newValue ? "true":"false"
            }, function(t){
                this.setState({preferences: t.responseJSON});
                this.props.pydio.displayMessage("SUCCESS", this.props.pydio.MessageHash[newValue?408:409]);
            }.bind(this));
        },
        savePassword: function(event){
            PydioApi.getClient().request({
                get_action : 'webdav_preferences',
                webdav_pass: this.refs['passfield'].getValue()
            }, function(t){
                this.setState({preferences: t.responseJSON});
                this.props.pydio.displayMessage("SUCCESS", this.props.pydio.MessageHash[410]);
            }.bind(this));
        },
        loadPrefs: function(){
            if(!this.isMounted()) return;
            PydioApi.getClient().request({
                get_action:'webdav_preferences'
            }, function(t){
                this.setState({preferences: t.responseJSON});
            }.bind(this));
        },

        renderPasswordField: function(){

            if(this.state.preferences.digest_set || !this.state.preferences.webdav_force_basic){
                return null;
            }
            return (
                <div>
                    <ReactMUI.TextField
                        type="password"
                        floatingLabelText={this.getMessage(523)}
                        ref="passfield"
                    />&nbsp;&nbsp;&nbsp;
                    <ReactMUI.RaisedButton
                        label="Save"
                        onClick={this.savePassword}
                    />
                    <div className="dav-password-legend">{this.getMessage(407)}</div>
                </div>
            );
        },

        renderURLList: function(){

            let base = this.state.preferences.webdav_base_url;
            let userRepos = this.props.pydio.user.getRepositoriesList();
            let webdavRepos = this.state.preferences.webdav_repositories;
            let otherUrls = [];
            if(!!this.state.toggler){
                userRepos.forEach(function(repo, key){
                    if(!webdavRepos[key]) return;
                    otherUrls.push(<WebDAVURL key={key} label={repo.getLabel()} url={webdavRepos[key]} getMessage={this.getMessage} />);
                }.bind(this));
            }

            let toggler = function(){
                this.setState({toggler: !this.state.toggler});
            }.bind(this);

            return (
                <div>
                    <h4>WebDAV shares adresses</h4>
                    <div>{this.getMessage(405)}</div>
                    <WebDAVURL label={this.getMessage(468)} url={base} getMessage={this.getMessage}/>
                    <div style={{paddingTop: 20}}>
                        <ReactMUI.Checkbox label={this.getMessage(465)} onCheck={toggler} checked={!!this.state.toggler}/>
                    </div>
                    {otherUrls}
                </div>
            );

        },
        render: function(){
            let webdavActive = this.state && this.state.preferences.webdav_active;
            return (
                <div className="react-mui-context">
                    <div className="title-flex">
                        <h3 style={{paddingLeft:20}}>
                            {this.getMessage(403)}
                            <div className="legend">{this.getMessage(404)}</div>
                        </h3>
                    </div>
                    <div style={{padding:20}}>
                        <ReactMUI.Toggle
                            label={this.getMessage(406)}
                            toggled={webdavActive}
                            onToggle={this.onToggleChange}/>
                        <div style={{paddingTop: 20}}>
                            {webdavActive ? this.renderPasswordField() : null}
                            {webdavActive ? this.renderURLList() : null}
                        </div>
                    </div>
                </div>
            );
        }

    });

    /**
     * Add to tabs - Requires MessagesProviderMixin & DND Context...
     */
    let UsersPane = React.createClass({

        render: function(){

            return (
                <PydioComponents.NodeListCustomProvider
                    nodeProviderProperties={{get_action:'ls', dir:'users', tmp_repository_id:'ajxp_user'}}
                    elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                />
            );

        }

    });

    let Dashboard = React.createClass({
        
        render: function(){

            return (
                <MaterialUI.MuiThemeProvider>
                    <MaterialUI.Tabs>
                        <MaterialUI.Tab label="Profile">
                            <ProfilePane {...this.props}/>
                        </MaterialUI.Tab>
                        <MaterialUI.Tab label="WebDAV Preferences">
                            <WebDAVPane {...this.props}/>
                        </MaterialUI.Tab>
                    </MaterialUI.Tabs>
                </MaterialUI.MuiThemeProvider>
            );
        }
        
    });

    class Callbacks {

        static delete(){

            var dModel = window.actionManager.getDataModel();
            var mime = dModel.getUniqueNode().getAjxpMime();
            var onLoad = function(oForm){
                $(oForm).down('span[id="delete_message"]').update(MessageHash['user_dash.11']);
                $(oForm).down('input[name="ajxp_mime"]').value = dModel.getUniqueNode().getAjxpMime();
            };
            modal.showDialogForm('Delete', 'delete_shared_form', onLoad, function(){
                var oForm = modal.getForm();
                dModel.updateFormOrUrl(oForm);
                var client = PydioApi.getClient();
                client.submitForm(oForm, true, function(transport){
                    client.parseXmlMessage(transport.responseXML);
                    if(mime == "shared_user" && $('address_book')) $('address_book').ajxpPaneObject.reloadDataModel();
                    else if(mime == "team" && $('team_panel')) $('team_panel').ajxpPaneObject.reloadDataModel();
                });
                hideLightBox(true);
                return false;
            });

        }

    }

    let ns = global.UserAccount || {};
    ns.ProfilePane = ProfilePane;
    ns.WebDAVPane = WebDAVPane;
    ns.Dashboard = Dashboard;
    ns.Callbacks = Callbacks;
    global.UserAccount = ns;


})(window);