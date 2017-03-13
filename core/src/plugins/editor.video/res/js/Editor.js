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

import Media from './Media';

class Editor extends React.Component {

    constructor(props) {

        super(props)

        const {url} = this.props

        this.state = {
            url: url
        }

        this.onReady = this._handleReady.bind(this)
    }

    _handleReady() {
        typeof this.props.onReady ==='function' && this.props.onReady()
    }

    render() {
        let options = {
            preload: 'auto',
            autoplay: false,
            controls: true,
            flash: {
                swf: "plugins/editor.video/node_modules/video.js/dist/video-js.swf"
            },
            techOrder: ['flash', 'html5'] // TODO - switch the order when the file is MP4 ??
        }

        return (
            <div style={{position: "relative", padding: 0, margin: 0}}>
                <Media options={options} src={this.state.url} resize={true} onReady={this.onReady}></Media>
            </div>
        )
    }
}

Editor.propTypes = {
    url: React.PropTypes.string.isRequired,

    onReady: React.PropTypes.func
}

export default Editor;
