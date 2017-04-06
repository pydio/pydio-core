const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {TextField, SelectField, MenuItem} = require('material-ui')

let NotificationPanel = React.createClass({

    dropDownChange:function(event, index, value){
        this.props.shareModel.setGlobal('watch', (value!=='no_watch'));
    },

    render: function(){
        const menuItems = [
            <MenuItem value="no_watch" primaryText={this.props.getMessage('187')}/>,
            <MenuItem value="watch_read" primaryText={this.props.getMessage('184')}/>
        ];

        const selectedIndex = this.props.shareModel.getGlobal('watch') ? 'watch_read' : 'no_watch';

        const unusedLegend = <div className="form-legend">{this.props.getMessage('188')}</div>;
        return (
            <div style={this.props.style}>
                <SelectField
                    disabled={this.props.isReadonly()}
                    fullWidth={true}
                    value={selectedIndex}
                    onChange={this.dropDownChange}
                    floatingLabelText={this.props.getMessage('218')}
                >{menuItems}</SelectField>
            </div>
        );
    }
});

NotificationPanel = ShareContextConsumer(NotificationPanel)
export default NotificationPanel