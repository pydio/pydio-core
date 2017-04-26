/**
 * Copyright (c) 2013-present, Facebook, Inc. All rights reserved.
 *
 * This file provided by Facebook is for non-commercial testing and evaluation
 * purposes only. Facebook reserves all rights not expressly granted.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * FACEBOOK BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

import { Toolbar, ToolbarGroup, Card, CardHeader, CardMedia } from 'material-ui';

import { connect } from 'react-redux';
import { compose, bindActionCreators } from 'redux';
import * as actions from '../../actions';

import makeMaximise from './make-maximise';
const {ResolutionControls, ContentActions, ContentControls, SizeControls, SelectionControls, withMenu} = PydioHOCs;

class Tab extends React.Component {
    static get styles() {
        return {
            container: {
                display: "flex",
                flex: 1,
                flexFlow: "column nowrap",
                overflow: "auto"
            },
            child: {
                display: "flex",
                flex: 1
            }
        }
    }

    render() {
        const {id, node, dispatch, editorData, size, resolution, selection, playing, isActive, style, editorSetActiveTab, tabModify, ...remainingProps} = this.props

        const select = () => editorSetActiveTab(id)
        const editorSetState = (data) => tabModify({id, ...data})

        let actions = {
            ...ContentActions,
            ...FuncUtils.getFunctionByName(editorData.editorActions, window)
        }

        let boundActionCreators = bindActionCreators(actions)

        return !isActive ? (
            <AnimatedCard style={style} containerStyle={Tab.styles.container} maximised={isActive} expanded={isActive} onExpandChange={!isActive ? select : null}>
                <CardHeader title={id} actAsExpander={true} showExpandableButton={true} />
                <CardMedia style={Tab.styles.child} mediaStyle={Tab.styles.child}>
                    <this.props.child {...this.props} style={Tab.styles.child} icon={false} {...boundActionCreators} />
                </CardMedia>
            </AnimatedCard>
        ) : (
            <AnimatedCard style={style} containerStyle={Tab.styles.container} maximised={true} expanded={isActive} onExpandChange={!isActive ? select : null}>
                <Toolbar style={{flexShrink: 0}}>
                    {selection && <SelectionControls editorData={editorData} node={node} firstChild={true} selection={selection} playing={playing} />}
                    {resolution && <ResolutionControls editorData={editorData} node={node} />}
                    {size && <SizeControls editorData={editorData} node={node} />}

                    <ToolbarGroup>
                        <ContentControls.Save editorData={editorData} node={node} {...boundActionCreators} />
                        <ContentControls.Undo editorData={editorData} node={node} {...boundActionCreators} />
                        <ContentControls.Redo editorData={editorData} node={node} {...boundActionCreators} />

                        <ContentControls.ToggleLineNumbers editorData={editorData} node={node} {...boundActionCreators} />
                        <ContentControls.ToggleLineWrapping editorData={editorData} node={node} {...boundActionCreators} />

                        <ContentControls.JumpTo editorData={editorData} node={node} {...boundActionCreators} />
                        <ContentControls.Search editorData={editorData} node={node} {...boundActionCreators} />
                    </ToolbarGroup>
                </Toolbar>

                <this.props.child {...this.props} style={Tab.styles.child} {...boundActionCreators} />
            </AnimatedCard>
        )
    }
}

function mapStateToProps(state, ownProps) {
    const { editor, tabs } = state

    let current = tabs.filter(tab => tab.id === ownProps.id)[0]

    return  {
        ...ownProps,
        ...current,
        isActive: editor.activeTabId === current.id
    }
}

const AnimatedCard = makeMaximise(Card)

const EditorTab = connect(mapStateToProps, actions)(Tab)

export default EditorTab
