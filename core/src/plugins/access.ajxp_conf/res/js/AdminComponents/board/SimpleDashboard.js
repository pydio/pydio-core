import {MessagesConsumerMixin} from '../util/Mixins'

export default React.createClass({

    mixins: [MessagesConsumerMixin],

    render: function(){
        return <div>Simple Dashboard {this.props.simpleMessage}</div>
    }

});