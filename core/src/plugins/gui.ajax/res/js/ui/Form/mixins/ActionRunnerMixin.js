const React = require('react')
const PathUtils = require('pydio/util/path')

export default {

    propTypes:{
        attributes:React.PropTypes.object.isRequired,
        applyButtonAction:React.PropTypes.func,
        actionCallback:React.PropTypes.func
    },

    applyAction:function(callback){
        const choicesValue = this.props.attributes['choices'].split(":");
        const firstPart = choicesValue.shift();
        if(firstPart === "run_client_action" && global.pydio){
            global.pydio.getController().fireAction(choicesValue.shift());
            return;
        }
        if(this.props.applyButtonAction){
            let parameters = {get_action:firstPart};
            if(choicesValue.length > 1){
                parameters['action_plugin_id'] = choicesValue.shift();
                parameters['action_plugin_method'] = choicesValue.shift();
            }
            if(this.props.attributes['name'].indexOf("/") !== -1){
                parameters['button_key'] = PathUtils.getDirname(this.props.attributes['name']);
            }
            this.props.applyButtonAction(parameters, callback);
        }
    }

};