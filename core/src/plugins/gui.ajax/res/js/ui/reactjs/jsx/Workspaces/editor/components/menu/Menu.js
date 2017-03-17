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
import { connect } from 'react-redux';
import * as actions from '../../actions';

import MainButton from './MainButton';
import MenuGroup from './MenuGroup';
import MenuItem from './MenuItem';



// Components
class Menu extends React.Component {
    constructor(props) {
        super(props);

        const {editorModifyMenu} = props

        this.toggle = () => {
            const {menu} = this.props

            editorModifyMenu({open: !menu.open})
        }
    }

    renderChild() {

        const {activeTabId, tabs, open} = this.props

        if (!open) return null

        return tabs.map((tab) => {
            const style = {
                position: "absolute",
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                transition: "transform 0.3s ease-in"
            }
            const activeStyle = activeTabId !== tab.id ? {transform: "translateX(-100%)"} : {transform: "translateX(0)"}

            return <MenuItem key={tab.id} id={tab.id} style={{...style, ...activeStyle}} />
        })
    }

    render() {
        const {tabs, style, open, loaded} = this.props

        return (
            <div>
                <MenuGroup style={style}>
                    {this.renderChild()}
                </MenuGroup>
                <MainButton open={open} style={style} onClick={this.toggle} />
            </div>
        );
    }
};

// REDUX - Then connect the redux store
function mapStateToProps(state, ownProps) {
    const { editor, tabs } = state

    const activeTabId = editor.activeTabId || (tabs.length > 0 && tabs[0].id)
    const activeTab = tabs.filter(tab => tab.id === activeTabId)[0]

    return  {
        ...editor,
        open: typeof activeTabId !== "boolean" && editor.menu.open,
        activeTabId: activeTabId,
        activeTab: activeTab,
        tabs
    }
}
const ConnectedMenu = connect(mapStateToProps, actions)(Menu)

// EXPORT
export default ConnectedMenu;
