const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {TextField, SelectField, MenuItem} = require('material-ui')

let PublicLinkTemplate = React.createClass({

    propTypes:{
        linkData:React.PropTypes.object
    },

    onDropDownChange: function(event, index, value){
        this.props.shareModel.setTemplate(this.props.linkData.hash, value);
    },

    render: function(){
        let crtLabel;
        let selected = this.props.shareModel.getTemplate(this.props.linkData.hash);
        const menuItems=this.props.layoutData.map(function(l){
            if(selected && l.LAYOUT_ELEMENT === selected) {
                crtLabel = l.LAYOUT_LABEL;
            }
            if(!selected && !crtLabel) {
                selected = l.LAYOUT_ELEMENT, crtLabel = l.LAYOUT_LABEL;
            }
            return <MenuItem key={l.LAYOUT_ELEMENT} value={l.LAYOUT_ELEMENT} primaryText={l.LAYOUT_LABEL}/>;
        });
        const unusedLegend = <div className="form-legend">{this.props.getMessage('198')}</div>;
        return (
            <div style={this.props.style}>
                <SelectField
                    fullWidth={true}
                    value={selected}
                    onChange={this.onDropDownChange}
                    disabled={this.props.isReadonly()}
                    floatingLabelText={this.props.getMessage('151')}
                >{menuItems}</SelectField>
            </div>
        );
    }
});

PublicLinkTemplate = ShareContextConsumer(PublicLinkTemplate)
export default PublicLinkTemplate