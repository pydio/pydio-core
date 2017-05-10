const React = require('react');
const {ActionDialogMixin,CancelButtonProviderMixin,SubmitButtonProviderMixin, Loader} = require('pydio').requireLib('boot');
const PydioApi = require('pydio/http/api');
const XMLUtils = require('pydio/util/xml');
const {Divider, List, ListItem, FlatButton} = require('material-ui');

/**
 * Dialog for letting users create a workspace
 */
export default React.createClass({

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitleId: '418',
            dialogIsModal: true,
            dialogScrollBody: true,
            dialogPadding: 0
        };
    },

    getInitialState: function(){
        return {xmlDefinitions: null, templateId: null, formParameters:null, formValues: {}, formLoaded: false, formValid: false};
    },

    getButtons: function(updater = null){
        if(updater !== null){
            this._updater = updater;
        }
        const {templateId, formValid} = this.state;
        let buttons = [];
        if(templateId) {
            buttons.push(<FlatButton style={{float:'left'}} label="<<" onTouchTap={this.resetTemplate.bind(this)}/>);
        }
        buttons.push(<FlatButton label="Cancel" onTouchTap={this.props.onDismiss} />);
        buttons.push(<FlatButton secondary={true} label="OK" disabled={!formValid}  onTouchTap={this.submit.bind(this)} />);
        return buttons;
    },
    
    componentDidMount: function(){

        require('pydio').requireLib('form', true).then(() => {
            this.setState({formLoaded: true});
        });

        PydioApi.getClient().request({get_action:'get_user_templates_definition'}, (transport) => {
            this.setState({xmlDefinitions: transport.responseXML});
        });
        return;

    },

    submit(){

        const {xmlDefinitions, templateId, formParameters, formValues, formLoaded} = this.state;
        const {Manager} = require('pydio').requireLib('form');

        const parameters = Manager.parseParameters(xmlDefinitions, '//template[@repository_id="'+templateId+'"]/param');
        let postValues = Manager.getValuesForPOST(parameters, formValues, 'DRIVER_OPTION_');
        if(postValues['DRIVER_OPTION_DISPLAY']){
            postValues['DISPLAY'] = postValues['DRIVER_OPTION_DISPLAY']
            delete postValues['DRIVER_OPTION_DISPLAY'],
            delete postValues['DRIVER_OPTION_DISPLAY_ajxptype'];
        }else{
            postValues['DISPLAY'] = "NEW REPOSITORY TEST";
        }
        PydioApi.getClient().request({
            get_action:'user_create_repository',
            template_id: templateId,
            ...postValues
        }, (transport)=>{
            this.dismiss();
        });

    },
    
    chooseTemplate: function(templateId){

        const {Manager} = require('pydio').requireLib('form');
        const {xmlDefinitions} = this.state;
        let parameters = Manager.parseParameters(xmlDefinitions, '//template[@repository_id="'+templateId+'"]/param');
        const displayParamIndex = parameters.findIndex((p)=> p.name === 'DISPLAY');
        if(displayParamIndex > -1) {
            const displayParam = parameters[displayParamIndex];
            parameters.splice(displayParamIndex,1);
            parameters.unshift(displayParam);
        }
        this.setState({
            templateId: templateId,
            formParameters: parameters
        }, () => {
            this._updater(this.getButtons());
        });
        
    },

    resetTemplate: function(){
        this.setState({
            templateId: null,
            formParameters: null,
            formValues: {},
            formValid: false
        }, () => {
            this._updater(this.getButtons());
        })
    },

    onFormValidStatusChange(newValidValue, failedFields){
        this.setState({formValid: newValidValue}, () => {
            this._updater(this.getButtons());
        });
    },

    render: function(){

        const {xmlDefinitions, templateId, formParameters, formValues, formLoaded} = this.state;
        const {pydio:{MessageHash}} = this.props;

        if(!xmlDefinitions || !formLoaded){
            return <Loader/>;
        }
        if(!templateId) {
            const templates = XMLUtils.XPathSelectNodes(xmlDefinitions, "//template");
            const items = [];
            for (let i = 0; i < templates.length; i++) {
                const label = templates[i].getAttribute('repository_label');
                const tplId = templates[i].getAttribute('repository_id');
                items.push(<ListItem key={tplId} primaryText={label} onTouchTap={() => this.chooseTemplate(tplId) }/>);
                if(i < templates.length - 1){
                    items.push(<Divider key={tplId + '-divider'}/>);
                }
            }
            return (
                <div style={{width: '100%'}}>
                    <p style={{padding: 16, paddingBottom:0, marginBottom: 0, color: 'rgba(0,0,0,.43)'}}>{MessageHash['420']}</p>
                    <List>{items}</List>
                </div>
            );
        }

        const {FormPanel} = require('pydio').requireLib('form');
        return (
            <div style={{width: '100%'}}>
                <FormPanel
                    depth={-2}
                    parameters={formParameters}
                    values={formValues}
                    onChange={(newValues) => {this.setState({formValues:newValues})}}
                    onValidStatusChange={this.onFormValidStatusChange.bind(this)}
                />
            </div>
        );


        return (<div></div>);
    }

});

