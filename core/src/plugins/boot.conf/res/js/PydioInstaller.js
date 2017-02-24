(function(global){

    const InstallerDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin
        ],

        getDefaultProps: function(){
            return {
                dialogTitle: "Welcome to Pydio",
                dialogIsModal: true,
                dialogSize:'md'
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
                            legend:''
                        });
                    }
                    if(param.type === 'legend'){
                        groups.get(g).legend = param.description;
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
                finished: false,
                stepIndex: 0,
                maxHeight: DOMUtils.getViewportHeight() - 40
            }
        },

        onFormChange: function(values){
            if(values['ADMIN_USER_LOGIN'] !== this.state.values['ADMIN_USER_LOGIN']
                && this.state.values['ADMIN_USER_LOGIN'] === this.state.values['ADMIN_USER_NAME']){
                values['ADMIN_USER_NAME'] = values['ADMIN_USER_LOGIN'];
            }
            this.setState({values: values});
        },

        showHelper: function(){
            return false;
        },

        parameterHasHelper: function(){
            return false;
        },

        handleNext : function(){
            const {stepIndex} = this.state;
            this.setState({
                stepIndex: stepIndex + 1,
                finished: stepIndex >= 2,
            });
        },

        handlePrev: function(){
            const {stepIndex} = this.state;
            if (stepIndex > 0) {
                this.setState({stepIndex: stepIndex - 1});
            }
        },

        renderStepActions(step) {
            const {stepIndex} = this.state;

            return (
                <div style={{margin: '12px 0'}}>
                    <MaterialUI.RaisedButton
                        label={stepIndex === 4 ? 'Finish' : 'Next'}
                        disableTouchRipple={true}
                        disableFocusRipple={true}
                        primary={true}
                        onTouchTap={this.handleNext}
                        style={{marginRight: 12}}
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


        render: function(){

            if(!this.state.parameters){
                return <PydioReactUI.Loader/>;
            }

            const {finished, stepIndex} = this.state;

            let forms = [], index = 0;
            console.log(this.state.parameters);
            this.state.groups.forEach(function(gData, groupKey){
                forms.push(
                    <MaterialUI.Step>
                        <MaterialUI.StepLabel>{gData.title}</MaterialUI.StepLabel>
                        <MaterialUI.StepContent style={{maxWidth:420}}>
                            <PydioForm.FormPanel
                                key={groupKey}
                                ref="formPanel"
                                className="stepper-form-panel"
                                parameters={this.state.parameters}
                                values={this.state.values}
                                onChange={this.onFormChange}
                                disabled={false}
                                limitToGroups={[groupKey]}
                                skipFieldsTypes={['legend', 'GroupHeader']}
                                setHelperData={this.showHelper}
                                checkHasHelper={this.parameterHasHelper}
                                depth={-1}
                            />
                            {this.renderStepActions(index)}
                        </MaterialUI.StepContent>
                    </MaterialUI.Step>
                );
                index ++;
            }.bind(this));

            forms.push(
                <MaterialUI.Step>
                    <MaterialUI.StepLabel>Summary</MaterialUI.StepLabel>
                    <MaterialUI.StepContent style={{maxWidth:420}}>
                        {this.renderStepActions(index)}
                    </MaterialUI.StepContent>
                </MaterialUI.Step>
            );


            return (
                <div style={{maxHeight: this.state.maxHeight, overflowY: 'auto', paddingBottom: 24}}>
                    <MaterialUI.Stepper activeStep={stepIndex} orientation="vertical">
                        {forms}
                    </MaterialUI.Stepper>
                    {finished && (
                        <p style={{margin: '20px 0', textAlign: 'center'}}>
                            <a
                                href="#"
                                onClick={(event) => {
                                    event.preventDefault();
                                    this.setState({stepIndex: 0, finished: false});
                                }}
                            >
                                Click here
                            </a> to reset the example.
                        </p>
                    )}
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