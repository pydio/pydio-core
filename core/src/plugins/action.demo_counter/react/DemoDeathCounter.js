(function(global){

    let Panel = React.createClass({

        tick: function(){

            // Compute next occurence of 0 or 30
            let d = new Date();
            let remainingMinutes = 30 - d.getMinutes() % 30;
            let remainingSeconds = 60 - d.getSeconds();
            this.setState({timeLeft: remainingMinutes + '\'' + (remainingSeconds < 10 ? '0' + remainingSeconds : remainingSeconds) + 'mn' });

        },

        getInitialState: function(){
            return {timeLeft: ''};
        },

        componentDidMount: function(){
            this._ticker = global.setInterval(this.tick.bind(this), 1);
        },

        componentWillUnmount: function(){
            global.clearInterval(this._ticker);
        },

        render: function(){
            let style = {
                position: 'absolute',
                zIndex: 10000,
                backgroundColor: 'rgba(255,255,255,0.33)',
                fontSize: 16,
                top: 0,
                left: '35%',
                width: '30%',
                padding: '8px 10px',
                borderRadius: '0 0 2px 2px',
                textAlign: 'center',
                color: '#ffffff',
            };
            return <div style={style}><span className="icon-warning-sign"/> This demo will reset itself in {this.state.timeLeft}</div>;
        }

    });

    let ns = global.DemoDeathCounter || {};
    ns.Panel = Panel;
    global.DemoDeathCounter = ns;

})(window);