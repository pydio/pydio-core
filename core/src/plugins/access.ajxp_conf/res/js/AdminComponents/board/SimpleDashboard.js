const React = require('react')
const {Paper} = require('material-ui')
import {MessagesConsumerMixin} from '../util/Mixins'

export default React.createClass({

    mixins: [MessagesConsumerMixin],

    render: function(){

        const paperStyle = {height: 400, flex:1, marginLeft:10, marginTop: 10};

        return (
            <div style={{height:'100%', overflow: 'auto'}}>
                <div style={{display:'flex', alignItems:'top'}}>
                    <Paper zDepth={1} style={paperStyle}>
                        Welcome on Pydio Community Dashboard.
                    </Paper>
                    <Paper zDepth={1} style={{...paperStyle, marginRight: 10}}>
                        Learn more about Pydio.
                    </Paper>
                </div>
                <div style={{display:'flex', alignItems:'top'}}>
                    <Paper zDepth={1} style={paperStyle}>
                        Contributing to Pydio
                    </Paper>
                    <Paper zDepth={1} style={{...paperStyle, marginRight: 10}}>
                        Learn more about Pydio Enterprise Distribution
                    </Paper>
                </div>
            </div>
        )
    }

});