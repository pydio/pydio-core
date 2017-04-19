import ActionDialogMixin from './ActionDialogMixin'

/**
 * Sample Dialog class used for reference only, ready to be
 * copy/pasted :-)
 */
export default React.createClass({

    mixins:[
        ActionDialogMixin
    ],

    getInitialState: function(){
        return {...this.props.activityState};
    },

    componentDidMount: function(){
        this._observer = (activityState) => {
            this.setState(activityState);
        };
        this.props.pydio.observe('activity_state_change', this._observer);
    },

    componentWillUnmount: function(){
        this.props.pydio.stopObserving('activity_state_change', this._observer);
    },

    getDefaultProps: function(){
        return {
            dialogTitleId: '375t',
            dialogIsModal: false
        };
    },
    render: function(){
        const {MessageHash} = this.props.pydio;
        const {lastActiveSince, timerString} = this.state;
        const sentence = MessageHash['375'].replace('__IDLE__', lastActiveSince).replace('__LOGOUT__', timerString);
        return (
            <div onTouchTap={() => {this.props.pydio.notify('user_activity');}}>
                <div style={{display:'flex', alignItems:'center'}}>
                    <div className="mdi mdi-security" style={{fontSize:70,paddingRight:10}}/>
                    <p>{sentence}</p>
                </div>
                <p style={{textAlign:'right', textAlign: 'right', fontWeight: 500, color: '#607D8B', fontSize: 14}}>{MessageHash['376']}</p>
            </div>
        );
    }

});

