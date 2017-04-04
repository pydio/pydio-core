import FormMixin from '../mixins/FormMixin'
const React = require('react')
const ReactMUI = require('material-ui-legacy')

/**
 * Text input that is converted to integer, and
 * the UI can react to arrows for incrementing/decrementing values
 */
export default React.createClass({

    mixins:[FormMixin],

    keyDown: function(event){
        let inc = 0, multiple=1;
        if(event.key == 'Enter'){
            this.toggleEditMode();
            return;
        }else if(event.key == 'ArrowUp'){
            inc = +1;
        }else if(event.key == 'ArrowDown'){
            inc = -1;
        }
        if(event.shiftKey){
            multiple = 10;
        }
        let parsed = parseInt(this.state.value);
        if(isNaN(parsed)) parsed = 0;
        const value = parsed + (inc * multiple);
        this.onChange(null, value);
    },

    render:function(){
        if(this.isDisplayGrid() && !this.state.editMode){
            const value = this.state.value;
            return <div onClick={this.props.disabled?function(){}:this.toggleEditMode} className={value?'':'paramValue-empty'}>{!value?'Empty':value}</div>;
        }else{
            let intval;
            if(this.state.value){
                intval = parseInt(this.state.value) + '';
                if(isNaN(intval)) intval = this.state.value + '';
            }else{
                intval = '0';
            }
            return(
                <span className="integer-input">
                    <ReactMUI.TextField
                        value={intval}
                        onChange={this.onChange}
                        onKeyDown={this.keyDown}
                        disabled={this.props.disabled}
                        floatingLabelText={this.isDisplayForm()?this.props.attributes.label:null}
                    />
                </span>
            );
        }
    }

});