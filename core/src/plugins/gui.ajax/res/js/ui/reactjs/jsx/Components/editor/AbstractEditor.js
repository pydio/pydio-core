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

class AbstractEditor extends React.Component {

    static getSvgSource(ajxpNode) {
        return ajxpNode.getMetadata().get("fonticon");
    }

    getActions() {
        if (!this.props.actions) return null;

        return <MaterialUI.Toolbar>{this.props.actions}</MaterialUI.Toolbar>
    }

    // Return potential Error message
    getError() {
        if (!this.props.errorString) return null;

        return (
            <div style={{display:'flex',alignItems:'center',width:'100%',height:'100%'}}>
                <div style={{flex:1,textAlign:'center',fontSize:20}}>{this.props.errorString}</div>
            </div>
        )
    }

    // Return the loading component
    getLoader() {
        if (!this.props.loading) return null;

        return (
            <PydioReactUI.Loader style={{position: "absolute", top: 0, bottom: 0, left: 0, right: 0, zIndex: 0}}/>
        )
    }

    render() {
        let style = null;

        if (this.props.loading) {
            // Make the editor unseeable so that we have time to load
            style = {
                position:"relative",
                zIndex: "-1",
                top: "-3000px"
            }
        }

        return (
            <div style={{position: "relative", padding: 0, margin: 0, display: "flex", flexDirection: "column", flex: 1}}>
                {this.getActions()}
                {this.getError()}
                <div style={{...style, display: "flex", flex: 1}}>
                    {this.getLoader()}
                    {this.props.children}
                </div>
            </div>
        )
    }
}

AbstractEditor.propTypes = {
    node: React.PropTypes.instanceOf(AjxpNode),
    pydio: React.PropTypes.instanceOf(Pydio),

    icon: React.PropTypes.bool,

    loading: React.PropTypes.bool,
    errorString: React.PropTypes.string,

    onRequestTabTitleUpdate: React.PropTypes.func,
    onRequestTabClose: React.PropTypes.func,
    actions:React.PropTypes.array
}

export {AbstractEditor as default}
