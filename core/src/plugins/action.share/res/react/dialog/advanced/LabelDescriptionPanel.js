const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {TextField, Subheader} = require('material-ui')

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
                    floatingLabelText={this.props.getMessage('35') + ' ( '+ this.props.getMessage('146') + ' )'}
                    floatingLabelStyle={{whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'}}
                    name="label"
                    onChange={this.updateLabel}
                    value={this.props.shareModel.getGlobal('label') || ''}
                    fullWidth={true}
                />
            );
        }
        return (
            <div style={this.props.style}>
                {label}
                <TextField
                    disabled={this.props.isReadonly()}
                    floatingLabelText={this.props.getMessage('145') + ' ( '+ this.props.getMessage('197') + ' )'}
                    floatingLabelStyle={{whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'}}
                    name="description"
                    onChange={this.updateDescription}
                    value={this.props.shareModel.getGlobal('description') || ''}
                    fullWidth={true}
                />
            </div>
        );
    }
});

LabelDescriptionPanel = ShareContextConsumer(LabelDescriptionPanel);

export {LabelDescriptionPanel as default}