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

import React, {Component} from 'react';
import {IconButton, TextField} from 'material-ui';
import {compose} from 'redux';

import CKEditor from './CKEditor';

class PydioCKEditor extends Component {
    static get propTypes() {
        return {
            showControls: React.PropTypes.bool.isRequired
        }
    }

    static get defaultProps() {
        return {
            showControls: false
        }
    }

    static get propTypes() {
        return {
            showControls: React.PropTypes.bool.isRequired
        }
    }

    static get defaultProps() {
        return {
            showControls: false
        }
    }

    static get controls() {
        return {
            options: {
                save: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-content-save" tooltip={MessageHash[53]}/>,
                undo: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-undo" tooltip={MessageHash["code_mirror.7"]} />,
                redo: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-redo" tooltip={MessageHash["code_mirror.8"]} />,
                toggleLineNumbers: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-format-list-numbers" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.5"]} />,
                toggleLineWrapping: (handler) => <IconButton onClick={handler} iconClassName="mdi mdi-wrap" tooltipPosition="bottom-right" tooltip={MessageHash["code_mirror.3b"]} />
            },
            actions: {
                jumpTo: (handler) => <TextField hintText={MessageHash["code_mirror.6"]} onKeyUp={handler} />,
                find: (handler) => <TextField hintText={MessageHash["code_mirror.9"]} onKeyUp={handler} />
            }
        }
    }

    static get base() {
        return {
            resize_enabled:false,
            toolbar : "Ajxp",
            contentsCss: '../../res/ckeditor.css',
            filebrowserBrowseUrl : 'index.php?external_selector_type=ckeditor',
            // IF YOU KNOW THE RELATIVE PATH OF THE IMAGES (BETWEEN REPOSITORY ROOT AND REAL FILE)
            // YOU CAN PASS IT WITH THE relative_path PARAMETER. FOR EXAMPLE :
            //filebrowserBrowseUrl : 'index.php?external_selector_type=ckeditor&relative_path=files',
            filebrowserImageBrowseUrl : 'index.php?external_selector_type=ckeditor',
            filebrowserFlashBrowseUrl : 'index.php?external_selector_type=ckeditor',
            language : ajaxplorer.currentLanguage,
            fullPage : true,
        }
    }

    static get config() {
        return {
            basePath: `${DOMUtils.getUrlFromBase()}plugins/editor.ckeditor/node_modules/ckeditor/`,
            desktop : {
                ...PydioCKEditor.base,
    			toolbar_Ajxp : [
    				['Source','Preview','Templates'],
    			    ['Undo','Redo','-', 'Cut','Copy','Paste','PasteText','PasteFromWord','-','Print', 'SpellChecker', 'Scayt'],
    			    ['Find','Replace','-','SelectAll','RemoveFormat'],
    			    ['Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'],
    			    '/',
    			    ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
    			    ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote'],
    			    ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
    			    ['Link','Unlink','Anchor'],
    			    ['Image','Flash','Table','HorizontalRule','Smiley','SpecialChar','PageBreak'],
    			    '/',
    			    ['Styles','Format','Font','FontSize'],
    			    ['TextColor','BGColor'],
    			    ['Maximize', 'ShowBlocks','-','About']
    			]
            },
            mobile: {
                ...PydioCKEditor.base,
				toolbar_Ajxp : [
				    ['Bold','Italic','Underline', '-', 'NumberedList','BulletedList'],
				    ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock']
				]
            }
        }
    }

    constructor(props) {
        super(props)

        const {pydio, node} = this.props

        this.state = {
            url: node.getPath()
        }
    }

    // Static functions
    static getPreviewComponent(node, rich = false) {
        if (rich) {
            return {
                element: PydioCKEditor,
                props: {
                    node: node,
                    rich: rich
                }
            }
        } else {

            // We don't have a player for the file icon
            return null;
        }
    }

    componentDidMount() {
        const {pydio} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: this.state.url
        }, (transport) => this.setState({content: transport.responseText}));
    }

    onSave() {
        const {pydio} = this.props;

        pydio.ApiClient.postPlainTextContent(this.state.url, this.state.content, (success) => {
            if (!success) {
                this.setState({error: "There was an error while saving"})
            }
        });
    }

    hasBeenModified() {
        return (this.state.originalText != this.state.content)
    }

    handleChange(event) {
      this.setState({textContent: event.target.value});
    }

    buildActions() {
        let actions = [];
        let mess = this.props.pydio.MessageHash;
        actions.push(
            <MaterialUI.ToolbarGroup
                firstChild={true}
                key="left"
            >

            </MaterialUI.ToolbarGroup>
        );
        return actions;
    }

    render() {
        const {desktop, mobile} = PydioCKEditor.config

        return (
            <CompositeEditor
                {...this.props}

                saveDisabled={!this.hasBeenModified()}
                onSave={() => this.onSave()}

                onChange={content => this.setState({content})}
                actions={this.buildActions()}
                url={this.state.url}
                config={window.ajxpMobile ? mobile : desktop}
            />
        );
    }
}

const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

let CompositeEditor = compose(withControls(PydioCKEditor.controls), withMenu, withLoader, withErrors)(CKEditor)

CKEDITOR.basePath = PydioCKEditor.config.basePath
CKEDITOR.contentsCss = PydioCKEditor.config.basePath + '../../res/css/ckeditor.css'
// We need to attach the element to window else it won't be found
window.PydioCKEditor = {
    Editor: PydioCKEditor,
    Actions: {
        onUndo: () => console.log("Whatever dude")
    }
}

export default PydioCKEditor
