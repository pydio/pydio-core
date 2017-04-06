const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {TextField, DropDownMenu} = require('material-ui')

let NotificationPanel = React.createClass({

    dropDownChange:function(event, index, item){
        this.props.shareModel.setGlobal('watch', (index!=0));
    },

    render: function(){
        const menuItems = [
            {payload:'no_watch', text:this.props.getMessage('187')},
            {payload:'watch_read', text:this.props.getMessage('184')}
            /*,{payload:'watch_write', text:'Notify me when share is modified'}*/
        ];
        const selectedIndex = this.props.shareModel.getGlobal('watch') ? 1 : 0;
        let element;
        if(this.props.isReadonly()){
            element = <TextField disabled={true} value={menuItems[selectedIndex].text} style={{width:'100%'}}/>
        }else{
            element = (
                <DropDownMenu
                    autoWidth={false}
                    className="full-width"
                    menuItems={menuItems}
                    selectedIndex={selectedIndex}
                    onChange={this.dropDownChange}
                />
            );
        }
        return (
            <div style={this.props.style}>
                <h3>{this.props.getMessage('218')}</h3>
                {element}
                <div className="form-legend">{this.props.getMessage('188')}</div>
            </div>
        );
    }
});

NotificationPanel = ShareContextConsumer(NotificationPanel)
export default NotificationPanel