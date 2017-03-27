import AddressBook from '../addressbook/AddressBook'

class ActionsPanel extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {showPicker : false, pickerAnchor: null, showMailer: false, mailerAnchor: null};
    }

    onTeamSelected(item){
        this.setState({showPicker: false});
        if(item.getType() === 'group' && item.getId().indexOf('/AJXP_TEAM/') === 0){
            PydioUsers.Client.addUserToTeam(item.getId().replace('/AJXP_TEAM/', ''), this.props.userId, this.props.reloadAction);
        }
    }
    
    onUserSelected(item){
        this.setState({showPicker: false});
        PydioUsers.Client.addUserToTeam(this.props.team.id, item.getId(), this.props.reloadAction);
    }

    openPicker(event){
        this.setState({showPicker: true, pickerAnchor: event.currentTarget});
    }

    openMailer(event){

        const target = event.currentTarget;
        ResourcesManager.loadClassesAndApply(['PydioMailer'], () => {
            this.setState({mailerLibLoaded: true}, () => {
                this.setState({showMailer: true, mailerAnchor: target});
            });
        });
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
        let mailer, usermails = {};
        let actions = [];
        if(this.props.user && this.props.user.hasEmail){
            actions.push({key:'message', label:'Send Message', icon:'email', callback: this.openMailer.bind(this)});
            usermails[this.props.user.id] = PydioUsers.User.fromObject(this.props.user);
        }
        if(this.props.team){
            actions.push({key:'users', label:'Add user', icon:'account-multiple-plus', callback:this.openPicker.bind(this)});
        }else{
            actions.push({key:'teams', label:'Add to team', icon:'account-multiple-plus', callback:this.openPicker.bind(this)});
        }
        if(this.props.userEditable){
            actions.push({key:'edit', label:'Edit user', icon:'pencil', callback:this.props.onEditAction});
            actions.push({key:'delete', label:'Delete user', icon:'delete', callback:this.props.onDeleteAction});
        }

        return (
            <div style={{textAlign:'center', marginBottom: 16}}>
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
                <MaterialUI.Popover
                    open={this.state.showPicker}
                    anchorEl={this.state.pickerAnchor}
                    anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                    targetOrigin={{horizontal: 'right', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showPicker: false})}}
                >
                    <div style={{width: 256, height: 320}}>
                        <AddressBook
                            mode="selector"
                            pydio={this.props.pydio}
                            loaderStyle={{width: 320, height: 420}}
                            onItemSelected={this.props.team ? this.onUserSelected.bind(this) : this.onTeamSelected.bind(this)}
                            teamsOnly={this.props.team ? false: true}
                            usersOnly={this.props.team ? true: false}
                        />
                    </div>
                </MaterialUI.Popover>
                <MaterialUI.Popover
                    open={this.state.showMailer}
                    anchorEl={this.state.mailerAnchor}
                    anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                    targetOrigin={{horizontal: 'right', vertical: 'top'}}
                >
                    <div style={{width: 256, height: 320}}>
                        {this.state.mailerLibLoaded &&
                        <PydioMailer.Pane
                            zDepth={0}
                            panelTitle="Send Message"
                            uniqueUserStyle={true}
                            users={usermails}
                            onDismiss={() => {this.setState({showMailer: false})}}
                        />}
                    </div>
                </MaterialUI.Popover>
            </div>
        );

    }

}

ActionsPanel = MaterialUI.Style.muiThemeable()(ActionsPanel)

export {ActionsPanel as default}