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

import React from 'react';
import { connect } from 'react-redux';
import { compose } from 'redux';

import CKEditor from './CKEditor';

const {EditorActions} = PydioWorkspaces;

class Editor extends React.Component {
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
                ...Editor.base,
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
                ...Editor.base,
				toolbar_Ajxp : [
				    ['Bold','Italic','Underline', '-', 'NumberedList','BulletedList'],
				    ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock']
				]
            }
        }
    }

    constructor(props) {
        super(props)

        const {pydio, node, id, dispatch} = this.props

        if (typeof dispatch === 'function') {
            // We have a redux dispatch so we use it
            this.setState = (data) => dispatch(EditorActions.tabModify({id, ...data}))
        }
    }

    // Static functions
    static getPreviewComponent(node, rich = false) {
        if (rich) {
            return {
                element: PydioCKEditor.Editor,
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
        const {pydio, url} = this.props

        pydio.ApiClient.request({
            get_action: 'get_content',
            file: url
        }, ({responseText}) => this.setState({content: responseText}))
    }

    render() {
        const {url, content} = this.props
        const {desktop, mobile} = Editor.config

        if (!content) return null

        return (
            <CKEditor
                url={this.props.url}
                content={this.props.content}
                config={this.props.pydio.UI.MOBILE_EXTENSIONS ? mobile : desktop}
                onChange={content => this.setState({content})}
            />
        );
    }
}

CKEDITOR.basePath = Editor.config.basePath
CKEDITOR.contentsCss = Editor.config.basePath + '../../res/css/ckeditor.css'

/*const {withMenu, withLoader, withErrors, withControls} = PydioHOCs;

//let CompositeEditor = compose(withControls(PydioCKEditor.controls), withMenu, withLoader, withErrors)(CKEditor)

// We need to attach the element to window else it won't be found
window.PydioCKEditor = {
    Editor: PydioCKEditor,
    Actions: {
        onUndo: () => console.log("Whatever dude")
    }
}*/

export default connect()(Editor)
