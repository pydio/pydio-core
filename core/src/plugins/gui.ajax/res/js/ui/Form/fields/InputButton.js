import ActionRunnerMixin from '../mixins/ActionRunnerMixin'
const React = require('react')
const {RaisedButton} = require('material-ui')

/**
 * Simple RaisedButton executing the applyButtonAction
 */
export default React.createClass({

    mixins:[ActionRunnerMixin],

    applyButton:function(){

        let callback = this.props.actionCallback;
        if(!callback){
            callback = function(transport){
                const text = transport.responseText;
                if(text.startsWith('SUCCESS:')){
                    global.pydio.displayMessage('SUCCESS', transport.responseText.replace('SUCCESS:', ''));
                }else{
                    global.pydio.displayMessage('ERROR', transport.responseText.replace('ERROR:', ''));
                }
            };
        }
        this.applyAction(callback);

    },

    render:function(){
        return (
            <RaisedButton
                label={this.props.attributes['label']}
                onTouchTap={this.applyButton}
                disabled={this.props.disabled}
            />
        );
    }
});
