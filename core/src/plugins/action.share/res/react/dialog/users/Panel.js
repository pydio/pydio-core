const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
import RemoteUsers from './RemoteUsers'
import SharedUsers from './SharedUsers'

let UsersPanel = React.createClass({

    propTypes:{
        shareModel:React.PropTypes.instanceOf(ReactModel.Share),
        showMailer:React.PropTypes.func
    },

    onUserUpdate: function(operation, userId, userData){
        this.props.shareModel.updateSharedUser(operation, userId, userData);
    },

    onSaveSelection:function(){
        var label = window.prompt(this.props.getMessage(510, ''));
        if(!label) return;
        this.props.shareModel.saveSelectionAsTeam(label);
    },

    sendInvitations:function(userObjects){
        try{
            var mailData = this.props.shareModel.prepareEmail("repository");
            this.props.showMailer(mailData.subject, mailData.message, userObjects);
        }catch(e){
            global.alert(e.message);
        }
    },

    render: function(){
        var currentUsers = this.props.shareModel.getSharedUsers();
        var federatedEnabled = ReactModel.Share.federatedSharingEnabled();
        if(federatedEnabled){
            var remoteUsersBlock = (
                <RemoteUsers
                    shareModel={this.props.shareModel}
                    onUserUpdate={this.onUserUpdate}
                />
            );
        }
        return (
            <div style={federatedEnabled?{padding:'0 16px 10px'}:{padding:'20px 16px 10px'}}>
                <SharedUsers
                    showTitle={federatedEnabled}
                    users={currentUsers}
                    userObjects={this.props.shareModel.getSharedUsersAsObjects()}
                    sendInvitations={this.props.showMailer ? this.sendInvitations : null}
                    onUserUpdate={this.onUserUpdate}
                    saveSelectionAsTeam={PydioUsers.Client.saveSelectionSupported()?this.onSaveSelection:null}
                    pydio={this.props.pydio}
                />
                {remoteUsersBlock}
            </div>
        );
    }
});

UsersPanel = ShareContextConsumer(UsersPanel);
export {UsersPanel as default}