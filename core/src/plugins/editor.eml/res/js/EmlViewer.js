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

const styles = {
     chip: {
         margin: 4,
     },
     container: {
         height: '100%',
         width: '100%',
         flexDirection: 'column',
     },
     headers: {
         maxHeight: 44,
         overflowY: 'scroll',
         paddingLeft: 20,
         display: 'flex',
         flexWrap: 'wrap',
         flexDirection: 'row',
         color: '#757575',
     },
     headerName: {
         width: 50,
         display: 'flex',
         justifyContent: 'flex-end',
         alignItems: 'center',
     },
     headerTitleAttribute: {
         fontSize: '14px',
         fontWeight: 'bold',
     },
     title: {
         display: 'flex',
         justifyContent: 'flex-end',
         marginTop: 20,
         marginBottom: 20,
         marginLeft: 20,
         marginRight: 20,
         subject: {
             flex: 1,
             fontWeight: 'bold',
             fontSize: 18,
             color: '#A9A9A9',
         },
         date: {
             fontWeight: 'light',
             color: '#A9A9A9',
         },
     },
     body: {
         height: '100%',
         flex: 1,
         marginLeft: 20,
         marginRight: 20,
         overflowY: 'scroll',
     },
     popover: {
         zIndex: 100000
     },
     attachments: {
         display: 'flex',
         overflowX: 'scroll',
         width: '100%',
         maxHeight: 150,
         position: 'absolute',
         alignItems: 'center',
         bottom: 0,
         paddingTop: 4,
         paddingBottom: 4,
     },
     attachment: {
         marginLeft: 8,
     },
};

const EmailBody = ({isHtml, body}) => {
    if (isHtml) {
        return <div style={styles.body} dangerouslySetInnerHTML={{__html: body}} />;
    } else {
        return (
            <p>
                {body}
            </p>
        );
    }
}

const ContactChip = ({contact}) => {
    return (
        <MaterialUI.Chip style={styles.chip}>
              <MaterialUI.Avatar size={32}>{contact[0]}</MaterialUI.Avatar>
              {contact}
        </MaterialUI.Chip>
    );
}

class HeaderField extends React.Component {

    constructor(props) {
        super(props);
    }

    render() {
        let {headerName, headerValue} = this.props;
        let emails = null
        if (headerValue) {
            emails = headerValue.split(',').map((email) => (<ContactChip contact={email} />));
        } else {
            return (<div></div>);
        }
        let hName;
        switch (headerName) {
            case 'From': hName = pydio.MessageHash['editor.eml.1']; break;
            case 'To': hName = pydio.MessageHash['editor.eml.2']; break;
            case 'Cc': hName = pydio.MessageHash['editor.eml.12']; break;
            default: hName = 'unexpected header'; break;
        }
        return (
            <div>
                <div style={styles.headers}>
                    <div style={styles.headerName}>
                        <p style={{fontSize: '14px', fontWeight: 'bold', color: '#9f9f9f'}} >{hName}: </p>
                    </div>
                    {emails}
                </div>
                <MaterialUI.Divider />
            </div>
        );
    }
}

class Attachment extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            open: false,
        };
    }

    downloadAttachment() {
        let {pydio, node} = this.props
        this.props.pydio.ApiClient.downloadSelection(null, 'eml_dl_attachment', {file: node.getPath(), attachment_id: this.props.attachment.id})
    }

    copyAttachment() {
        let {pydio, node, attachment} = this.props

        let submit = function(path, wsId = null) {
            pydio.ApiClient.request({
                get_action: 'eml_cp_attachment',
                file: node.getPath(),
                attachment_id: attachment.id,
                destination: path,
            }, function (transport) {
                console.log(transport.responseXML)
            });
        };

        pydio.UI.openComponentInModal('FSActions', 'TreeDialog', {
            isMove: false,
            dialogTitle: MessageHash[159],
            submitValue: submit
        });
    }

    handleTouchTap(event) {
        // This prevents ghost click.
        event.preventDefault();

        this.setState({
            open: true,
            anchorEl: event.currentTarget,
        });
    };

    handleRequestClose() {
        this.setState({
            open: false,
        });
    };

    render() {
        let {pydio, attachment} = this.props

        return (
            <MaterialUI.Paper
                style={styles.attachment}
            >
                <MaterialUI.IconButton iconClassName="mdi mdi-file" disabled={true} />
                {attachment.fileName}
                <MaterialUI.IconButton
                    iconClassName="mdi mdi-dots-vertical"
                    onTouchTap={this.handleTouchTap.bind(this)}
                >
                    <MaterialUI.Popover
                        useLayerForClickAway={false}
                        style={ {zIndex: 100000} }
                        open={this.state.open}
                        anchorEl={this.state.anchorEl}
                        anchorOrigin={{horizontal: 'right', vertical: 'top'}}
                        targetOrigin={{horizontal: 'right', vertical: 'bottom'}}
                        onRequestClose={this.handleRequestClose.bind(this)}
                    >
                        <MaterialUI.Menu
                            style={ {zIndex: 100000} }
                        >
                            <MaterialUI.MenuItem
                                primaryText={pydio.MessageHash['editor.eml.10']}
                                onTouchTap={this.downloadAttachment.bind(this)}
                            />
                            <MaterialUI.MenuItem
                                primaryText={pydio.MessageHash['editor.eml.11']}
                                onTouchTap={this.copyAttachment.bind(this)}
                            />
                        </MaterialUI.Menu>
                    </MaterialUI.Popover>
                </MaterialUI.IconButton>
            </MaterialUI.Paper>
        );
    }
}

class EmlViewer extends React.Component {

    constructor(props) {
        super(props)

        this.state = {headers: [], isHtml: false, body: '', attachments: []}
    }

    componentWillMount() {
        this.loadFileContent();
    }

    loadFileContent() {
        let {pydio, node, onLoad} = this.props;

        pydio.ApiClient.request({
            get_action: 'eml_get_xml_structure',
            file: node.getPath(),
        }, function (transport) {
            this.parseHeaders(transport.responseXML);
            this.parseAttachments(transport.responseXML);
        }.bind(this));

        pydio.ApiClient.request({
            get_action: 'eml_get_bodies',
            file: node.getPath(),
        }, function (transport) {
            this.parseBody(transport.responseXML)

            onLoad()
        }.bind(this));

        // Should be handled with promises
        setTimeout(() => onLoad(), 2000)
    }


    parseBody(xmlDoc) {
        let body = XMLUtils.XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="html"]').firstChild.nodeValue;
        let isHtml = true;
        if (!body) {
            body = XMLUtils.XPathSelectSingleNode(xmlDoc, 'email_body/mimepart[@type="plain"]').firstChild.nodeValue;
            ishtml = false;
        }
        this.setState({body: body});
        this.setState({isHtml: isHtml})
    }

    parseHeaders(xmlDoc) {
        let headers = XMLUtils.XPathSelectNodes(xmlDoc, 'email/header');
        let searchedHeaders = {};

        headers.forEach(function (value) {
            let hName = XMLUtils.XPathGetSingleNodeText(value, 'headername');
            let hValue = XMLUtils.XPathGetSingleNodeText(value, 'headervalue');
            searchedHeaders[hName] = hValue;
        });
        this.setState({headers: searchedHeaders})
    }

    parseAttachments(xmlDoc) {
        let allHeaders = XMLUtils.XPathSelectNodes(xmlDoc, '//header');
        // let attachments = {};
        let attachments = []
        let id = 0;
        allHeaders.forEach((el) => {
            let hName = XMLUtils.XPathGetSingleNodeText(el, 'headername');
            let hValue = XMLUtils.XPathGetSingleNodeText(el, 'headervalue');
            if (hName != 'Content-Disposition' || hValue != 'attachment') return;
            let mimepart = el.parentNode;
            let filename = '';

            let params = XMLUtils.XPathSelectNodes(el, 'parameter');
            params.forEach((c) => {
                if(XMLUtils.XPathGetSingleNodeText(c, "paramname") == "filename") {
                    filename = XMLUtils.XPathGetSingleNodeText(c, "paramvalue");
                }
            });

            let foundId = false;
            allHeaders.forEach((h) => {
                if (h.parentNode != mimepart) return;
                let siblingName = XMLUtils.XPathGetSingleNodeText(h, "headername");
                if (siblingName == "X-Attachment-Id") {
                    id = XMLUtils.XPathGetSingleNodeText(h, "headervalue");
                    foundId = true;
                }
            });
            // attachments[id] = filename;
            attachments.push({id: id, fileName: filename})
            if(!foundId){
                id = id+1;
            }
        });
        this.setState({attachments: attachments})
    }

    render() {
        return (
            <MaterialUI.Paper zDepth={1} style={styles.container} >
                {["From", "To", "Cc"].map((id) => {
                    return <HeaderField {...this.props} key={id} headerName={id} headerValue={this.state.headers[id]} />
                })}
                <div style={styles.title}>
                    <div style={styles.title.subject}>
                        <p style={{fontSize: 18, color: '#9f9f9f'}}>{this.state.headers['Subject']}</p>
                    </div>
                    <div style={styles.title.date}>
                        <p style={{fontSize: 14, color: '#9f9f9f', opacity: 0.8}} >{this.state.headers['Date']}</p>
                    </div>
                </div>
                <EmailBody isHtml={this.state.isHtml} body={this.state.body} />
                <div style={styles.attachments}>
                    {Object.values(this.state.attachments).map((a) => <Attachment {...this.props} attachment={a} />)}
                </div>
            </MaterialUI.Paper>
        );
    }
}

window.EmlViewer = EmlViewer;
