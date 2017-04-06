const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
import UserBadge from './UserBadge'
import SharedUserEntry from './SharedUserEntry'
const {UsersCompleter} = require('pydio').requireLib('components')

let SharedUsers = React.createClass({
    
    propTypes: {
        pydio:React.PropTypes.instanceOf(pydio),
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
        return (
            <UserBadge
                label={(userObject.getExtendedLabel() || userObject.getLabel())}
                avatar={userObject.getAvatar()}
                type={userObject.getGroup() ? 'group' : (userObject.getTemporary()?'temporary' : (userObject.getExternal()?'tmp_user':'user'))}
            />
        );
    },

    render: function(){
        // sort by group/user then by ID;
        const userEntries = this.props.users.sort(function(a,b) {
            return (b.TYPE == "group") ? 1 : ((a.TYPE == "group") ? -1 : (a.ID > b.ID) ? 1 : ((b.ID > a.ID) ? -1 : 0));
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
        var actionLinks = [];
        if(this.props.users.length && !this.props.isReadonly()){
            actionLinks.push(<a key="clear" onClick={this.clearAllUsers}>{this.props.getMessage('180')}</a>);
        }
        if(this.props.sendInvitations && this.props.users.length){
            actionLinks.push(<a key="invite" onClick={this.sendInvitationToAllUsers}>{this.props.getMessage('45')}</a>);
        }
        if(this.props.saveSelectionAsTeam && this.props.users.length > 1 && !this.props.isReadonly()){
            actionLinks.push(<a key="team" onClick={this.props.saveSelectionAsTeam}>{this.props.getMessage('509', '')}</a>);
        }
        if(actionLinks.length){
            var linkActions = <div className="additional-actions-links">{actionLinks}</div>;
        }
        var rwHeader;
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
            var usersInput = (
                <UsersCompleter
                    className="share-form-users"
                    fieldLabel={this.props.getMessage('34')}
                    renderSuggestion={this.completerRenderSuggestion}
                    onValueSelected={this.valueSelected}
                    excludes={excludes}
                    pydio={this.props.pydio}
                    showAddressBook={true}
                />
            );
        }
        var title;
        if(this.props.showTitle){
            title = <h3>{this.props.getMessage('217')}</h3>;
        }
        return (
            <div>
                {title}
                <div className="section-legend">{this.props.getMessage('182')}</div>
                {usersInput}
                {rwHeader}
                <div>{userEntries}</div>
                {linkActions}
            </div>
        );
    }
});

SharedUsers = ShareContextConsumer(SharedUsers)
export {SharedUsers as default}