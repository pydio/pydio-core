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
(function(global) {

    var UsersLoader = React.createClass({

        propTypes:{
            renderSuggestion:React.PropTypes.func.isRequired,
            onValueSelected:React.PropTypes.func.isRequired,
            fieldLabel:React.PropTypes.string.isRequired,
            excludes:React.PropTypes.array.isRequired,
            usersOnly:React.PropTypes.bool,
            existingOnly:React.PropTypes.bool,
            freeValueAllowed:React.PropTypes.bool,
            className:React.PropTypes.string
        },

        getInitialState:function(){
            return {
                createUser: null,
                showComplete: true,
                loading:0,
                minChars:parseInt(global.pydio.getPluginConfigs("core.conf").get("USERS_LIST_COMPLETE_MIN_CHARS"))
            };
        },

        suggestionLoader:function(input, callback){
            var excludes = this.props.excludes;
            var disallowTemporary = this.props.existingOnly && !this.props.freeValueAllowed;
            this.setState({loading:this.state.loading + 1});
            PydioUsers.Client.authorizedUsersStartingWith(input, function(users){
                this.setState({loading:this.state.loading - 1});
                if(disallowTemporary){
                    users = users.filter(function(user){
                        return !user.getTemporary();
                    });
                }
                if(excludes && excludes.length){
                    users = users.filter(function(user){
                        return excludes.indexOf(user.getId()) == -1;
                    });
                }
                // Hack : force suggestions display
                if(this.refs.autosuggest.lastSuggestionsInputValue && this.refs.autosuggest.lastSuggestionsInputValue.indexOf(input) === 0){
                    this.refs.autosuggest.lastSuggestionsInputValue = input;
                }
                callback(null, users);
            }.bind(this), this.props.usersOnly, this.props.existingOnly);
        },

        getSuggestions(input, callback){
            var minChars = this.state.minChars;
            if(!this.state.showComplete || input.length < minChars){
                callback(null, []);
                return;
            }
            bufferCallback('suggestion-loader-users', 400, function(){
                this.suggestionLoader(input, callback);
            }.bind(this));
        },

        suggestionValue: function(suggestion){
            return '';
        },

        onSuggestionSelected: function(userObject, event){
            if(!userObject) return;
            var suggestion = userObject.asObject();
            var blur = true;
            if(suggestion.group){
                this.props.onValueSelected(suggestion.group, suggestion.label, 'group', userObject);
            }else if(suggestion.id) {
                this.props.onValueSelected(suggestion.id, suggestion.label, suggestion.external?'tmp_user':'user', userObject);
            }else if(suggestion.temporary){
                this.setState({createUser:suggestion.label});
                blur = false;
            }
            if(blur){
                this.setState({showComplete: false}, function(){
                    global.setTimeout(function(){
                        this.refs['autosuggest'].refs['input'].getDOMNode().blur();
                    }.bind(this), 10);
                });
            }
        },

        submitCreationForm: function(){

            var prefix = PydioUsers.Client.getCreateUserPostPrefix();
            var values = this.refs['creationForm'].getValuesForPost(prefix);
            PydioUsers.Client.createUserFromPost(values, function(values){
                var id = values[prefix + 'new_user_id'];
                var display = values[prefix + 'USER_DISPLAY_NAME'] || id;
                var fakeUser = new PydioUsers.User(id, display, 'user');
                this.props.onValueSelected(id, display, 'user', fakeUser);
                this.setState({createUser:null});
            }.bind(this));

        },

        cancelCreationForm:function(){
            this.setState({createUser:null});
        },

        render: function(){

            if(this.state.createUser){

                return (
                    <div className={this.props.className}>
                    <div className={"react-autosuggest"}>
                        <input type="text" id="users-autosuggest" className="react-autosuggest__input" value={'Create User ' + this.state.createUser}/>
                        <div className="react-autosuggest__suggestions">
                            <UserCreationForm ref="creationForm" newUserName={this.state.createUser} />
                            <div style={{padding:16, textAlign:'right', paddingTop:0}}>
                                <ReactMUI.FlatButton label="Save & add" secondary={true} onClick={this.submitCreationForm} />
                                <ReactMUI.FlatButton label="Cancel" onClick={this.cancelCreationForm} />
                            </div>
                        </div>
                    </div>
                    </div>
                );

            }else{
                const inputAttributes = {
                    id: 'users-autosuggest',
                    name: 'users-autosuggest',
                    className: 'react-autosuggest__input',
                    placeholder: this.props.fieldLabel,
                    onBlur: event => this.setState({showComplete:false}),
                    onFocus: event => this.setState({showComplete:true}),
                    value: ''   // Initial value
                };
                return (
                    <div style={{position:'relative'}} className={this.props.className + (this.state.showComplete ? '':' list-force-hide')}>
                        <span className={"suggest-search icon-" + (this.state.loading && this.state.showComplete ? 'refresh rotating' : 'search')}/>
                        <ReactAutoSuggest
                            ref="autosuggest"
                            cache={false}
                            showWhen = {input => true }
                            inputAttributes={inputAttributes}
                            suggestions={this.getSuggestions}
                            suggestionRenderer={this.props.renderSuggestion}
                            suggestionValue={this.suggestionValue}
                            onSuggestionSelected={this.onSuggestionSelected}
                        />
                    </div>
                );
            }
        }

    });

    var UserCreationForm = React.createClass({

        propTypes:{
            newUserName:React.PropTypes.string.isRequired
        },

        getParameters: function(){
            if(!this._parsedParameters){
                this._parsedParameters = PydioUsers.Client.getCreateUserParameters();
            }
            return this._parsedParameters;
        },

        getValuesForPost: function(prefix){
            return PydioForm.Manager.getValuesForPOST(this.getParameters(),this.state.values,prefix);
        },

        getInitialState: function(){
            return {
                values:{
                    new_user_id:this.props.newUserName,
                    lang:global.pydio.currentLanguage,
                    new_password:'',
                    send_email:true
                }
            };
        },

        onValuesChange:function(newValues){
            this.setState({values:newValues});
        },

        render:function(){
            return <PydioForm.FormPanel
                className="reset-pydio-forms"
                depth={-1}
                parameters={this.getParameters()}
                values={this.state.values}
                onChange={this.onValuesChange}
            />
        }
    });


    var ns = global.UsersCompleter || {};
    ns.Input = UsersLoader;
    global.UsersCompleter = ns;

})(window);