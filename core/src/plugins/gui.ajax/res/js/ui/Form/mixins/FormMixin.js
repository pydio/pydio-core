const React = require('react');
const PydioApi = require('pydio/http/api')
/**
 * React Mixin for Form Element
 */
export default {

    propTypes:{
        attributes:React.PropTypes.object.isRequired,
        name:React.PropTypes.string.isRequired,

        displayContext:React.PropTypes.oneOf(['form', 'grid']),
        disabled:React.PropTypes.bool,
        multiple:React.PropTypes.bool,
        value:React.PropTypes.any,
        onChange:React.PropTypes.func,
        onChangeEditMode:React.PropTypes.func,
        binary_context:React.PropTypes.string,
        errorText:React.PropTypes.string
    },

    getDefaultProps:function(){
        return {
            displayContext:'form',
            disabled:false
        };
    },

    isDisplayGrid:function(){
        return this.props.displayContext == 'grid';
    },

    isDisplayForm:function(){
        return this.props.displayContext == 'form';
    },

    toggleEditMode:function(){
        if(this.isDisplayForm()) return;
        const newState = !this.state.editMode;
        this.setState({editMode:newState});
        if(this.props.onChangeEditMode){
            this.props.onChangeEditMode(newState);
        }
    },

    enterToToggle:function(event){
        if(event.key == 'Enter'){
            this.toggleEditMode();
        }
    },

    bufferChanges:function(newValue, oldValue){
        this.triggerPropsOnChange(newValue, oldValue);
    },

    onChange:function(event, value){
        if(value === undefined) {
            value = event.currentTarget.getValue ? event.currentTarget.getValue() : event.currentTarget.value;
        }
        if(this.changeTimeout){
            global.clearTimeout(this.changeTimeout);
        }
        const newValue = value, oldValue = this.state.value;
        if(this.props.skipBufferChanges){
            this.triggerPropsOnChange(newValue, oldValue);
        }
        this.setState({
            dirty:true,
            value:newValue
        });
        if(!this.props.skipBufferChanges) {
            let timerLength = 250;
            if(this.props.attributes['type'] === 'password'){
                timerLength = 1200;
            }
            this.changeTimeout = global.setTimeout(function () {
                this.bufferChanges(newValue, oldValue);
            }.bind(this), timerLength);
        }
    },

    triggerPropsOnChange:function(newValue, oldValue){
        if(this.props.attributes['type'] === 'password'){
            this.toggleEditMode();
            this.props.onChange(newValue, oldValue, {type:this.props.attributes['type']});
        }else{
            this.props.onChange(newValue, oldValue);
        }
    },

    componentWillReceiveProps:function(newProps){
        let choices;
        if(newProps.attributes['choices']) {
            if(newProps.attributes['choices'] != this.props.attributes['choices']){
                choices = this.loadExternalValues(newProps.attributes['choices']);
            }else{
                choices = this.state.choices;
            }
        }
        this.setState({
            value:newProps.value,
            dirty:false,
            choices:choices
        });
    },

    getInitialState:function(){
        let choices;
        if(this.props.attributes['choices']) {
            choices = this.loadExternalValues(this.props.attributes['choices']);
        }
        return {
            editMode:false,
            dirty:false,
            value:this.props.value,
            choices:choices
        };
    },

    loadExternalValues:function(choices){
        let list_action;
        if(choices instanceof Map){
            return choices;
        }
        let output = new Map();
        if(choices.indexOf('json_list:') === 0){
            list_action = choices.replace('json_list:', '');
            output.set('0', pydio.MessageHash['ajxp_admin.home.6']);
            PydioApi.getClient().request({get_action:list_action}, function(transport){
                const list = transport.responseJSON.LIST;
                let newOutput = new Map();
                if(transport.responseJSON.HAS_GROUPS){
                    for(key in list){
                        if(list.hasOwnProperty(key)){
                            // TODO: HANDLE OPTIONS GROUPS
                            for (let index=0;index<list[key].length;index++){
                                newOutput.set(key+'-'+index, list[key][index].action);
                            }
                        }
                    }
                }else{
                    for (let key in list){
                        if(list.hasOwnProperty(key)){
                            newOutput.set(key, list[key]);
                        }
                    }
                }
                this.setState({choices:newOutput});
            }.bind(this));
        }else if(choices.indexOf('json_file:') === 0){
            list_action = choices.replace('json_file:', '');
            output.set('0', pydio.MessageHash['ajxp_admin.home.6']);
            PydioApi.getClient().loadFile(list_action, function(transport){
                let newOutput = new Map();
                transport.responseJSON.map(function(entry){
                    newOutput.set(entry.key, entry.label);
                });
                this.setState({choices:newOutput});
            }.bind(this));
        }else if(choices == "AJXP_AVAILABLE_LANGUAGES"){
            global.pydio.listLanguagesWithCallback(function(key, label){
                output.set(key, label);
            });
        }else if(choices == "AJXP_AVAILABLE_REPOSITORIES"){
            if(global.pydio.user){
                global.pydio.user.repositories.forEach(function(repository){
                    output.set(repository.getId() , repository.getLabel());
                });
            }
        }else{
            // Parse string and return map
            choices.split(",").map(function(choice){
                let label,value;
                const l = choice.split('|');
                if(l.length > 1){
                    value = l[0];label=l[1];
                }else{
                    value = label = choice;
                }
                if(global.pydio.MessageHash[label]) label = global.pydio.MessageHash[label];
                output.set(value, label);
            });
        }
        return output;
    }

};