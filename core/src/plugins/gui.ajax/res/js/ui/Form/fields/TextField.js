import FormMixin from '../mixins/FormMixin'
const React = require('react')
const ReactMUI = require('material-ui-legacy')

/**
 * Text input, can be single line, multiLine, or password, depending on the
 * attributes.type key.
 */
export default React.createClass({

    mixins:[FormMixin],

    render:function(){
        if(this.isDisplayGrid() && !this.state.editMode){
            let value = this.state.value;
            if(this.props.attributes['type'] === 'password' && value){
                value = '***********';
            }else{
                value = this.state.value;
            }
            return <div onClick={this.props.disabled?function(){}:this.toggleEditMode} className={value?'':'paramValue-empty'}>{!value?'Empty':value}</div>;
        }else{
            let field = (
                <ReactMUI.TextField
                    floatingLabelText={this.isDisplayForm()?this.props.attributes.label:null}
                    value={this.state.value || ""}
                    onChange={this.onChange}
                    onKeyDown={this.enterToToggle}
                    type={this.props.attributes['type'] == 'password'?'password':null}
                    multiLine={this.props.attributes['type'] == 'textarea'}
                    disabled={this.props.disabled}
                    errorText={this.props.errorText}
                    autoComplete="off"
                />
            );
            if(this.props.attributes['type'] === 'password'){
                return (
                    <form autoComplete="off" style={{display:'inline'}}>{field}</form>
                );
            }else{
                return(
                    <span>{field}</span>
                );
            }
        }
    }

});
