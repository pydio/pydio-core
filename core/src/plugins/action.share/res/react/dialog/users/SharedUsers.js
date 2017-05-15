const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
import UserBadge from './UserBadge'
import SharedUserEntry from './SharedUserEntry'
import ActionButton from '../main/ActionButton'
import Card from '../main/Card'
const Pydio = require('pydio')
const {UsersCompleter} = Pydio.requireLib('components')
const {Paper} = require('material-ui')

let SharedUsers = React.createClass({
    
    propTypes: {
        pydio:React.PropTypes.instanceOf(Pydio),
        users:React.PropTypes.array.isRequired,
        userObjects:React.PropTypes.object.isRequired,
        onUserUpdate:React.PropTypes.func.isRequired,
        saveSelectionAsTeam:React.PropTypes.func,
        sendInvitations:React.PropTypes.func,
        showTitle:React.PropTypes.bool
    },
    sendInvitationToAllUsers:function(){
        this.props.sendInvitations(this.props.userObjects);
    },
    clearAllUsers:function(){
        this.props.users.map(function(entry){
            this.props.onUserUpdate('remove', entry.ID, entry);
        }.bind(this));
    },
    valueSelected: function(userObject){
        var newEntry = {
            ID      : userObject.getId(),
            RIGHT   :'r',
            LABEL   : userObject.getLabel(),
            TYPE    : userObject.getGroup() ? 'group' : 'user'
        };
        this.props.onUserUpdate('add', newEntry.ID, newEntry);
    },
    completerRenderSuggestion: function(userObject){
        let type = (userObject.getType() === 'team' || userObject.getId().indexOf('/AJXP_TEAM/') === 0 ? 'team' : (
                        userObject.getGroup() ? 'group' : (
                            userObject.getTemporary()? 'temporary' : (
                                userObject.getExternal()? 'tmp_user':'user'
                                )
                            )
                        )
                    );

        return (
            <UserBadge
                label={(userObject.getExtendedLabel() || userObject.getLabel())}
                avatar={userObject.getAvatar()}
                type={type}
            />
        );
    },

    render: function(){
        // sort by group/user then by ID;
        const userEntries = this.props.users.sort(function(a,b) {
            return (b.TYPE === 'group' || b.TYPE === 'team') ? 1 : ((a.TYPE === 'group' || a.TYPE === 'team') ? -1 : (a.ID > b.ID) ? 1 : ((b.ID > a.ID) ? -1 : 0));
        } ).map(function(u){
            return <SharedUserEntry
                userEntry={u}
                userObject={this.props.userObjects[u.ID]}
                key={u.ID}
                shareModel={this.props.shareModel}
                onUserUpdate={this.props.onUserUpdate}
                sendInvitations={this.props.sendInvitations}
            />
        }.bind(this));
        let actionLinks = [];
        if(this.props.users.length && !this.props.isReadonly()){
            actionLinks.push(<ActionButton key="clear" callback={this.clearAllUsers} mdiIcon="delete" messageId="180"/>)
        }
        if(this.props.sendInvitations && this.props.users.length){
            actionLinks.push(<ActionButton key="invite" callback={this.sendInvitationToAllUsers} mdiIcon="email-outline" messageId="45"/>)
        }
        if(this.props.saveSelectionAsTeam && this.props.users.length > 1 && !this.props.isReadonly()){
            actionLinks.push(<ActionButton key="team" callback={this.props.saveSelectionAsTeam} mdiIcon="account-multiple-plus" messageId="509" messageCoreNamespace={true}/>)
        }
        let rwHeader, usersInput;
        if(this.props.users.length){
            rwHeader = (
                <div>
                    <div className="shared-users-rights-header">
                        <span className="read">{this.props.getMessage('361', '')}</span>
                        <span className="read">{this.props.getMessage('181')}</span>
                    </div>
                </div>
            );
        }
        if(!this.props.isReadonly()){
            const excludes = this.props.users.map(function(u){return u.ID});
            usersInput = (
                <UsersCompleter
                    className="share-form-users"
                    fieldLabel={this.props.getMessage('34')}
                    renderSuggestion={this.completerRenderSuggestion}
                    onValueSelected={this.valueSelected}
                    excludes={excludes}
                    pydio={this.props.pydio}
                    showAddressBook={true}
                    usersFrom="local"
                />
            );
        }
        return (
            <Card
                title={this.props.showTitle ? this.props.getMessage('217') : null}
                actions={actionLinks}
            >
                <div style={userEntries.length? {margin: '-20px 8px 16px'} : {marginTop: -20}}>{usersInput}</div>
                {rwHeader}
                <div>{userEntries}</div>
                {!userEntries.length &&
                    <div style={{color: 'rgba(0,0,0,0.43)'}}>{this.props.getMessage('182')}</div>
                }
            </Card>
        );
    }
});

SharedUsers = ShareContextConsumer(SharedUsers)
export {SharedUsers as default}