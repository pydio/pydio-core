import AddressBook from '../addressbook/AddressBook'

class ActionsPanel extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {showTeamPicker : false, teamPickerAnchor: null};
    }

    onTeamSelected(item){
        if(item.getType() === 'group' && item.getId().indexOf('/AJXP_TEAM/') === 0){
            PydioUsers.Client.addUserToTeam(item.getId().replace('/AJXP_TEAM/', ''), this.props.userId, this.props.reloadAction);
        }
    }

    openTeamPicker(event){
        this.setState({showTeamPicker: true, teamPickerAnchor: event.currentTarget});
    }

    render(){

        const styles = {
            button: {
                backgroundColor: this.props.muiTheme.palette.accent2Color,
                borderRadius: '50%',
                margin: '0 4px'
            },
            icon : {
                color: 'white'
            }
        }

        let actions = [];
        if(this.props.user.hasEmail){
            actions.push({key:'message', label:'Send Message', icon:'email'});
        }
        actions.push({key:'teams', label:'Add to team', icon:'account-multiple-plus', callback:this.openTeamPicker.bind(this)});
        if(this.props.userEditable){
            actions.push({key:'edit', label:'Edit user', icon:'pencil', callback:this.props.onEditAction});
            actions.push({key:'delete', label:'Delete user', icon:'delete', callback:this.props.onDeleteAction});
        }

        return (
            <div style={{textAlign:'center'}}>
                {actions.map(function(a){
                    return <MaterialUI.IconButton
                        key={a.key}
                        style={styles.button}
                        iconStyle={styles.icon}
                        tooltip={a.label}
                        iconClassName={"mdi mdi-" + a.icon}
                        onTouchTap={a.callback}
                    />
                })}
                {<MaterialUI.Popover
                    open={this.state.showTeamPicker}
                    anchorEl={this.state.teamPickerAnchor}
                    anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                    targetOrigin={{horizontal: 'right', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showTeamPicker: false})}}
                >
                    <div style={{width: 256, height: 320}}>
                        <AddressBook
                            mode="selector"
                            pydio={this.props.pydio}
                            loaderStyle={{width: 320, height: 420}}
                            onItemSelected={this.onTeamSelected.bind(this)}
                            teamsOnly={true}
                        />
                    </div>
                </MaterialUI.Popover>}
            </div>
        );

    }

}

ActionsPanel = MaterialUI.Style.muiThemeable()(ActionsPanel)

export {ActionsPanel as default}