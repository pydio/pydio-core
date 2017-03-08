import UserWidget from './UserWidget'
import WorkspacesList from '../wslist/WorkspacesList'

let LeftPanel = React.createClass({

    propTypes: {
        pydio: React.PropTypes.instanceOf(Pydio).isRequired,
        userWidgetProps: React.PropTypes.object,
        workspacesListProps: React.PropTypes.object
    },

    render: function(){
        const palette = this.props.muiTheme.palette;
        const Color = MaterialUI.Color;
        const widgetStyle = {
            backgroundColor: Color(palette.primary1Color).darken(0.2)
        };
        const uWidgetProps = this.props.userWidgetProps || {};
        const wsListProps = this.props.workspacesListProps || {};
        return (
            <div className="left-panel vertical_fit vertical_layout">
                <UserWidget
                    pydio={this.props.pydio}
                    style={widgetStyle}
                    {...uWidgetProps}
                />
                <WorkspacesList
                    className={"vertical_fit"}
                    style={{overflowY:'auto'}}
                    pydio={this.props.pydio}
                    workspaces={this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []}
                    showTreeForWorkspace={this.props.pydio.user?this.props.pydio.user.activeRepository:false}
                    {...wsListProps}
                />
            </div>
        );
    }
});

LeftPanel = MaterialUI.Style.muiThemeable()(LeftPanel);

export {LeftPanel as default}