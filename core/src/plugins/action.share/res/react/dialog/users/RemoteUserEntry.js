const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
import UserBadge from './UserBadge'

let RemoteUserEntry = React.createClass({

    propTypes:{
        shareModel:React.PropTypes.instanceOf(ReactModel.Share),
        linkData:React.PropTypes.object.isRequired,
        onRemoveUser:React.PropTypes.func.isRequired,
        onUserUpdate:React.PropTypes.func.isRequired
    },

    getInitialState(){
        return {
            internalUser: this.props.shareModel.getSharedUser(this.props.linkData['internal_user_id'])
        };
    },

    componentWillReceiveProps(newProps, oldProps){
        this.setState({
            internalUser:newProps.shareModel.getSharedUser(newProps.linkData['internal_user_id'])
        });
    },

    getStatus:function(){
        var link = this.props.linkData;
        if(!link.invitation) return -1;
        else return link.invitation.STATUS;
    },

    getStatusString: function(){
        const statuses = {'s-1':214, 's1':211, 's2':212, 's4':213};
        return this.props.getMessage(statuses['s'+this.getStatus()]);
    },

    buildLabel: function(){
        var link = this.props.linkData;
        var host = link.HOST || (link.invitation ? link.invitation.HOST : null);
        var user = link.USER || (link.invitation ? link.invitation.USER : null);
        if(!host || !user) return "Error";
        return user + " @ " + host ;
    },

    removeUser: function(){
        this.props.onRemoveUser(this.props.linkData['hash']);
    },

    onUpdateRight:function(event){
        var target = event.target;
        this.props.onUserUpdate('update_right', this.state.internalUser.ID, {right:target.name, add:target.checked});
    },

    render: function(){
        var menuItems = [];
        if(!this.props.isReadonly()){
            menuItems = [{
                text:this.props.getMessage('257', ''),
                callback:this.removeUser
            }];
        }
        var status = this.getStatus();
        var additionalItem;
        if(status == 2){
            additionalItem = (
                <span className="user-badge-rights-container">
                    <input type="checkbox" name="read"  disabled={this.props.isReadonly()} checked={this.state.internalUser.RIGHT.indexOf('r') !== -1} onChange={this.onUpdateRight}/>
                    <input type="checkbox" name="write" disabled={this.props.isReadonly()} checked={this.state.internalUser.RIGHT.indexOf('w') !== -1} onChange={this.onUpdateRight}/>
                </span>
            );
        }else{
            additionalItem = (
                <span className="user-badge-rights-container">{this.getStatusString()}</span>
            );
        }

        return (
            <UserBadge
                label={this.buildLabel()}
                avatar={null}
                type={"remote_user"}
                menus={menuItems}
            >
                {additionalItem}
            </UserBadge>
        );
    }

});

RemoteUserEntry = ShareContextConsumer(RemoteUserEntry)
export {RemoteUserEntry as default}