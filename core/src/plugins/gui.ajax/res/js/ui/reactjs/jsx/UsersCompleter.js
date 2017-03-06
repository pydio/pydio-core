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
(function(global) {

    var UserCreationForm = React.createClass({

        propTypes:{
            newUserName:React.PropTypes.string.isRequired,
            submitCreationForm: React.PropTypes.func.isRequired,
            cancelCreationForm: React.PropTypes.func.isRequired
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
            let userPrefix = pydio.getPluginConfigs('action.share').get('SHARED_USERS_TMP_PREFIX');
            if(!userPrefix || this.props.newUserName.startsWith(userPrefix)) userPrefix = '';
            return {
                values:{
                    new_user_id:userPrefix + this.props.newUserName,
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
            return (
                <MaterialUI.Paper zDepth={2} style={{height: 250, overflowY: 'auto'}}>
                    <PydioForm.FormPanel
                        className="reset-pydio-forms"
                        depth={-1}
                        parameters={this.getParameters()}
                        values={this.state.values}
                        onChange={this.onValuesChange}
                    />
                    <div style={{padding:16, textAlign:'right', paddingTop:0}}>
                        <MaterialUI.FlatButton label={global.pydio.MessageHash[484]} secondary={true} onClick={this.props.submitCreationForm} />
                        <MaterialUI.FlatButton label={global.pydio.MessageHash[49]} onClick={this.props.cancelCreationForm} />
                    </div>
                </MaterialUI.Paper>
            )
        }
    });

    var UsersLoader = React.createClass({

        propTypes:{

            renderSuggestion:React.PropTypes.func.isRequired,
            onValueSelected :React.PropTypes.func.isRequired,
            fieldLabel      :React.PropTypes.string.isRequired,
            excludes        :React.PropTypes.array.isRequired,
            usersOnly       :React.PropTypes.bool,
            existingOnly    :React.PropTypes.bool,
            freeValueAllowed:React.PropTypes.bool,
            className       :React.PropTypes.string

        },

        getInitialState:function(){
            return {
                dataSource  : [],
                loading     : false,
                searchText  : '',
                minChars    : parseInt(global.pydio.getPluginConfigs("core.conf").get("USERS_LIST_COMPLETE_MIN_CHARS"))
            };
        },

        suggestionLoader:function(input, callback){

            var excludes = this.props.excludes;
            var disallowTemporary = this.props.existingOnly && !this.props.freeValueAllowed;
            this.setState({loading:this.state.loading + 1});
            PydioUsers.Client.authorizedUsersStartingWith(input, function(users){
                this.setState({loading:this.state.loading - 1});
                console.log(users);
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
                callback(users);
            }.bind(this), this.props.usersOnly, this.props.existingOnly);

        },


        textFieldUpdate: function(value){

            this.setState({searchText: value});
            if(this.state.minChars && value && value.length < this.state.minChars ){
                return;
            }
            this.setState({loading: true});
            FuncUtils.bufferCallback('remote_users_search', 300, function(){
                this.suggestionLoader(value, function(users){
                    let crtValueFound = false;
                    const values = users.map(function(userObject){
                        let component = (<MaterialUI.MenuItem>{this.props.renderSuggestion(userObject)}</MaterialUI.MenuItem>);
                        return {
                            userObject  : userObject,
                            text        : userObject.getExtendedLabel(),
                            value       : component
                        };
                    }.bind(this));
                    this.setState({dataSource: values, loading: false});
                }.bind(this));
            }.bind(this));
        },

        onCompleterRequest: function(value, index){

            console.log(value, index);
            if(index === -1){
                this.state.dataSource.map(function(entry){
                    if(entry.text === value){
                        value = entry;
                    }
                });
                if(value && !value.userObject && this.props.freeValueAllowed){
                    this.props.onValueSelected(value, value, '');
                    return;
                }
            }
            if(value && value.userObject){
                const object = value.userObject;
                if(object.getTemporary()){
                    this.setState({createUser: object.getLabel()});
                }else{
                    this.props.onValueSelected(object.getId(), object.getLabel(), object.getType());
                }
                this.setState({searchText: '', dataSource:[]});
            }

        },

        submitCreationForm: function(){

            var prefix = PydioUsers.Client.getCreateUserPostPrefix();
            var values = this.refs['creationForm'].getValuesForPost(prefix);
            PydioUsers.Client.createUserFromPost(values, function(values, jsonReponse){
                let id;
                if(jsonReponse['createdUserId']){
                    id = jsonReponse['createdUserId'];
                }else{
                    id = values[prefix + 'new_user_id'];
                }
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

            const {dataSource} = this.state;
            const containerStyle = {position:'relative', overflow: 'visible'};

            if(this.state.createUser){

                return (
                    <div style={containerStyle}>
                        <MaterialUI.TextField
                            floatingLabelText={this.props.fieldLabel}
                            value={global.pydio.MessageHash[485] + ' (' + this.state.createUser + ')'}
                            disabled={true}
                            fullWidth={true}
                        />
                        <div style={{position: 'absolute', top: 73, left: 0, right: 0, zIndex: 10}}>
                            <UserCreationForm
                                ref="creationForm"
                                newUserName={this.state.createUser}
                                submitCreationForm={this.submitCreationForm}
                                cancelCreationForm={this.cancelCreationForm}
                            />
                        </div>
                    </div>
                );

            }

            return (
                <div style={containerStyle}>
                    <MaterialUI.AutoComplete
                        filter={MaterialUI.AutoComplete.noFilter}
                        dataSource={dataSource}
                        searchText={this.state.searchText}
                        onUpdateInput={this.textFieldUpdate}
                        className={this.props.className}
                        openOnFocus={true}
                        floatingLabelText={this.props.fieldLabel}
                        fullWidth={true}
                        onNewRequest={this.onCompleterRequest}
                        listStyle={{maxHeight: 350, overflowY: 'auto'}}
                    />
                    <div style={{position:'absolute', right:4, bottom: 14, height: 20, width: 20}}>
                        <MaterialUI.RefreshIndicator
                            size={20}
                            left={0}
                            top={0}
                            status={this.state.loading ? 'loading' : 'hide' }
                        />
                    </div>
                </div>
            );

        }

    });

    var ns = global.UsersCompleter || {};
    ns.Input = UsersLoader;
    global.UsersCompleter = ns;

})(window);