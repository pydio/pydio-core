const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {TextField, DropDownMenu} = require('material-ui')

let PublicLinkTemplate = React.createClass({

    propTypes:{
        linkData:React.PropTypes.object
    },

    onDropDownChange: function(event, index, item){
        this.props.shareModel.setTemplate(this.props.linkData.hash, item.payload);
    },

    render: function(){
        var index = 0, crtIndex = 0;
        var selected = this.props.shareModel.getTemplate(this.props.linkData.hash);
        var menuItems=this.props.layoutData.map(function(l){
            if(selected && l.LAYOUT_ELEMENT == selected) {
                crtIndex = index;
            }
            index ++;
            return {payload:l.LAYOUT_ELEMENT, text:l.LAYOUT_LABEL};
        });
        var element;
        if(this.props.isReadonly()){
            element = <TextField disabled={true} value={menuItems[crtIndex].text} style={{width:'100%'}}/>
        }else{
            element = (
                <DropDownMenu
                    autoWidth={false}
                    className="full-width"
                    menuItems={menuItems}
                    selectedIndex={crtIndex}
                    onChange={this.onDropDownChange}
                />
            );
        }
        return (
            <div className="reset-pydio-forms">
                <h3>{this.props.getMessage('151')}</h3>
                {element}
                <div className="form-legend">{this.props.getMessage('198')}</div>
            </div>
        );
    }
});

PublicLinkTemplate = ShareContextConsumer(PublicLinkTemplate)
export default PublicLinkTemplate