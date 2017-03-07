import ActionDialogMixin from './ActionDialogMixin'
import CancelButtonProviderMixin from './CancelButtonProviderMixin'
import SubmitButtonProviderMixin from './SubmitButtonProviderMixin'

export default React.createClass({

    propTypes: {
        dialogTitleId:React.PropTypes.integer,
        legendId:React.PropTypes.integer,
        fieldLabelId:React.PropTypes.integer,
        submitValue:React.PropTypes.func.isRequired,
        defaultValue:React.PropTypes.string,
        defaultInputSelection:React.PropTypes.string
    },

    mixins:[
        ActionDialogMixin,
        CancelButtonProviderMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: '',
            dialogIsModal: true
        };
    },
    submit(){
        this.props.submitValue(this.refs.input.getValue());
        this.dismiss();
    },
    render: function(){
        return (
            <div>
                <div className="dialogLegend">{MessageHash[this.props.legendId]}</div>
                <MaterialUI.TextField
                    floatingLabelText={MessageHash[this.props.fieldLabelId]}
                    ref="input"
                    onKeyDown={this.submitOnEnterKey}
                    defaultValue={this.props.defaultValue}
                />
            </div>
        );
    }

});
