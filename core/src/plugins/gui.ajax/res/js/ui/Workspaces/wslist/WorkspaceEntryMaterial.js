class WorkspaceEntryMaterial extends React.Component{

    onClick(){
        if(this.props.workspace.getId() === this.props.pydio.user.activeRepository && this.props.showFoldersTree){
            this.props.pydio.goTo('/');
        }else{
            this.props.pydio.triggerRepositoryChange(this.props.workspace.getId());
        }
    }

    render(){

        const {workspace, muiTheme} = this.props;
        let leftAvatar, leftIcon;
        let backgroundColor = muiTheme.palette.primary1Color;
        let color = 'white';
        if(workspace.getOwner() || workspace.getAccessType() === 'inbox'){
            backgroundColor = MaterialUI.Style.colors.teal500;
            let icon = workspace.getAccessType() === 'inbox' ? 'file-multiple' : 'folder';
            if(workspace.getRepositoryType() === 'remote') icon = 'cloud-outline';
            leftAvatar =  <MaterialUI.Avatar backgroundColor={backgroundColor} color={color} icon={<MaterialUI.FontIcon className={'mdi mdi-' + icon}/>}/>
        }else{
            leftAvatar = <MaterialUI.Avatar backgroundColor={backgroundColor} color={color}>{workspace.getLettersBadge()}</MaterialUI.Avatar>;
        }
        return (
            <MaterialUI.ListItem
                leftAvatar={leftAvatar}
                leftIcon={leftIcon}
                primaryText={workspace.getLabel()}
                secondaryText={workspace.getDescription()}
                onTouchTap={this.onClick.bind(this)}
            />
        );

    }

}

WorkspaceEntryMaterial.propTypes = {
    pydio    : React.PropTypes.instanceOf(Pydio).isRequired,
    workspace: React.PropTypes.instanceOf(Repository).isRequired,
    muiTheme : React.PropTypes.object
};

WorkspaceEntryMaterial = MaterialUI.Style.muiThemeable()(WorkspaceEntryMaterial);

export {WorkspaceEntryMaterial as default}