const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
import PublicLinkField from './Field'
import PublicLinkPermissions from './Permissions'
import PublicLinkSecureOptions from './SecureOptions'
const {ValidPassword} = require('pydio').requireLib('form')
const {RaisedButton, Checkbox} = require('material-ui')

let PublicLinkPanel = React.createClass({

    propTypes: {
        linkData:React.PropTypes.object,
        pydio:React.PropTypes.instanceOf(Pydio),
        shareModel: React.PropTypes.instanceOf(ReactModel.Share),
        authorizations: React.PropTypes.object,
        showMailer:React.PropTypes.func
    },

    disableSave: function(){
        this.setState({disabled: true});
    },
    enableSave: function(){
        this.setState({disabled:false});
    },
    componentDidMount: function(){
        this.props.shareModel.observe('saving', this.disableSave);
        this.props.shareModel.observe('saved', this.enableSave);
    },
    componendWillUnmount: function(){
        this.props.shareModel.stopObserving('saving', this.disableSave);
        this.props.shareModel.stopObserving('saved', this.enableSave);
    },

    toggleLink: function(){
        const publicLinks = this.props.shareModel.getPublicLinks();
        if(this.state.showTemporaryPassword){
            this.setState({showTemporaryPassword: false, temporaryPassword: null});
        }else if(!publicLinks.length && ReactModel.Share.getAuthorizations(this.props.pydio).password_mandatory){
            this.setState({showTemporaryPassword: true, temporaryPassword: ''});
        }else{
            this.props.shareModel.togglePublicLink();
        }
    },

    getInitialState: function(){
        return {showTemporaryPassword: false, temporaryPassword: null, disabled: false};
    },

    updateTemporaryPassword: function(value, event){
        if(value == undefined) value = event.currentTarget.getValue();
        this.setState({temporaryPassword:value});
    },

    enableLinkWithPassword:function(){
        this.props.shareModel.enablePublicLinkWithPassword(this.state.temporaryPassword);
        this.setState({showTemporaryPassword:false, temporaryPassword:null});
    },

    render: function(){

        let publicLinkPanes;
        if(this.props.linkData) {
            publicLinkPanes = [
                <PublicLinkField
                    showMailer={this.props.showMailer}
                    linkData={this.props.linkData}
                    shareModel={this.props.shareModel}
                    editAllowed={this.props.authorizations.editable_hash}
                    key="public-link"
                />,
                <PublicLinkPermissions
                    linkData={this.props.linkData}
                    shareModel={this.props.shareModel}
                    key="public-perm"/>,
                <PublicLinkSecureOptions
                    linkData={this.props.linkData}
                    shareModel={this.props.shareModel}
                    pydio={this.props.pydio}
                    key="public-secure"
                />
            ];
        }else if(this.state.showTemporaryPassword){
            publicLinkPanes = [
                <div>
                    <div className="section-legend" style={{marginTop:20}}>{this.props.getMessage('215')}</div>
                    <div>
                        <div style={{float:'left'}}>
                            <ValidPassword
                                attributes={{label:this.props.getMessage('23')}}
                                value={this.state.temporaryPassword}
                                onChange={this.updateTemporaryPassword}
                            />
                        </div>
                        <div style={{marginLeft:7,marginTop: 26,float:'left'}}>
                            <RaisedButton label={this.props.getMessage('92')} secondary={true} onClick={this.enableLinkWithPassword}/>
                        </div>
                    </div>
                </div>
            ];

        }else{
            publicLinkPanes = [
                <div className="section-legend" style={{marginTop:20}}>{this.props.getMessage('190')}</div>
            ];
        }
        let checked = !!this.props.linkData;
        let disableForNotOwner = false;
        if(checked && !this.props.shareModel.currentIsOwner()){
            disableForNotOwner = true;
        }
        return (
            <div style={{padding:16}} className="ie_material_checkbox_fix">
                <Checkbox
                    disabled={this.props.isReadonly() || disableForNotOwner || this.state.disabled}
                    onCheck={this.toggleLink}
                    checked={!!this.props.linkData || this.state.showTemporaryPassword}
                    label={this.props.getMessage('189')}
                />
                {publicLinkPanes}
            </div>
        );
    }
});

PublicLinkPanel = ShareContextConsumer(PublicLinkPanel);
export {PublicLinkPanel as default}