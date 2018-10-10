/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */


import React from 'react'
import SoundObserver from './observer';
import { soundManager } from 'soundmanager2';
// import { threeSixtyPlayer } from '../../../sm/360-player/script/360player';

const self = this,
    pl = this,
    sm = soundManager, // soundManager instance
    uA = navigator.userAgent,
    isIE = (uA.match(/msie/i)),
    isOpera = (uA.match(/opera/i)),
    isSafari = (uA.match(/safari/i)),
    isChrome = (uA.match(/chrome/i)),
    isFirefox = (uA.match(/firefox/i)),
    isTouchDevice = (uA.match(/ipad|iphone/i)),
    hasRealCanvas = (typeof window.G_vmlCanvasManager === 'undefined' && typeof document.createElement('canvas').getContext('2d') !== 'undefined'),
    // I dunno what Opera doesn't like about this. I'm probably doing it wrong.
    fullCircle = (isOpera||isChrome?359.9:360);

const STATUS_DEFAULT = "STATUS_DEFAULT"
const STATUS_BUFFERING = "STATUS_BUFFERING"
const STATUS_PLAYING = "STATUS_PLAYING"
const STATUS_PAUSED = "STATUS_PAUSED"

const css = {
  STATUS_DEFAULT: 'sm2_link',
  STATUS_BUFFERING: 'sm2_buffering',
  STATUS_PLAYING: 'sm2_playing',
  STATUS_PAUSED: 'sm2_paused'
};

const soundObserver = new SoundObserver()

soundManager.setup({
    // path to directory containing SM2 SWF
    url: 'plugins/editor.soundmanager/sm/swf/',
});

class Player extends React.Component {
    constructor(props) {
        super(props)

        this.state = {
            status: STATUS_DEFAULT
        }
    }

    componentDidMount() {
        const {id, url, style, rich, autoPlay, onFinish} = this.props

        soundManager.onready(() => {
            const sound = soundManager.getSoundById(id)

            // First we check if it already exists
            if (sound) {
                return
            }

            soundManager.createSound({
                id: id,
                url: url,
                multiShot: false,
                onplay: () => soundObserver.play(id),
                onstop: () => soundObserver.stop(id),
                onpause: () => soundObserver.pause(id),
                onresume: () => soundObserver.resume(id),
                onfinish: () => soundObserver.finish(id),
                onbufferchange: () => soundObserver.bufferchange(id),
                whileloading: () => soundObserver.whileloading(id),
                whileplaying: () => soundObserver.whileplaying(id)
            })

            // Adding autoplay, listening to previous sound finish events
            const previousSoundID = soundManager.soundIDs.indexOf(id) - 1
            if (previousSoundID > -1) {
                soundObserver.observe("soundfinish" + soundManager.soundIDs[previousSoundID], () => {
                    soundManager.getSoundById(id).play()
                })
            }
        })

        const oCanvasCTX = this.canvas.getContext('2d');

        oCanvasCTX.translate(parseInt(style.width / 2), parseInt(style.height / 2));
        oCanvasCTX.rotate(deg2rad(-90))

        soundObserver.observe("soundplay" + id, () => this.setState({status: STATUS_PLAYING}))
        soundObserver.observe("soundpause" + id, () => this.setState({status: STATUS_PAUSED}))
        soundObserver.observe("soundbuffering" + id, () => this.setState({status: STATUS_BUFFERING}))
        soundObserver.observe("soundresume" + id, () => this.setState({status: STATUS_PLAYING}))
        soundObserver.observe("soundstop" + id, () => {
            clearCanvas(this.canvas)
            this.setState({status: STATUS_DEFAULT})
        })
        soundObserver.observe("soundfinish" + id, () => {
            clearCanvas(this.canvas)
            this.setState({status: STATUS_DEFAULT})

            if (typeof onFinish === "function") {
                onFinish()
            }
        })

        soundObserver.observe("soundwhileplaying" + id, () => {
            const sound = soundManager.getSoundById(id)
            const radius = rich ? 60: 20
            const width = rich ? 20 : 5

            const durationEstimate = sound.durationEstimate > 0 ? sound.durationEstimate : sound.buffered[0].end

            if (this.canvas) {
                // Background
                drawSolidArc(this.canvas, '#dddddd', radius, width, deg2rad(fullCircle), 0, false);
                // Loading ring
                drawSolidArc(this.canvas, '#cccccc', radius, width, deg2rad(fullCircle*(sound.bytesLoaded/sound.bytesTotal)), 0, true);
                // Playing ring
                drawSolidArc(this.canvas, '#000000', radius, width, deg2rad(fullCircle*(sound.position/durationEstimate)), 0, true);
            }


            this.setState({
                time: getTime(sound.position,true),
            })
        })

        // Making sure the status is correct if sound is already playing
        const sound = soundManager.getSoundById(id)
        if (sound && sound.playState == 1) {
            if (sound.paused) {
                this.setState({
                    status: STATUS_PAUSED
                })
            } else {
                this.setState({
                    status: STATUS_PLAYING
                })
            }
        }
    }

    _handleEvent(e) {
        const {id, rich} = this.props

        const offset = rich ? 20 : 5
        const sound = soundManager.getSoundById(id)
        const coords = getMouseXY(e)
        const x = coords[0]
        const y = coords[1]

        const rect = this.canvas.getBoundingClientRect();

        const canvasMidX = rect.left + parseInt(this.canvas.width / 2)
        const canvasMidY = rect.top + parseInt(this.canvas.height / 2)

        const deltaX = x-canvasMidX
        const deltaY = y-canvasMidY

        if (Math.abs(deltaX) < offset && Math.abs(deltaY) < offset) {
            if (e.type == "mouseup") {
                soundManager.soundIDs.map((soundID) => soundID !== id && soundManager.sounds[soundID].stop())
                sound.togglePause()
            }
            return
        }

        const angle = Math.floor(fullCircle-(rad2deg(Math.atan2(deltaX,deltaY))+180));

        const durationEstimate = sound.durationEstimate > 0 ? sound.durationEstimate : sound.buffered[0].end
        const position = Math.floor(durationEstimate*(angle/fullCircle) / 1000)

        sound.setPosition(position * 1000);
        sound.resume()
    }

    handleMouseUp(e) {
        e.preventDefault()
        e.stopPropagation()

        this.dragging = false

        this._handleEvent(e)

        return false
    }

    handleMouseMove(e) {
        e.preventDefault()
        e.stopPropagation()

        if (this.dragging) {
            this._handleEvent(e)
        }

        return false
    }

    handleMouseDown(e) {
        e.preventDefault()
        e.stopPropagation()

        this.dragging = true

        this._handleEvent(e)

        return false
    }

    render() {
        const {status, time} = this.state
        let className="ui360"
        if (this.props.rich) {
            className += " ui360-vis"
        }

        const style = {
            ...this.props.style,
            "backgroundImage": "none",
        }

        const canvasStyle = {
            "width": this.props.style.width,
            "height": this.props.style.height,
            "backgroundColor": "transparent",
            "borderRadius": 0,
            "boxShadow": "none"
        }

        const childStyle = {
            "width": this.props.style.width,
            "height": this.props.style.height,
            "backgroundColor": "transparent",
            "border": 0,
            "borderRadius": 0,
            "boxShadow": "none"
        }

        const btnStyle = {
            "width": this.props.style.width,
            "height": this.props.style.height,
            "lineHeight": this.props.style.width + "px",
            "margin": 0,
            "top": 0,
            "left": 0,
            "bottom": 0,
            "right": 0
        }

        const timingStyle = {
            "width": this.props.style.width,
            "height": this.props.style.height,
            "lineHeight": this.props.style.height + "px"
        }

        const coverStyle = {
            "width": this.props.style.width,
            "height": this.props.style.height,
            "lineHeight": this.props.style.height + "px"
        }

        //

        return (
            <div className="sm2-inline-list" style={style}>
                <div className={className} style={style} onMouseMove={(e) => this.handleMouseMove(e)} onMouseUp={(e) => this.handleMouseUp(e)} onMouseDown={(e) => this.handleMouseDown(e)}>
                    <div className={"sm2-360ui " + css[status]} style={childStyle}>
                        <canvas ref={(el) => this.canvas = el} className="sm2-canvas" style={canvasStyle} width={this.props.style.width} height={this.props.style.height}></canvas>
                        <span className="sm2-360btn sm2-360btn-default" style={btnStyle}></span>
                        <div className="sm2-timing" style={timingStyle}>{time}</div>
                        <div className="sm2-cover" style={coverStyle}></div>
                    </div>
                </div>
            </div>
        )
    }
}

Player.propTypes = {
    // threeSixtyPlayer: React.PropTypes.object,
    autoPlay: React.PropTypes.bool,
    rich: React.PropTypes.bool.isRequired,
    onReady: React.PropTypes.func
}

Player.defaultProps = {
    autoPlay: false,
    rich: true
}

const deg2rad = function(nDeg) {
  return (nDeg * Math.PI/180);
};

const rad2deg = function(nRad) {
  return (nRad * 180/Math.PI);
};

const getTime = function(nMSec,bAsString) {

  // convert milliseconds to mm:ss, return as object literal or string
  var nSec = Math.floor(nMSec/1000),
      min = Math.floor(nSec/60),
      sec = nSec-(min*60);
  // if (min === 0 && sec === 0) return null; // return 0:00 as null
  return (bAsString?(min+':'+(sec<10?'0'+sec:sec)):{'min':min,'sec':sec});

};

const getArcEndpointCoords = function(radius, radians) {

  return {
    x: radius * Math.cos(radians),
    y: radius * Math.sin(radians)
  };

};

const clearCanvas = function(oCanvas) {

  var canvas = oCanvas,
      ctx = null,
      width, height;
  if (canvas && canvas.getContext){
    // use getContext to use the canvas for drawing
    ctx = canvas.getContext('2d');
  }
  width = canvas.offsetWidth;
  height = canvas.offsetHeight;
  ctx.clearRect(-(width/2), -(height/2), width, height);

};

const drawSolidArc = function(oCanvas, color, radius, width, radians, startAngle, noClear) {

  var x = radius,
      y = radius,
      canvas = oCanvas,
      ctx, innerRadius, doesntLikeZero, endPoint;

  if (canvas && canvas.getContext){
    // use getContext to use the canvas for drawing
    ctx = canvas.getContext('2d');
  }

  // re-assign canvas as the actual context
  oCanvas = ctx;

  if (!noClear) {
    clearCanvas(canvas);
  }
  // ctx.restore();

  if (color) {
    ctx.fillStyle = color;
  }

  oCanvas.beginPath();

  if (isNaN(radians)) {
    radians = 0;
  }

  innerRadius = radius-width;
  doesntLikeZero = (isOpera || isSafari); // safari 4 doesn't actually seem to mind.

  if (!doesntLikeZero || (doesntLikeZero && radius > 0)) {
    oCanvas.arc(0, 0, radius, startAngle, radians, false);
    endPoint = getArcEndpointCoords(innerRadius, radians);
    oCanvas.lineTo(endPoint.x, endPoint.y);
    oCanvas.arc(0, 0, innerRadius, radians, startAngle, true);
    oCanvas.closePath();
    oCanvas.fill();
  }

};

const getMouseXY = function(e) {

  // http://www.quirksmode.org/js/events_properties.html
  e = e?e:window.event;
  if (isTouchDevice && e.touches) {
    e = e.touches[0];
  }
  if (e.pageX || e.pageY) {
    return [e.pageX,e.pageY];
  } else if (e.clientX || e.clientY) {
    return [e.clientX+getScrollLeft(),e.clientY+getScrollTop()];
  }

};

const getScrollLeft = function() {
  return (document.body.scrollLeft+document.documentElement.scrollLeft);
};

const getScrollTop = function() {
  return (document.body.scrollTop+document.documentElement.scrollTop);
};

export default Player
