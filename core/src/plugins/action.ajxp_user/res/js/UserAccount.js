(function(global){

    class ComponentConfigParser {

        static getAccountTabs(pydio){

            return XMLUtils.XPathSelectNodes(pydio.getXmlRegistry(), 'client_configs/component_config[@className="UserAccountTabs"]/additional_tab').map(function(node){
                return {
                    id: node.getAttribute("id"),
                    tabInfo: JSON.parse(node.getAttribute('tabInfo')),
                    paneInfo: JSON.parse(node.getAttribute('paneInfo'))
                };

            });

        }

    }

    let PasswordForm = React.createClass({

        getInitialState: function(){
            return {error: null, old: '', newPass: ''};
        },

        getMessage: function(id){
            return this.props.pydio.MessageHash[id];
        },

        update: function(value, field){
            let newStatus = {}
            newStatus[field] = value;
            this.setState(newStatus, () => {
                let status = this.validate();
                if(this.props.onValidStatusChange){
                    this.props.onValidStatusChange(status);
                }
            });
        },

        validate: function(){
            if(!this.refs.newpass.isValid()){
                return false;
            }
            const {oldPass, newPass} = this.state;
            if(!oldPass || !newPass){
                this.setState({error: this.getMessage(239)});
                return false;
            }
            if(newPass.length < parseInt(this.props.pydio.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"))){
                this.setState({error: this.getMessage(378)});
                return false;
            }
            this.setState({error: null});
            return true;
        },

        post: function(callback){
            const {oldPass, newPass} = this.state;
            let logoutString = '';
            if(this.props.pydio.user.lock) {
                logoutString =  ' ' + this.getMessage(445);
            }
            PydioApi.getClient().request({
                get_action:'pass_change',
                old_pass: oldPass,
                new_pass: newPass,
                pass_seed: '-1'
            }, function(transport){

                if(transport.responseText === 'PASS_ERROR'){

                    this.setState({error: this.getMessage(240)});
                    callback(false);

                }else if(transport.responseText === 'SUCCESS'){

                    this.props.pydio.displayMessage('SUCCESS', this.getMessage(197) + logoutString);
                    callback(true);
                    if(logoutString) {
                        this.props.pydio.getController().fireAction('logout');
                    }
                }

            }.bind(this));
        },

        render: function(){

            const messages = this.props.pydio.MessageHash;
            let legend;
            if(this.state.error){
                legend = <div className="error">{this.state.error}</div>;
            } else if(this.props.pydio.user.lock){
                legend = <div>{messages[444]}</div>
            }
            let oldChange = (event, newV) => {this.update(newV, 'oldPass')};
            let newChange = (newV, oldV) => {this.update(newV, 'newPass')};
            return(
                <div style={this.props.style}>
                    {legend}
                    <div>
                        <form autoComplete="off">
                        <MaterialUI.TextField
                            onChange={oldChange}
                            type="password"
                            value={this.state.oldPass}
                            ref="old"
                            floatingLabelText={messages[237]}
                            autoComplete="off"
                        />
                        </form>
                    </div>
                    <div style={{width:250}}>
                        <PydioForm.ValidPassword
                            onChange={newChange}
                            attributes={{name:'pass',label:messages[199]}}
                            value={this.state.newPass}
                            name="newpassword"
                            ref="newpass"
                        />
                    </div>
                </div>
            );

        }

    });

    let ProfilePane = React.createClass({

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
                this.props.pydio.observeOnce("registry_part_loaded", function(event){
                    if(event.memo != "user/preferences") return;
                    pydio.Registry.logXmlUser(false);
                });
                pydio.loadXmlRegistry(false, "user/preferences");
                this.setState({dirty: false});
            }.bind(this));
        },

        passOpenPopover: function(event){
            this.setState({passOpen: true, passAnchor:event.currentTarget});
        },

        passClosePopover: function(){
            this.setState({passOpen: false});
        },

        passValidStatusChange: function(status){
            this.setState({passValid: status});
        },

        passSubmit: function(){
            this.refs.passwordForm.post(function(value){
                if(value) this.passClosePopover();
            }.bind(this));
        },

        render: function(){
            let pydio = this.props.pydio;
            let passButton;
            if(pydio.Controller.getActionByName('pass_change')){
                passButton = (
                    <div style={{marginLeft: 8}}>
                        <MaterialUI.RaisedButton
                            onTouchTap={this.passOpenPopover}
                            label={this.props.pydio.MessageHash[194]}
                            primary={true}
                        />
                        <MaterialUI.Popover
                            open={this.state.passOpen}
                            anchorEl={this.state.passAnchor}
                            anchorOrigin={{horizontal: 'right', vertical: 'bottom'}}
                            targetOrigin={{horizontal: 'right', vertical: 'top'}}
                            onRequestClose={this.passClosePopover}
                        >
                            <div>
                                <PasswordForm
                                    style={{padding:10, backgroundColor:'#fafafa'}}
                                    pydio={this.props.pydio}
                                    ref="passwordForm"
                                    onValidStatusChange={this.passValidStatusChange}
                                />
                                <MaterialUI.Divider/>
                                <div style={{textAlign:'right', padding: '8px 0'}}>
                                    <MaterialUI.FlatButton label="Cancel" onTouchTap={this.passClosePopover}/>
                                    <MaterialUI.FlatButton disabled={!this.state.passValid} label="Ok" onTouchTap={this.passSubmit}/>
                                </div>
                            </div>
                        </MaterialUI.Popover>
                    </div>
                );
            }

            return (
                <div>
                    <div style={{display:'flex', padding: 8}}>
                        <ReactMUI.RaisedButton disabled={!this.state.dirty} label={pydio.MessageHash[53]} onClick={this.saveForm}/>
                        <div style={{flex:1}}></div>
                        {passButton}
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
            if(this.refs['copy-button']) {
                this._clip = new Clipboard(ReactDOM.findDOMNode(this.refs['copy-button']), {
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
            this.loadPrefs();
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
                <div>
                    <div className="legend">{this.getMessage(404)}</div>
                    <div style={{padding:20}}>
                        <MaterialUI.Toggle
                            labelPosition="right"
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

    let UsersPane = React.createClass({

        render: function(){

            return (
                <PydioComponents.NodeListCustomProvider
                    nodeProviderProperties={{
                        get_action:'ls',
                        dir:'users',
                        tmp_repository_id:'ajxp_user'
                    }}
                    elementHeight={PydioComponents.SimpleList.HEIGHT_ONE_LINE}
                    style={{maxWidth:720}}
                    actionBarGroups={['change', 'address_book']}
                />
            );

        }

    });

    let ModalDashboard = React.createClass({

        mixins: [
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: '',
                dialogSize: 'lg',
                dialogPadding: false,
                dialogIsModal: false,
                dialogScrollBody: false
            };
        },

        submit: function(){
            this.dismiss();
        },

        render: function(){

            let tabs = [
                (<MaterialUI.Tab key="account" label={this.props.pydio.MessageHash['user_dash.43']} icon={<MaterialUI.FontIcon className="mdi mdi-account"/>}>
                    <ProfilePane {...this.props}/>
                </MaterialUI.Tab>)
            ];

            ComponentConfigParser.getAccountTabs(this.props.pydio).map(function(tab){
                tabs.push(
                    <MaterialUI.Tab key={tab.id} label={this.props.pydio.MessageHash[tab.tabInfo.label]} icon={<MaterialUI.FontIcon className={tab.tabInfo.icon}/>}>
                        <PydioReactUI.AsyncComponent
                            {...tab.paneInfo}
                            pydio={this.props.pydio}
                        />
                    </MaterialUI.Tab>
                );
            }.bind(this));

            return (
                <MaterialUI.Tabs
                    style={{display:'flex', flexDirection:'column', width:'100%'}}
                    tabItemContainerStyle={{minHeight:72}}
                    contentContainerStyle={{overflowY:'auto', minHeight: 350}}
                >
                    {tabs}
                </MaterialUI.Tabs>
            );

        }

    });

    class Callbacks {

        static openDashboard(){
            ResourcesManager.loadClassesAndApply(['PydioForm'], function(){
                global.pydio.UI.openComponentInModal('UserAccount', 'ModalDashboard');
            });
        }

        static openAddressBook(){
            global.pydio.UI.openComponentInModal('AddressBook', 'Modal');
        }

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

    const FakeDndBackend = function(){
        return{
            setup:function(){},
            teardown:function(){},
            connectDragSource:function(){},
            connectDragPreview:function(){},
            connectDropTarget:function(){}
        };
    };


    let ns = global.UserAccount || {};
    ns.ProfilePane = ProfilePane;
    ns.WebDAVPane = WebDAVPane;
    ns.UsersPane = ReactDND.DragDropContext(FakeDndBackend)(UsersPane);
    ns.ModalDashboard = ModalDashboard;
    ns.Callbacks = Callbacks;
    ns.PasswordForm = PasswordForm;
    global.UserAccount = ns;


})(window);
