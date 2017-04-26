const {Component, PropTypes} = require('react')
const {IconButton} = require('material-ui')
const {muiThemeable} = require('material-ui/styles');
import ShareContextConsumer from '../ShareContextConsumer'


class ActionButton extends Component{

    render(){

        const {palette} = this.props.muiTheme;

        const style = {
            root: {
                borderRadius: '50%',
                backgroundColor: palette.primary1Color,
                width: 36, height: 36,
                padding: 8,
                margin: '0 6px',
                zIndex: 0
            },
            icon: {
                color: 'white',
                fontSize: 20,
                lineHeight: '20px'
            }
        }

        return (
            <IconButton
                style={style.root}
                iconStyle={style.icon}
                onTouchTap={this.props.callback || this.props.onTouchTap}
                iconClassName={"mdi mdi-" + this.props.mdiIcon}
                tooltip={this.props.getMessage(this.props.messageId, this.props.messageCoreNamespace?'': undefined)}
            />
        );

    }

}

ActionButton.propTypes = {
    callback: PropTypes.func,
    onTouchTap: PropTypes.func,
    mdiIcon: PropTypes.string,
    messageId: PropTypes.string
};

ActionButton = ShareContextConsumer(ActionButton);
ActionButton = muiThemeable()(ActionButton);

export default ActionButton