
import React from 'react'
import { soundManager } from 'soundmanager2';
import { threeSixtyPlayer } from '../../../sm/360-player/script/360player';

soundManager.setup({
    // path to directory containing SM2 SWF
    url: 'plugins/editor.soundmanager/sm/swf/'
});

class Player extends React.Component {
    constructor(props) {
        super(props)

        threeSixtyPlayer.config.autoPlay = props.autoPlay

        threeSixtyPlayer.config.scaleFont = (navigator.userAgent.match(/msie/i)?false:true);
        threeSixtyPlayer.config.showHMSTime = true;

        // enable some spectrum stuffs
        threeSixtyPlayer.config.useWaveformData = true;
        threeSixtyPlayer.config.useEQData = true;

        // enable this in SM2 as well, as needed
        if (threeSixtyPlayer.config.useWaveformData) {
          soundManager.flash9Options.useWaveformData = true;
        }
        if (threeSixtyPlayer.config.useEQData) {
          soundManager.flash9Options.useEQData = true;
        }
        if (threeSixtyPlayer.config.usePeakData) {
          soundManager.flash9Options.usePeakData = true;
        }

        if (threeSixtyPlayer.config.useWaveformData || threeSixtyPlayer.flash9Options.useEQData || threeSixtyPlayer.flash9Options.usePeakData) {
            // even if HTML5 supports MP3, prefer flash so the visualization features can be used.
            soundManager.preferFlash = true;
        }

        // favicon is expensive CPU-wise, but can be used.
        if (window.location.href.match(/hifi/i)) {
          threeSixtyPlayer.config.useFavIcon = true;
        }

        if (window.location.href.match(/html5/i)) {
          // for testing IE 9, etc.
          soundManager.useHTML5Audio = true;
        }
    }

    componentWillMount() {

        //soundManager.createSound()
    }


    componentDidMount() {
        //soundManager.onready(() => React.Children.map(this.props.children, (child) => soundManager.createSound({url: child.href})))
        soundManager.onready(threeSixtyPlayer.init)

        // soundManager.onready(nextProps.onReady)
        // soundManager.beginDelayedInit()
    }

    componentWillReceiveProps(nextProps) {
        //soundManager.onready(() => React.Children.map(nextProps.children, (child) => soundManager.createSound({url: child.href})))
        soundManager.onready(threeSixtyPlayer.init)
    }

    /*componentWillUnmount() {
        soundManager.reboot()
    }*/

    render() {
        let className="ui360"
        if (this.props.rich) {
            className += " ui360-vis"
        }

        return (
            <div className="ui360 ui360-vis" style={this.props.style}>
                {this.props.children}
            </div>
        )
    }
}

Player.propTypes = {
    autoPlay: React.PropTypes.bool,
    rich: React.PropTypes.bool.isRequired,
    onReady: React.PropTypes.func
}

Player.defaultProps = {
    autoPlay: false,
    rich: true
}

export default Player
