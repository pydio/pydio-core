/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
(function(global){

    var DestBadge = React.createClass({
        propTypes:{
            user:React.PropTypes.instanceOf(PydioUsers.User)
        },
        render: function(){
            const userObject = this.props.user;
            return (
                <div className={"user-badge user-type-" + (userObject.getTemporary() ? "tmp_user" : "user")}>
                    <span className={"avatar icon-" + (userObject.getTemporary()?"envelope":"user")}/>
                    <span className="user-badge-label">{userObject.getExtendedLabel() || userObject.getLabel()}</span>
                </div>
            );
        }
    });

    var UserEntry = React.createClass({
        propTypes:{
            user:React.PropTypes.instanceOf(PydioUsers.User),
            onRemove:React.PropTypes.func
        },
        remove: function(){
            this.props.onRemove(this.props.user.getId());
        },
        toggleRemove:function(){
            var current = this.state && this.state.remove;
            this.setState({remove:!current});
        },
        render:function(){
            var icon, className = 'pydio-mailer-user ' + 'user-type-' + (this.props.user.getTemporary() ? "email" : "user");
            var clik = function(){};
            if(this.state && this.state.remove){
                clik = this.remove;
                icon = <span className="avatar mdi mdi-close"/>;
                className += ' remove';
            }else{
                icon = <span className={"avatar icon-" + (this.props.user.getTemporary()?"envelope":"user")}/>;
            }
            return (
                <div className={className} onMouseOver={this.toggleRemove} onMouseOut={this.toggleRemove} onClick={clik}>
                    {icon}
                    {this.props.user.getLabel()}
                </div>
            );
        }
    });

    var Mailer = React.createClass({

        propTypes:{
            message:React.PropTypes.string.isRequired,
            subject:React.PropTypes.string.isRequired,
            link:React.PropTypes.string,
            onDismiss:React.PropTypes.func,
            className:React.PropTypes.string,
            overlay:React.PropTypes.bool,
            users:React.PropTypes.object,
            panelTitle:React.PropTypes.string
        },

        getInitialState: function(){
            return {
                users:this.props.users || {},
                subject:this.props.subject,
                message:this.props.message,
                errorMessage:null
            };
        },

        componentDidMount(){
            var res = new ResourcesManager();
            res.loadCSSResource("plugins/core.mailer/css/PydioMailer.css");
        },

        updateSubject: function(event){
            this.setState({subject:event.currentTarget.value});
        },

        updateMessage: function(event){
            this.setState({message:event.currentTarget.value});
        },

        addUser: function(userObject){
            var users = this.state.users;
            users[userObject.getId()] = userObject;
            this.setState({users:users, errorMessage:null});
        },

        removeUser: function(userId){
            delete this.state.users[userId];
            this.setState({users:this.state.users});
        },

        getMessage: function(messageId, nameSpace = undefined){
            try{
                if(nameSpace === undefined) nameSpace = 'core.mailer';
                if(nameSpace) nameSpace += ".";
                return global.pydio.MessageHash[ nameSpace + messageId ];
            }catch(e){
                return messageId;
            }
        },

        postEmail : function(){
            if(!Object.keys(this.state.users).length){
                this.setState({errorMessage:'Please pick a user or a mail address'});
                return;
            }
            var params = {
                get_action:"send_mail",
                'emails[]': Object.keys(this.state.users),
                subject:this.state.subject,
                message:this.state.message
            };
            if(this.props.link){
                params['link'] = this.props.link;
            }
            var client = PydioApi.getClient();
            client.request(params, function(transport){
                const res = client.parseXmlMessage(transport.responseXML);
                if(res !== false){
                    this.props.onDismiss();
                }
            }.bind(this));
        },

        usersLoaderRenderSuggestion(userObject){
            return <DestBadge user={userObject}/> ;
        },

        render: function(){
            const className = [this.props.className, "react-mailer", "react-mui-context", "reset-pydio-forms"].join(" ");
            const users = Object.keys(this.state.users).map(function(uId){
                return (
                    <UserEntry key={uId} user={this.state.users[uId]} onRemove={this.removeUser}/>
                );
            }.bind(this));
            if(this.state.errorMessage){
                var errorDiv = <div className="error">{this.state.errorMessage}</div>
            }
            var content = (
                <div className={className}>
                    <h3>{this.props.panelTitle}</h3>
                    {errorDiv}
                    <div className="users-block">
                        <PydioComponents.UsersCompleter
                            fieldLabel={this.getMessage('8')}
                            usersOnly={true}
                            existingOnly={true}
                            freeValueAllowed={true}
                            onValueSelected={this.addUser}
                            excludes={Object.keys(this.state.users)}
                            renderSuggestion={this.usersLoaderRenderSuggestion}
                        />
                        <div className="pydio-mailer-users">{users}</div>
                    </div>
                    <ReactMUI.TextField floatingLabelText={this.getMessage('6')} value={this.state.subject} onChange={this.updateSubject}/>
                    <ReactMUI.TextField floatingLabelText={this.getMessage('7')} value={this.state.message} multiLine={true} onChange={this.updateMessage}/>
                    <div style={{textAlign:'right'}}>
                        <ReactMUI.FlatButton label={this.getMessage('54', '')} onClick={this.props.onDismiss}/>
                        <ReactMUI.FlatButton primary={true} label={this.getMessage('77', '')} onClick={this.postEmail}/>
                    </div>
                </div>
            );
            if(this.props.overlay){
                return (
                    <div className="react-mailer-overlay">{content}</div>
                );
            }else{
                return {content};
            }
        }
    });

    var Preferences = React.createClass({
        render: function(){
            return <div>Preferences Panel</div>;
        }
    });


    var PydioMailer = global.PydioMailer || {};
    PydioMailer.Pane = Mailer;
    PydioMailer.PreferencesPanel = Preferences;
    global.PydioMailer = PydioMailer;

})(window);