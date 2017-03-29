/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

 class CustomIframe extends React.Component {

    onUnload(e) {
        let href = this.myIframe.contentDocument.location.href;

        if(href && href.indexOf('image=') > -1){
            this.save(href);
        }else if(href && (href.indexOf('close_pixlr')>-1 || href.indexOf('error_pixlr')>-1)){
            // TODO: Close the editor
        }
    }

    save(pixlrUrl) {
        let {pydio, node} = this.props;

        pydio.ApiClient.request({
            get_action: 'retrieve_pixlr_image',
            original_file: node.getPath(),
            new_url: pixlrUrl,
        }, function (transport) {
            console.log(transport.responseXML)
            node.getParent().getMetadata().set('preview_seed', Math.round(Math.random() * new Date().getTime()));
            pydio.fireNodeRefresh(node);
            // TODO: Close the editor
        });
    }

    componentDidMount() {
        this.myIframe.contentWindow.addEventListener("onbeforeunload", this.onUnload)
    }

    componentWillUnmount() {
        this.myIframe.contentWindow.removeEventListener("onbeforeunload", this.onUnload)
    }

    render() {
        return (
            <iframe
                ref={(element) => {this.myIframe = element}}
                src={this.props.url}
                style={{...this.props.style, border: 0, flex: 1}}
                className="vertical_fit"
                onLoad={this.onUnload.bind(this)}
            >
            </iframe>
        );
    }
}


class PixlrEditor extends React.Component {

    constructor(props) {
        super(props);

        this.state = {}
    }

    componentWillMount() {
        let {pydio, node} = this.props;

        this.setState({url: pydio.Parameters.get('ajxpServerAccess')+"&get_action=post_to_server&file=base64encoded:" + HasherUtils.base64_encode(node.getPath()) + "&parent_url=" + HasherUtils.base64_encode(DOMUtils.getUrlFromBase())});
    }

    render() {
        return (
            <PydioComponents.AbstractEditor {...this.props}>
                <CustomIframe
                    {...this.props}
                    url={this.state.url}
                />
            </PydioComponents.AbstractEditor>
        );
    }

}

window.PixlrEditor = PixlrEditor;
