import Utils from './Utils'
import PopupMenu from './PopupMenu'

export default React.createClass({

    propTypes:{
        buttonTitle: React.PropTypes.oneOfType([React.PropTypes.string,React.PropTypes.object]).isRequired,
        className: React.PropTypes.string,
        menuItems:React.PropTypes.array,
        selectionContext:React.PropTypes.string,
        toolbars:React.PropTypes.array,
        raised:React.PropTypes.bool,
        direction: React.PropTypes.oneOf(['left', 'right'])
    },

    getInitialState: function(){
        return {showMenu: false, menuItems: this.props.menuItems || []};
    },

    componentDidMount: function(){
        if(this.props.controller && !this.props.menuItems){
            this._observer = () => {
                const actions = this.props.controller.getContextActions('genericContext', null, this.props.toolbars);
                const menuItems = Utils.pydioActionsToItems(actions);
                this.setState({menuItems: menuItems});
            };
            if(this.props.controller === pydio.Controller){
                pydio.observe("actions_refreshed", this._observer);
            }else{
                this.props.controller.observe("actions_refreshed", this._observer);
            }
            this._observer();
        }
    },

    componentWillUnmount: function(){
        if(this._observer){
            if(this.props.controller === pydio.Controller){
                pydio.stopObserving("actions_refreshed", this._observer);
            }else{
                this.props.controller.stopObserving("actions_refreshed", this._observer);
            }
        }
    },

    componentWillReceiveProps: function(nextProps){
        if(nextProps.menuItems && nextProps.menuItems !== this.props.menuItems){
            this.setState({menuItems: nextProps.menuItems});
        }
    },

    showMenu: function(event){
        this.setState({
            showMenu: true,
            anchor: event.currentTarget
        })
    },

    menuClicked: function(event, index, object){
        object.payload();
        this.setState({showMenu: false});
    },

    render: function(){
        let label = <span>{this.props.buttonTitle} <span className="icon-caret-down"></span></span>
        let button;
        const props = {
            primary: this.props.primary,
            secondary: this.props.secondary,
            disabled: this.props.disabeld,
            label: label,
            onTouchTap: this.showMenu
        };
        const {menuItems, showMenu, anchor} = this.state;
        if(menuItems.length){
            if(this.props.raised){
                button = <MaterialUI.RaisedButton {...props} style={this.props.buttonStyle} labelStyle={this.props.buttonLabelStyle}/>;
            }else{
                button = <MaterialUI.FlatButton {...props} style={this.props.buttonStyle} labelStyle={this.props.buttonLabelStyle}/>;
            }
        }
        return (
            <span id={this.props.id} className={this.props.className}>
                {button}
                <MaterialUI.Popover
                    className="menuPopover"
                    open={showMenu}
                    anchorEl={anchor}
                    anchorOrigin={{horizontal: this.props.direction || 'left', vertical: 'bottom'}}
                    targetOrigin={{horizontal: this.props.direction || 'left', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showMenu: false})}}
                >
                    <ReactMUI.Menu
                        onItemClick={this.menuClicked}
                        menuItems={menuItems}
                    />
                </MaterialUI.Popover>
            </span>
        );
    }

});
