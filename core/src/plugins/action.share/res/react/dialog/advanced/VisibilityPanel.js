const React = require('react');
import ShareContextConsumer from '../ShareContextConsumer'
const {Checkbox, RaisedButton, TextField} = require('material-ui')

let VisibilityPanel = React.createClass({

    toggleVisibility: function(){
        this.props.shareModel.toggleVisibility();
    },
    transferOwnership: function(){
        this.props.shareModel.setNewShareOwner(this.refs['newOwner'].getValue());
    },
    render: function(){
        var currentIsOwner = this.props.shareModel.currentIsOwner();

        var legend;
        if(this.props.shareModel.isPublic()){
            if(currentIsOwner){
                legend = this.props.getMessage('201');
            }else{
                legend = this.props.getMessage('202');
            }
        }else{
            legend = this.props.getMessage('206');
        }
        var showToggle = (
            <div>
                <Checkbox type="checkbox"
                                   name="share_visibility"
                                   disabled={!currentIsOwner || this.props.isReadonly()}
                                   onCheck={this.toggleVisibility}
                                   checked={this.props.shareModel.isPublic()}
                                   label={this.props.getMessage('200')}
                />
                <div className="section-legend">{legend}</div>
            </div>
        );
        if(this.props.shareModel.isPublic() && currentIsOwner && !this.props.isReadonly()){
            var showTransfer = (
                <div className="ownership-form">
                    <h4>{this.props.getMessage('203')}</h4>
                    <div className="section-legend">{this.props.getMessage('204')}</div>
                    <div>
                        <TextField ref="newOwner" floatingLabelText={this.props.getMessage('205')}/>
                        <RaisedButton label={this.props.getMessage('203b')} onClick={this.transferOwnership}/>
                    </div>
                </div>
            );
        }
        return (
            <div className="reset-pydio-forms ie_material_checkbox_fix">
                <h3>{this.props.getMessage('199')}</h3>
                {showToggle}
                {showTransfer}
            </div>
        );
    }
});

VisibilityPanel = ShareContextConsumer(VisibilityPanel)
export default VisibilityPanel