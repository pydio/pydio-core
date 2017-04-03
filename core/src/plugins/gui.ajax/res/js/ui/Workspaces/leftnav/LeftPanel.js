import UserWidget from './UserWidget'
import WorkspacesList from '../wslist/WorkspacesList'

let LeftPanel = React.createClass({

    propTypes: {
        pydio: React.PropTypes.instanceOf(Pydio).isRequired,
        userWidgetProps: React.PropTypes.object,
        workspacesListProps: React.PropTypes.object,
        style: React.PropTypes.object
    },

    render: function(){
        const palette = this.props.muiTheme.palette;
        const Color = MaterialUI.Color;
        const propStyle = this.props.style || {};
        const colorHue = Color(palette.primary1Color).hsl().array()[0];
        const lightBg = new Color({h:colorHue,s:35,l:98});
        const style = {
            ...propStyle,
            backgroundColor: lightBg
        };
        const widgetStyle = {
            backgroundColor: Color(palette.primary1Color).darken(0.2),
            width:'100%'
        };
        const wsListStyle = {
            overflowY: 'auto',
            color    : Color(palette.primary1Color).darken(0.1).alpha(0.87)
        };
        const wsSectionTitleStyle = {
            color    : Color(palette.primary1Color).darken(0.1).alpha(0.50)
        };
        const uWidgetProps = this.props.userWidgetProps || {};
        const wsListProps = this.props.workspacesListProps || {};
        return (
            <div className="left-panel vertical_fit vertical_layout" style={style}>
                <UserWidget
                    pydio={this.props.pydio}
                    style={widgetStyle}
                    {...uWidgetProps}
                />
                <WorkspacesList
                    className={"vertical_fit"}
                    style={wsListStyle}
                    sectionTitleStyle={wsSectionTitleStyle}
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