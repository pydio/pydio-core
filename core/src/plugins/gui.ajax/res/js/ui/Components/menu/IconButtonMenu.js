const React = require('react')
const {IconButton, Popover} = require('material-ui')
import Utils from './Utils'

class IconButtonMenu extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {showMenu: false};
    }

    showMenu(event){
        this.setState({
            showMenu: true,
            anchor: event.currentTarget
        })
    }

    closeMenu(event, index, menuItem){
        this.setState({showMenu: false});
    }

    render(){
        return (
            <span className={"toolbars-button-menu " + (this.props.className ? this.props.className  : '')}>
                    <IconButton
                        ref="menuButton"
                        tooltip={this.props.buttonTitle}
                        iconClassName={this.props.buttonClassName}
                        onTouchTap={this.showMenu.bind(this)}
                        iconStyle={this.props.buttonStyle}
                    />
                    <Popover
                        open={this.state.showMenu}
                        anchorEl={this.state.anchor}
                        anchorOrigin={{horizontal: this.props.direction || 'right', vertical: 'bottom'}}
                        targetOrigin={{horizontal: this.props.direction || 'right', vertical: 'top'}}
                        onRequestClose={() => {this.setState({showMenu: false})}}
                        useLayerForClickAway={false}
                    >
                        {Utils.itemsToMenu(this.props.menuItems, this.closeMenu.bind(this))}
                    </Popover>
                </span>
        );
    }
}

IconButtonMenu.propTypes =  {
    buttonTitle: React.PropTypes.string.isRequired,
    buttonClassName: React.PropTypes.string.isRequired,
    className: React.PropTypes.string,
    direction: React.PropTypes.oneOf(['right', 'left']),
    menuItems: React.PropTypes.array.isRequired
}

export {IconButtonMenu as default}