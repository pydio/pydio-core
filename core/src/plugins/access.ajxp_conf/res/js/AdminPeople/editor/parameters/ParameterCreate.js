import EditorCache from '../util/EditorCache'
import ParametersPicker from './ParametersPicker'

export default React.createClass({

    propTypes:{
        workspaceScope:React.PropTypes.string,
        showModal:React.PropTypes.func,
        hideModal:React.PropTypes.func,
        pluginsFilter:React.PropTypes.func,
        roleType:React.PropTypes.oneOf(['user', 'group', 'role']),
        createParameter:React.PropTypes.func
    },

    componentDidMount: function(){
        global.setTimeout(function(){
            this.refs.modal.show();
        }.bind(this), 10);
    },

    getInitialState: function(){
        return {
            step:1,
            workspaceScope:this.props.workspaceScope,
            pluginName:null,
            paramName:null
        };
    },

    setSelection:function(plugin, type, param, attributes){
        this.setState({pluginName:plugin, type:type, paramName:param, attributes:attributes}, this.createParameter);
    },

    hideModal:function(){
        this.refs.modal.dismiss();
        global.setTimeout(function(){
            this.props.hideModal();
        }.bind(this), 500);
    },

    createParameter:function(){
        this.props.createParameter(this.state.type, this.state.pluginName, this.state.paramName, this.state.attributes);
        this.hideModal();
    },

    setModal:function(modalRef){
        this.modal = modalRef;
    },

    render: function(){

        // This is passed via state, context is not working,
        // so we have to get the messages from the global.
        var getMessage = function (id, namespace='pydio_role') {
            return global.pydio.MessageHash[namespace + (namespace ? '.' : '') + id] || id;
        };

        var title, content, actions;
        var params = EditorCache.CACHE['PARAMETERS'];
        if(!params){
            return (<div>Oups: parameters cache is not loaded!</div>);
        }
        var scopeId = this.props.workspaceScope;
        var pluginsFilter = this.props.pluginsFilter || function(){return true;};

        var allParams = {};
        var currentRoleType = this.props.roleType;
        params.forEach(function(data, pluginName){
            if(data.size && pluginsFilter(scopeId, pluginName)){
                var pluginParams = [];
                data.forEach(function(aParam){
                    aParam._type = 'parameter';
                    if(aParam.scope && aParam.scope.indexOf(currentRoleType) !== -1){
                        //console.log('ignoring ' + aParam.label + '? Scope is ' + aParam.scope);
                        return;
                    }
                    pluginParams.push(aParam);
                });
                if(pluginParams.length){
                    allParams[pluginName] = {name:pluginName, params:pluginParams};
                }
            }
        });

        var theActions = EditorCache.CACHE['ACTIONS'];
        var allActions = {};
        theActions.forEach(function(value, pluginName){
            if(value.size && pluginsFilter(scopeId, pluginName)){
                var pluginActions = [];
                value.forEach(function(actionObject, actionName){
                    pluginActions.push({_type:'action', name:actionName,label:actionObject.label?actionObject.label:actionName});
                });
                allActions[pluginName] = {name:pluginName, actions:pluginActions};
            }
        });


        title = (
            <div className="color-dialog-title">
                <h3>{getMessage('14')}</h3>
                <div className="legend">{getMessage('15')}</div>
            </div>
        );
        content = (
            <div className="picker-list">
                <ParametersPicker
                    allActions={allActions}
                    allParameters={allParams}
                    onSelection={this.setSelection}
                    getMessage={getMessage}
                />
            </div>
        );

        var button = <ReactMUI.FlatButton key="can" label={getMessage('54', '')} onClick={this.hideModal}/>;

        return(
            <ReactMUI.Dialog
                ref="modal"
                modal={true}
                title={title}
                actions={[button]}
                dismissOnClickAway={false}
                className="param-picker-dialog"
                contentClassName={this.state.className}
                openImmediately={false}
            >{content}</ReactMUI.Dialog>
        );
    }

});

