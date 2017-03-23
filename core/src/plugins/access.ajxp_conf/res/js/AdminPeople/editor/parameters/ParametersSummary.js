import ParamsMixins from './ParamsMixins'
import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'

export default React.createClass({

    mixins:[ParamsMixins, RoleMessagesConsumerMixin],

    render: function(){
        var render = function(pluginName, paramName, paramValue, paramAttributes, inherited, type){
            if(type == 'action'){
                if(paramAttributes['label'] && pydio.MessageHash[paramAttributes['label']]){
                    return pydio.MessageHash[paramAttributes['label']];
                }else{
                    return paramName;
                }
            }else{
                let displayValue = (paramValue === '__AJXP_VALUE_SET__' ? '***********' : paramValue);
                return (paramAttributes['label'] + ' ' + displayValue);
            }
        };
        var parameters = this.browseParams(
            this.props.role.PARAMETERS,
            this.props.roleParent.PARAMETERS,
            this.props.id,
            render,
            this.props.pluginsFilter,
            'parameter',
            false,
            true
        );
        var actions = this.browseParams(
            this.props.role.ACTIONS,
            this.props.roleParent.ACTIONS,
            this.props.id,
            render,
            this.props.pluginsFilter,
            'action',
            false,
            true
        );
        var strings = [];
        parameters = parameters[0].concat(parameters[1]);
        actions = actions[0].concat(actions[1]);
        if(parameters.length) {
            strings.push(this.context.getAjxpRoleMessage('6') + ': ' + parameters.join(','))
        }
        if(actions.length) {
            strings.push( this.context.getAjxpRoleMessage('46') + ': ' + actions.join(','));
        }
        return(
            <span className={'summary-parameters summary' + (strings.length?'':'-empty')}>
                    {strings.length?strings.join(' - '):this.context.getMessage('1')}
                </span>
        );
    }
});
