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

import React from 'react';
import ReactDOM from 'react-dom';

import {ToolbarGroup, IconButton} from 'material-ui';

import {withControls} from './controls';
import {getDisplayName} from './utils';

class SelectionModel extends Observable{

    constructor(node, filter = () => true){
        super();
        this.currentNode = node;
        this.filter = filter
        this.selection = [];
        this.buildSelection();
    }

    buildSelection(){
        let currentIndex;
        let child;
        let it = this.currentNode.getParent().getChildren().values();
        while(child = it.next()){
            if(child.done) break;
            let node = child.value;
            if (this.filter(node)) {
                this.selection.push(node);
                if(node === this.currentNode){
                    this.currentIndex = this.selection.length - 1;
                }
            }
        }
    }

    length(){
        return this.selection.length;
    }

    hasNext(){
        return this.currentIndex < this.selection.length - 1;
    }

    hasPrevious(){
        return this.currentIndex > 0;
    }

    current(){
        return this.selection[this.currentIndex];
    }

    next(){
        if(this.hasNext()){
            this.currentIndex ++;
        }
        return this.current();
    }

    previous(){
        if(this.hasPrevious()){
            this.currentIndex --;
        }
        return this.current();
    }

    first(){
        return this.selection[0];
    }

    last(){
        return this.selection[this.selection.length -1];
    }

    nextOrFirst(){
        if(this.hasNext()) this.currentIndex ++;
        else this.currentIndex = 0;
        return this.current();
    }
}

const withSelection = (filter) => {
    return (Component) => {
        return class extends React.Component {
            static get displayName() {
                return `WithSelection(${getDisplayName(Component)})`
            }

            static get propTypes() {
                return {
                    node: React.PropTypes.instanceOf(AjxpNode).isRequired,
                    filter: React.PropTypes.func
                }
            }

            constructor(props) {
                super(props)

                this.selection = new SelectionModel(props.node, props.filter)

                this.state = {
                    node: this.selection.currentNode
                }
            }

            render() {
                const sel = this.selection
                const {node, playing} = this.state
                const {controls, ...remainingProps} = this.props

                if (!node) return null

                let newControls = {
                    ...controls,
                    selection: [
                        <IconButton onClick={() => this.setState({node: sel.previous()})} iconClassName="mdi mdi-arrow-left" disabled={!sel.hasPrevious()} />,
                        <IconButton onClick={() => this.setState({playing: !playing})} iconClassName={`mdi mdi-${playing ? "pause" : "play"}`} disabled={!sel.hasPrevious() && !sel.hasNext()} />,
                        <IconButton onClick={() => this.setState({node: sel.next()})} iconClassName="mdi mdi-arrow-right" disabled={!sel.hasNext()} />
                    ]
                }

                return (
                    <Component
                        {...remainingProps}

                        selectionPlaying={playing}
                        onRequestSelectionPlay={() => this.setState({node: sel.nextOrFirst()})}

                        node={node}
                        controls={newControls}
                    />
                )
            }
        }
    }
}

export {withSelection}
