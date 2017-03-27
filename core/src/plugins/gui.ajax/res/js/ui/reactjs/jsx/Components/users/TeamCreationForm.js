class TeamCreationForm extends React.Component{

    static updateTeamUsers(team, operation, users, callback){
        const teamId = team.id.replace('/AJXP_TEAM/', '');
        const clearUserCache = function(uId){
            MetaCacheService.getInstance().deleteKey('user_public_data-rich', uId);
        };
        if(operation === 'add'){
            users.forEach((user) => {
                const userId = user.getId ? user.getId() : user.id;
                PydioUsers.Client.addUserToTeam(teamId, userId, callback);
                clearUserCache(userId);
            });
        }else if(operation === 'delete'){
            users.forEach((user) => {
                const userId = user.getId ? user.getId() : user.id;
                PydioUsers.Client.removeUserFromTeam(teamId, userId, callback);
                clearUserCache(userId);
            });
        }else if(operation === 'create'){
            PydioUsers.Client.saveSelectionAsTeam(teamId, users, callback);
            users.forEach((user) => {
                clearUserCache(user.getId ? user.getId() : user.id);
            })
        }
    }

    constructor(props, context){
        super(props, context);
        this.state = {value : ''};
    }

    onChange(e,value){
        this.setState({value: value});
    }

    submitCreationForm(){
        const value = this.state.value;
        TeamCreationForm.updateTeamUsers({id: value}, 'create', [], this.props.onTeamCreated);
    }

    render(){
        return (
            <div style={{padding: 20}}>
                <div>Choose a name for your team, and you will add users to it after creation.</div>
                <MaterialUI.TextField floatingLabelText="Team Label" value={this.state.value} onChange={this.onChange.bind(this)} fullWidth={true}/>
                <div>
                    <div style={{textAlign:'right', paddingTop:10}}>
                        <MaterialUI.FlatButton label={"Create Team"} secondary={true} onTouchTap={this.submitCreationForm.bind(this)} />
                        <MaterialUI.FlatButton label={pydio.MessageHash[49]} onTouchTap={this.props.onCancel.bind(this)} />
                    </div>
                </div>
            </div>
        );
    }

}
TeamCreationForm.propTypes = {
    onTeamCreated: React.PropTypes.func.isRequired,
    onCancel: React.PropTypes.func.isRequired
};

export {TeamCreationForm as default}