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
 * The latest code can be found at <http://pyd.io/>.
 */
(function(global){

    var Mailer = React.createClass({

        propTypes:{
            message:React.PropTypes.string.isRequired,
            subject:React.PropTypes.string.isRequired,
            link:React.PropTypes.string,
            onDismiss:React.PropTypes.func,
            className:React.PropTypes.string,
            overlay:React.PropTypes.bool,
            users:React.PropTypes.array
        },

        getInitialState: function(){
            return {
                users:this.props.users || [],
                subject:this.props.subject,
                message:this.props.message
            };
        },

        updateSubject: function(event){
            this.setState({subject:event.currentTarget.getValue()});
        },

        updateMessage: function(event){
            this.setState({message:event.currentTarget.getValue()});
        },

        addUser: function(userId, userLabel){
            var users = this.state.users;
            users.push(userId);
            this.setState({users:users});
        },

        postEmail : function(){
            var params = {
                get_action:"send_mail",
                'emails[]':this.state.users,
                mailer_input_field:'Subject of the mail',
                message:this.props.message
            };
            if(this.props.link){
                params['link'] = this.props.link;
            }
            var client = PydioApi.getClient();
            client.request(params, function(transport){
                client.parseXmlMessage(transport.responseXML);
            });
        },

        usersLoaderRenderSuggestion(userObject){
            return (
                <div>{userObject.getExtendedLabel() || userObject.getLabel()}</div>
            );
        },

        render: function(){
            var className = [this.props.className, "react-mui-context", "reset-pydio-forms"].join(" ");
            var content = (
                <div className={className}>
                    <div>
                        <UsersCompleter.Input
                            usersOnly={true}
                            existingOnly={true}
                            onValueSelected={this.addUser}
                            excludes={this.state.users}
                            renderSuggestion={this.usersLoaderRenderSuggestion}
                        />
                        <div>{this.state.users.map(function(u){return <span>{u}</span>;})}</div>
                        <ReactMUI.TextField floatingLabelText="Subjet" value={this.state.subject} onChange={this.updateSubject}/>
                        <ReactMUI.TextField floatingLabelText="Message" value={this.state.message} multiLine={true} onChange={this.updateMessage}/>
                        <div>
                            <ReactMUI.FlatButton label="Close" onClick={this.props.onDismiss}/>
                            <ReactMUI.FlatButton primary={true} label="Send" onClick={this.postEmail}/>
                        </div>
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

    var PydioMailer = global.PydioMailer || {};
    PydioMailer.Pane = Mailer;
    global.PydioMailer = PydioMailer;

})(window);