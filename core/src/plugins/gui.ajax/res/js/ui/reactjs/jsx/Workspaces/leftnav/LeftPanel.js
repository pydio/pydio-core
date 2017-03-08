import UserWidget from './UserWidget'
import WorkspacesList from '../wslist/WorkspacesList'

let LeftPanel = React.createClass({

    propTypes: {
        pydio: React.PropTypes.instanceOf(Pydio).isRequired
    },

    render: function(){
        const palette = this.props.muiTheme.palette;
        const Color = MaterialUI.Color;
        const widgetStyle = {
            backgroundColor: Color(palette.primary1Color).darken(0.2)
        };
        return (
            <div className="left-panel vertical_fit vertical_layout">
                <UserWidget pydio={this.props.pydio} style={widgetStyle}/>
                <WorkspacesList
                    className={"vertical_fit"}
                    style={{overflowY:'auto'}}
                    pydio={this.props.pydio}
                    workspaces={this.props.pydio.user ? this.props.pydio.user.getRepositoriesList() : []}
                    showTreeForWorkspace={this.props.pydio.user?this.props.pydio.user.activeRepository:false}
                />
            </div>
        );
    }
});

LeftPanel = MaterialUI.Style.muiThemeable()(LeftPanel);

export {LeftPanel as default}