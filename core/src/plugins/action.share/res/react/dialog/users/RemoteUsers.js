const React = require('react');
const {TextField, IconButton, Paper} = require('material-ui')
import ShareContextConsumer from '../ShareContextConsumer'
import RemoteUserEntry from './RemoteUserEntry'
import Card from '../main/Card'
const {ReactModelShare} = require('pydio').requireLib('ReactModelShare')
const {AddressBook} = require('pydio').requireLib('components')
import ActionButton from '../main/ActionButton'

let RemoteUsers = React.createClass({

    propTypes:{
        shareModel: React.PropTypes.instanceOf(ReactModelShare),
        onUserUpdate:React.PropTypes.func.isRequired,
        pydio: React.PropTypes.instanceOf(Pydio)
    },

    getInitialState: function(){
        return {addDisabled: true, showUserForm: false};
    },

    addUser:function(){
        const h = this.refs["host"].getValue();
        const u = this.refs["user"].getValue();
        this.props.shareModel.createRemoteLink(h, u);
    },

    removeUser: function(linkId){
        this.props.shareModel.removeRemoteLink(linkId);
    },

    monitorInput:function(){
        const h = this.refs["host"].getValue();
        const u = this.refs["user"].getValue();
        this.setState({addDisabled:!(h && u)});
    },

    onAddressBookItemSelected: function(uObject, parent){
        const {trustedServerId} = uObject;
        const userId = uObject.getId();
        this.props.shareModel.createRemoteLink('trusted://' + trustedServerId, userId);
    },

    getActions: function () {
        const ocsRemotes = this.props.pydio.getPluginConfigs('core.ocs').get('TRUSTED_SERVERS');
        const hasTrusted = ocsRemotes && ocsRemotes.length;

        return [
                <ActionButton key="manual" mdiIcon={'account-plus'} messageId={'45'} onTouchTap={()=>{this.setState({showUserForm:true})}}/>,
                <AddressBook
                    key="addressbook"
                    mode="popover"
                    pydio={this.props.pydio}
                    onItemSelected={this.onAddressBookItemSelected}
                    usersFrom={'remote'}
                    disableSearch={true}
                    popoverButton={<ActionButton mdiIcon={'server-network'} messageId={'45'}/>}
                />
        ];
    },

    renderUserForm: function(){
        if(this.props.isReadonly()){
            return null;
        }
        return (
            <Paper zDepth={0} style={{padding: '0 16px', backgroundColor: '#FAFAFA', marginTop: 10}}>
                <div>
                    <TextField fullWidth={true} ref="host" floatingLabelText={this.props.getMessage('209')} onChange={this.monitorInput}/>
                    <TextField fullWidth={true} ref="user" type="text" floatingLabelText={this.props.getMessage('210')} onChange={this.monitorInput}/>
                </div>
                <div style={{textAlign:'right'}}>
                    <IconButton tooltip={'Cancel'} iconClassName="mdi mdi-undo" onClick={() => {this.setState({showUserForm: false})}}/>
                    <IconButton tooltip={this.props.getMessage('45')} iconClassName="icon-plus-sign" onClick={this.addUser} disabled={this.state.addDisabled}/>
                </div>
            </Paper>
        );
    },

    render: function() {
        var ocsLinks = this.props.shareModel.getOcsLinksByStatus(),
            inv, rwHeader, hasActiveOcsLink = false;

        inv = ocsLinks.map(function(link){
            hasActiveOcsLink = (!hasActiveOcsLink && link && link.invitation && link.invitation.STATUS == 2) ? true : hasActiveOcsLink;

            return (
                <RemoteUserEntry
                    shareModel={this.props.shareModel}
                    linkData={link}
                    onRemoveUser={this.removeUser}
                    onUserUpdate={this.props.onUserUpdate}
                />
            );
        }.bind(this));

        if(hasActiveOcsLink){
            rwHeader = (
                <div>
                    <div className="shared-users-rights-header">
                        <span className="read">{this.props.getMessage('361', '')}</span>
                        <span className="read">{this.props.getMessage('181')}</span>
                    </div>
                </div>
            );
        }

        return (
            <Card title={this.props.getMessage('207')} actions={this.getActions()}>
                {!ocsLinks.length &&
                    <div style={{color: 'rgba(0,0,0,0.43)', paddingBottom: 16}}>{this.props.getMessage('208')}</div>
                }
                <div>
                    {rwHeader}
                    {inv}
                </div>
                {this.state.showUserForm && this.renderUserForm()}
            </Card>
        );
    }
});

RemoteUsers = ShareContextConsumer(RemoteUsers);
export {RemoteUsers as default}