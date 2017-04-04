import FormMixin from '../mixins/FormMixin'
const React = require('react')
const ReactMUI = require('material-ui-legacy')


/**
 * Checkboxk input
 */
export default React.createClass({

    mixins:[FormMixin],

    getDefaultProps:function(){
        return {
            skipBufferChanges:true
        };
    },

    componentDidUpdate:function(){
        // Checkbox Hack
        const boolVal = this.getBooleanState();
        if(this.refs.checkbox && !this.refs.checkbox.isChecked() && boolVal){
            this.refs.checkbox.setChecked(true);
        }else if(this.refs.checkbox && this.refs.checkbox.isChecked() && !boolVal){
            this.refs.checkbox.setChecked(false);
        }
    },

    onCheck:function(event){
        const newValue = this.refs.checkbox.isChecked();
        this.props.onChange(newValue, this.state.value);
        this.setState({
            dirty:true,
            value:newValue
        });
    },

    getBooleanState:function(){
        let boolVal = this.state.value;
        if(typeof(boolVal) == 'string'){
            boolVal = (boolVal == "true");
        }
        return boolVal;
    },

    render:function(){
        const boolVal = this.getBooleanState();
        return(
            <span>
                <ReactMUI.Checkbox
                    ref="checkbox"
                    defaultSwitched={boolVal}
                    disabled={this.props.disabled}
                    onCheck={this.onCheck}
                    label={this.isDisplayForm()?this.props.attributes.label:null}
                    labelPosition={this.isDisplayForm()?'left':'right'}
                />
            </span>
        );
    }

});
