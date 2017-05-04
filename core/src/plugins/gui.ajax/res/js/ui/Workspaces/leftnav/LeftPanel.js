const React = require('react')
const Pydio = require('pydio')
const {muiThemeable} = require('material-ui/styles')
import UserWidget from './UserWidget'
import WorkspacesList from '../wslist/WorkspacesList'

let LeftPanel = ({muiTheme, style={}, userWidgetProps, workspacesListProps, pydio}) => {

        const palette = muiTheme.palette;
        const Color = require('color');
        const colorHue = Color(palette.primary1Color).hsl().array()[0];
        const lightBg = new Color({h:colorHue,s:35,l:98});

        style = {
            backgroundColor: lightBg.toString(),
            ...style
        };
        const widgetStyle = {
            backgroundColor: Color(palette.primary1Color).darken(0.2).toString(),
            width:'100%'
        };
        const wsListStyle = {
            overflowY           : 'auto',
            backgroundColor     : lightBg.toString(),
            color               : Color(palette.primary1Color).darken(0.1).alpha(0.87).toString()
        };
        const wsSectionTitleStyle = {
            color    : Color(palette.primary1Color).darken(0.1).alpha(0.50).toString()
        };
        const uWidgetProps = userWidgetProps || {};
        const wsListProps = workspacesListProps || {};

        return (
            <div className="left-panel vertical_fit vertical_layout" style={style}>
                <UserWidget
                    pydio={pydio}
                    style={widgetStyle}
                    {...uWidgetProps}
                />
                <WorkspacesList
                    className={"vertical_fit"}
                    style={wsListStyle}
                    sectionTitleStyle={wsSectionTitleStyle}
                    pydio={pydio}
                    workspaces={pydio.user ? pydio.user.getRepositoriesList() : []}
                    showTreeForWorkspace={pydio.user?pydio.user.activeRepository:false}
                    {...wsListProps}
                />
            </div>
        );
};

LeftPanel.propTypes = {
    pydio               : React.PropTypes.instanceOf(Pydio).isRequired,
    userWidgetProps     : React.PropTypes.object,
    workspacesListProps : React.PropTypes.object,
    style               : React.PropTypes.object
};

LeftPanel = muiThemeable()(LeftPanel);

export {LeftPanel as default}
