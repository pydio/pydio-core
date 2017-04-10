const React = require('react')
const {ListItem, Avatar, FontIcon} = require('material-ui')
const {muiThemeable} = require('material-ui/styles')

const Pydio = require('pydio')
const Repository = require('pydio/model/repository')

class WorkspaceEntryMaterial extends React.Component{

    onClick(){
        if(this.props.onWorkspaceTouchTap){
            this.props.onWorkspaceTouchTap(this.props.workspace.getId());
            return;
        }
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
            leftAvatar =  <Avatar backgroundColor={backgroundColor} color={color} icon={<FontIcon className={'mdi mdi-' + icon}/>}/>
        }else{
            leftAvatar = <Avatar backgroundColor={backgroundColor} color={color}>{workspace.getLettersBadge()}</Avatar>;
        }
        return (
            <ListItem
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

WorkspaceEntryMaterial = muiThemeable()(WorkspaceEntryMaterial);
export {WorkspaceEntryMaterial as default}