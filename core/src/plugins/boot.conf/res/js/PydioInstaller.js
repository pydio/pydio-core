(function(global){

    const InstallerDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: "Welcome to Pydio",
                dialogIsModal: true,
                dialogSize:'lg'
            };
        },

        componentDidMount: function() {
            PydioApi.getClient().request({
                get_action:'load_installer_form',
                lang:this.props.pydio.currentLanguage
            }, function(transp){
                let formParameters= PydioForm.Manager.parseParameters(transp.responseXML, '//global_param');
                let groups = new Map();
                let values = {};
                formParameters.map(function(param){
                    if(param.default){
                        values[param.name] = param.default;
                    }
                    if(!param.group) return;
                    let g = param.group;
                    if(!groups.has(g)) {
                        groups.set(g, {
                            title:g,
                            legend:'',
                            valid:true,
                            switches:[]
                        });
                    }
                    if(param.type === 'legend'){
                        groups.get(g).legend = param.description;
                    }else if(param.type.indexOf('group_switch:') === 0){
                        groups.get(g).switches.push(param.type.replace('group_switch:', ''));
                    }

                });

                this.setState({
                    parameters: formParameters,
                    groups: groups,
                    values: values
                });

            }.bind(this));
        },

        getInitialState: function(){
            return {
                welcomeScreen: true,
                stepIndex: 0,
                maxHeight: DOMUtils.getViewportHeight() - 40
            }
        },

        onFormChange: function(values){
            if(values['ADMIN_USER_LOGIN'] !== this.state.values['ADMIN_USER_LOGIN']
                && this.state.values['ADMIN_USER_LOGIN'] === this.state.values['ADMIN_USER_NAME']){
                values['ADMIN_USER_NAME'] = values['ADMIN_USER_LOGIN'];
            }
            if(this.props.onFormChange){
                this.props.onFormChange(values);
            }
            this.setState({values: values});
        },

        handleNext : function(){
            const {stepIndex} = this.state;
            this.setState({
                stepIndex: stepIndex + 1
            });
        },

        handlePrev: function(){
            const {stepIndex} = this.state;
            if (stepIndex > 0) {
                this.setState({stepIndex: stepIndex - 1});
            }
        },

        onValidStatusChange: function(groupKey, status, missingFields){
            // IGNORE SWITCH_GROUP FIELDS UNTIL PROPERLY IMPLEMENTED IN THE FORMS
            let groupMissing = 0;
            let groups = this.state.groups;
            let groupSwitches = groups.get(groupKey).switches;
            missingFields.map(function(field){
                if(field.group && field.group === groupKey && !field.group_switch_name){
                    groupMissing ++;
                }else if(field.group_switch_name && groupSwitches.indexOf(field.group_switch_name) > -1){
                    //groupMissing ++;
                }
            });
            groups.get(groupKey).valid = groupMissing > 0 ? false: true;
            this.setState({groups: groups});
        },

        checkDBPanelValidity: function(){
            let db_type = this.state.values['db_type'];
            if(!db_type) return false;
            let values = this.state.values;
            let params = this.state.parameters;
            let missing = 0;
            params.map(function(p){
                if(p.group_switch_name === 'dibi_provider' && p.group_switch_value === db_type && p.mandatory === "true"
                    && !values['db_type/' + p.name]){
                    missing ++;
                }
            });
            return missing > 0;
        },

        testDBConnection: function(postValues){
            postValues['get_action'] =  'boot_test_sql_connexion';
            PydioApi.getClient().request(postValues, function(transp){
                if(transp.responseText && transp.responseText.indexOf('SUCCESS:') === 0){
                    this.setState({dbTestedSuccesfully:true});
                    this.handleNext();
                }else{
                    this.setState({dbTestFailed:true});
                }
            }.bind(this));
        },

        computeInstallationParams(){

            let allParams = {
                get_action:'apply_installer_form',
                installer_lang:global.pydio.currentLanguage
            };
            for(var key in this.refs){
                if(!this.refs.hasOwnProperty(key) || key.indexOf('form-') !== 0) continue;
                let formPanel = this.refs[key];
                allParams = Object.assign(allParams, formPanel.getValuesForPOST(this.state.values, ''));
            }

            return allParams;
        },

        installPydio: function(){

            let allParams = this.state.installationParams || this.computeInstallationParams();

            PydioApi.getClient().request(allParams, function(transp){
                if(this.props.beforeInstallStep){
                    this.setState({customPanel: null});
                }
                if(transp.responseText && transp.responseText === 'OK'){
                    this.setState({INSTALLED: true});
                    global.setTimeout(function(){
                        global.document.location.reload(true);
                    }, 3000);
                }else if(transp.responseJSON){
                    this.setState({
                        INSTALLED: true,
                        HTACCESS_NOTIF: transp.responseJSON
                    });
                }
            }.bind(this));
        },

        renderStepActions(step, groupKey) {
            const {stepIndex} = this.state;
            let LAST_STEP = (stepIndex === this.state.groups.size - 1);
            let forwardLabel = LAST_STEP ? 'Install Pydio Now' : 'Next';
            let nextDisabled = !this.state.groups.get(groupKey).valid;
            let nextCallback = this.handleNext.bind(this);

            if(this.state.groups.get(groupKey).switches.indexOf('dibi_provider') > -1 && !this.state.dbTestSuccessfully){
                nextDisabled = this.checkDBPanelValidity();
                forwardLabel = this.state.dbTestFailed ? "Cannot connect, try again" : "Test DB Connection";
                nextCallback = function(){
                    let testValues = this.refs['form-' + groupKey].getValuesForPOST(this.state.values);
                    this.testDBConnection(testValues);
                }.bind(this);
            }
            if(LAST_STEP){
                if(this.props.beforeInstallStep){
                    nextCallback = ()=> {
                        this.setState({
                            installationParams:this.computeInstallationParams(),
                            customPanel:this.props.beforeInstallStep
                        })
                    };
                }else{
                    nextCallback = this.installPydio.bind(this);
                }
            }

            if(this.props.renderStepActions){
                let test = this.props.renderStepActions(step, groupKey, LAST_STEP, this.state, nextCallback);
                if(test){
                    return test;
                }
            }

            // For testing purpose, disable validations
            // nextDisabled = false;
            // nextCallback = this.handleNext.bind(this);

            return (
                <div style={{margin: '12px 0'}}>
                    <MaterialUI.RaisedButton
                        label={forwardLabel}
                        disableTouchRipple={true}
                        disableFocusRipple={true}
                        primary={true}
                        onTouchTap={nextCallback}
                        style={{marginRight: 12}}
                        disabled={nextDisabled}
                    />
                    {step > 0 && (
                        <MaterialUI.FlatButton
                            label="Back"
                            disabled={stepIndex === 0}
                            disableTouchRipple={true}
                            disableFocusRipple={true}
                            onTouchTap={this.handlePrev}
                        />
                    )}
                </div>
            );
        },

        switchLanguage: function(event, key, payload){
            global.pydio.fire('language_changed');
            global.pydio.currentLanguage = payload;
            global.pydio.loadI18NMessages(payload);
        },

        render: function(){

            if(this.state.customPanel){

                return this.state.customPanel;

            }else if(!this.state.parameters){

                return <PydioReactUI.Loader/>;

            }else if(this.state.welcomeScreen){

                let languages = [], currentLanguage;
                global.pydio.listLanguagesWithCallback(function(key, label, selected){
                    if(selected) currentLanguage = key;
                    languages.push(<MaterialUI.MenuItem value={key} primaryText={label}/>);
                });

                return (
                    <div id="installer_form">
                        <img className="install_pydio_logo" src="plugins/gui.ajax/PydioLogo250.png" style={{display:'block', margin:'20px auto'}}/>
                        <div className="installerWelcome">{global.pydio.MessageHash['installer.3']}</div>
                        <MaterialUI.SelectField floatingLabelText="Pick your language" value={currentLanguage} onChange={this.switchLanguage}>
                            {languages}
                        </MaterialUI.SelectField>
                        <div>
                            <MaterialUI.RaisedButton label="Start Installation" onTouchTap={()=>{this.setState({welcomeScreen:false});}}/>
                        </div>
                    </div>
                );

            }else if(this.state.INSTALLED){

                if(this.state.HTACCESS_NOTIF){

                    return <div>Pydio Installation succeeded, but we could not successfully edit the .htaccess file.<br/>
                        Please update the file <em>{this.state.HTACCESS_NOTIF.file}</em> !
                        After applying this, just reload the page and can log in with
                        the admin user {this.state.values['ADMIN_USER_LOGIN']} you have just defined.</div>;

                }else{
                    return <div>Pydio Installation succeeded! The page will now reload automatically. You can log in with
                        the admin user {this.state.values['ADMIN_USER_LOGIN']} you have just defined. The page with reload automatically in 3s.</div>;
                }

            }

            const {stepIndex} = this.state;

            let forms = [], index = 0;
            this.state.groups.forEach(function(gData, groupKey){
                forms.push(
                    <MaterialUI.Step>
                        <MaterialUI.StepLabel>{gData.title}</MaterialUI.StepLabel>
                        <MaterialUI.StepContent style={{maxWidth:420}}>
                            <PydioForm.FormPanel
                                key={groupKey}
                                ref={"form-" + groupKey}
                                className="stepper-form-panel"
                                parameters={this.state.parameters}
                                values={this.state.values}
                                onChange={this.onFormChange}
                                disabled={false}
                                limitToGroups={[groupKey]}
                                skipFieldsTypes={['legend', 'GroupHeader']}
                                depth={-1}
                                onValidStatusChange={this.onValidStatusChange.bind(this, groupKey)}
                                forceValidStatusCheck={true}
                            />
                            {this.renderStepActions(index, groupKey)}
                        </MaterialUI.StepContent>
                    </MaterialUI.Step>
                );
                index ++;
            }.bind(this));

            return (
                <div style={{maxHeight: this.state.maxHeight, overflowY: 'auto', paddingBottom: 24}}>
                    <MaterialUI.Stepper activeStep={stepIndex} orientation="vertical">
                        {forms}
                    </MaterialUI.Stepper>
                </div>
            );
        }

    });



    global.PydioInstaller = {
        Dialog: InstallerDialog,
        openDialog: function(){
            global.pydio.UI.openComponentInModal('PydioInstaller', 'Dialog');
        }
    };


})(window);