const React = require('react');
const {TextField, IconButton} = require('material-ui')
import ShareContextConsumer from '../ShareContextConsumer'
import RemoteUserEntry from './RemoteUserEntry'
const {ReactModelShare} = require('pydio').requireLib('ReactModelShare')

let RemoteUsers = React.createClass({

    propTypes:{
        shareModel: React.PropTypes.instanceOf(ReactModelShare),
        onUserUpdate:React.PropTypes.func.isRequired
    },

    getInitialState: function(){
        return {addDisabled: true};
    },

    addUser:function(){
        var h = this.refs["host"].getValue();
        var u = this.refs["user"].getValue();
        this.props.shareModel.createRemoteLink(h, u);
    },

    removeUser: function(linkId){
        this.props.shareModel.removeRemoteLink(linkId);
    },

    monitorInput:function(){
        var h = this.refs["host"].getValue();
        var u = this.refs["user"].getValue();
        this.setState({addDisabled:!(h && u)});
    },

    renderForm: function(){
        if(this.props.isReadonly()){
            return null;
        }
        return (
            <div className="remote-users-add">
                <TextField className="host" ref="host" floatingLabelText={this.props.getMessage('209')} onChange={this.monitorInput}/>
                <TextField className="user" ref="user" type="text" floatingLabelText={this.props.getMessage('210')} onChange={this.monitorInput}/>
                <IconButton tooltip={this.props.getMessage('45')} iconClassName="icon-plus-sign" onClick={this.addUser} disabled={this.state.addDisabled}/>
            </div>
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
            <div>
                <h3>{this.props.getMessage('207')}</h3>
                <div className="section-legend">{this.props.getMessage('208')}</div>
                {this.renderForm()}
                <div>
                    {rwHeader}
                    {inv}
                </div>
            </div>
        );
    }
});

RemoteUsers = ShareContextConsumer(RemoteUsers);
export {RemoteUsers as default}