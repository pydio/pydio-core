const React = require('react');
import UserBadge from './UserBadge'
import ShareContextConsumer from '../ShareContextConsumer'

let SharedUserEntry = React.createClass({

    propTypes: {
        userEntry:React.PropTypes.object.isRequired,
        userObject:React.PropTypes.instanceOf(PydioUsers.User).isRequired,
        onUserUpdate:React.PropTypes.func.isRequired,
        sendInvitations:React.PropTypes.func
    },
    onRemove:function(){
        this.props.onUserUpdate('remove', this.props.userEntry.ID, this.props.userEntry);
    },
    onToggleWatch:function(){
        this.props.onUserUpdate('update_right', this.props.userEntry.ID, {right:'watch', add:!this.props.userEntry['WATCH']});
    },
    onInvite:function(){
        let targets = {};
        targets[this.props.userObject.getId()] = this.props.userObject;
        this.props.sendInvitations(targets);
    },
    onUpdateRight:function(event){
        const target = event.target;
        this.props.onUserUpdate('update_right', this.props.userEntry.ID, {right:target.name, add:target.checked});
    },
    render: function(){
        let menuItems = [];
        if(this.props.userEntry.TYPE != 'group'){
            if(!this.props.isReadonly()){
                // Toggle Notif
                menuItems.push({
                    text:this.props.getMessage('183'),
                    callback:this.onToggleWatch,
                    checked:this.props.userEntry.WATCH
                });
            }
            if(this.props.sendInvitations){
                // Send invitation
                menuItems.push({
                    text:this.props.getMessage('45'),
                    callback:this.onInvite
                });
            }
        }
        if(!this.props.isReadonly()){
            // Remove Entry
            menuItems.push({
                text:this.props.getMessage('257', ''),
                callback:this.onRemove
            });
        }
        return (
            <UserBadge
                label={this.props.userEntry.LABEL || this.props.userEntry.ID }
                avatar={this.props.userEntry.AVATAR}
                type={this.props.userEntry.TYPE}
                menus={menuItems}
            >
                <span className="user-badge-rights-container" style={!menuItems.length ? {marginRight: 48} : {}}>
                    <input type="checkbox" name="read" disabled={this.props.isReadonly()} checked={this.props.userEntry.RIGHT.indexOf('r') !== -1} onChange={this.onUpdateRight}/>
                    <input type="checkbox" name="write" disabled={this.props.isReadonly()} checked={this.props.userEntry.RIGHT.indexOf('w') !== -1} onChange={this.onUpdateRight}/>
                </span>
            </UserBadge>
        );
    }
});

SharedUserEntry = ShareContextConsumer(SharedUserEntry);
export {SharedUserEntry as default}