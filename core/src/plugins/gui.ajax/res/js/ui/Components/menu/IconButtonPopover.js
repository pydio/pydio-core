const React = require('react')
const {IconButton, Popover} = require('material-ui')

class IconButtonPopover extends React.Component{

    constructor(props, context){
        super(props, context);
        this.state = {showPopover: false};
    }

    showPopover(event){
        this.setState({
            showPopover: true,
            anchor: event.currentTarget
        })
    }

    render(){
        return (
            <span className={"toolbars-button-menu " + (this.props.className ? this.props.className  : '')}>
                <IconButton
                    ref="menuButton"
                    tooltip={this.props.buttonTitle}
                    iconClassName={this.props.buttonClassName}
                    onTouchTap={this.showPopover.bind(this)}
                    iconStyle={this.props.buttonStyle}
                />
                <Popover
                    open={this.state.showPopover}
                    anchorEl={this.state.anchor}
                    anchorOrigin={{horizontal: this.props.direction || 'right', vertical: 'bottom'}}
                    targetOrigin={{horizontal: this.props.direction || 'right', vertical: 'top'}}
                    onRequestClose={() => {this.setState({showPopover: false})}}
                    useLayerForClickAway={false}
                >
                    {this.props.popoverContent}
                </Popover>
            </span>
        );
    }

}

IconButtonPopover.propTypes = {
    buttonTitle: React.PropTypes.string.isRequired,
    buttonClassName: React.PropTypes.string.isRequired,
    className: React.PropTypes.string,
    direction: React.PropTypes.oneOf(['right', 'left']),
    popoverContent: React.PropTypes.object.isRequired
}

export default IconButtonPopover