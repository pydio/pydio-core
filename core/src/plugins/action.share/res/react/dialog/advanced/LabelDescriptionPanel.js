const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {TextField} = require('material-ui')

let LabelDescriptionPanel = React.createClass({

    updateLabel: function(event){
        this.props.shareModel.setGlobal("label", event.currentTarget.value);
    },

    updateDescription: function(event){
        this.props.shareModel.setGlobal("description", event.currentTarget.value);
    },

    render: function(){
        let label, labelLegend;
        if(!this.props.shareModel.getNode().isLeaf()){
            label = (
                <TextField
                    disabled={this.props.isReadonly()}
                    floatingLabelText={this.props.getMessage('35')}
                    name="label"
                    onChange={this.updateLabel}
                    value={this.props.shareModel.getGlobal('label')}
                    fullWidth={true}
                />
            );
            labelLegend = (
                <div className="form-legend">{this.props.getMessage('146')}</div>
            );
        }
        return (
            <div style={this.props.style}>
                <h3>{this.props.getMessage('145')}</h3>
                <div className="label-desc-edit">
                    {label}
                    {labelLegend}
                    <TextField
                        disabled={this.props.isReadonly()}
                        floatingLabelText={this.props.getMessage('145')}
                        name="description"
                        onChange={this.updateDescription}
                        value={this.props.shareModel.getGlobal('description')}
                        fullWidth={true}
                    />
                    <div className="form-legend">{this.props.getMessage('197')}</div>
                </div>
            </div>
        );
    }
});

LabelDescriptionPanel = ShareContextConsumer(LabelDescriptionPanel);

export {LabelDescriptionPanel as default}