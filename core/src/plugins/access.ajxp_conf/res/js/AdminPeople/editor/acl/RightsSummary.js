import {RoleMessagesConsumerMixin} from '../util/MessagesMixin'

export default React.createClass({

    mixins:[RoleMessagesConsumerMixin],

    render: function(){
        var acl;
        switch(this.props.acl){
            case 'rw':
                acl = this.context.getMessage('8');
                break;
            case 'r':
                acl = this.context.getMessage('9');
                break;
            case 'w':
                acl = this.context.getMessage('10');
                break;
            case 'AJXP_VALUE_CLEAR':
                acl = this.context.getMessage('11');
                break;
            default:
                acl = this.context.getMessage('12');
        }
        return (
            <span className={'summary-rights summary'}>
                    {acl}
                </span>
        );
    }

});
