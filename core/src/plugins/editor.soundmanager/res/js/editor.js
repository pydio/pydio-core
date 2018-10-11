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

import React, {Component} from 'react'
import { connect } from 'react-redux'
import { compose } from 'redux'
import {Paper, Table, TableBody, TableRow, TableRowColumn } from 'material-ui'
import Player from './Player'
import HasherUtils from 'pydio/util/hasher'

class Editor extends Component {

    constructor(props) {
        super(props);
        this.state = {showPlayer: true};
    }


    static get styles() {
        return {
            container: {
                margin: "auto",
                display: "flex",
                flexDirection: "column",
                justifyContent: "space-between",
                flex: 1,
                backgroundColor: '#fafafa'
            },
            player: {
                margin: "auto"
            },
            paper : {
                margin: 10,
            },
            table: {
                width: "100%"
            },
            row:{
                backgroundColor:'transparent'
            },
            rowSelected:{
                backgroundColor:'#fafafa'
            },
            leftCol: {
                width:60,
                textAlign:'center',
                paddingRight:0
            },
            leftColIcon:{
                cursor: 'pointer'
            }
        }
    }

    componentWillReceiveProps(nextProps) {
        const sound = soundManager.getSoundById(nextProps.node.getPath())

        if (sound && this.props.node.getPath() !== nextProps.node.getPath()) {
            soundManager.soundIDs.map((soundID) => {
                try{
                    soundManager.sounds[soundID].stop()
                } catch (e) {

                }
            })
            this.setState({showPlayer: false}, () => {
                this.setState({showPlayer: true});
            });
        }

        if (sound)  {
            if (nextProps.selectionPlaying) {
                sound.play()
            } else {
                sound.pause()
            }
        }
    }

    onRowsSelected(rows){
        if(!rows || !rows.length) return;
        const index = rows[0];
        const {onRequestSelectionPlay} = this.props;
        onRequestSelectionPlay(null, index, true);
    }

    render() {

        const {node, selectionPlaying, selection, onRequestSelectionPlay} = this.props;
        const {showPlayer} = this.state;
        console.log(selection, showPlayer);
        let url, crtIndex = 0;
        if(selection && showPlayer && node){
            url = pydio.Parameters.get('ajxpServerAccess') + '&get_action=audio_proxy&file=' + encodeURIComponent(HasherUtils.base64_encode(node.getPath()));
            selection.selection.forEach((n, i) => {
                if (n.getPath() === node.getPath()){
                    crtIndex = i;
                }
            })
        }

        return (
            <div style={Editor.styles.container}>
                {url &&
                    <Player
                        id={node.getPath()}
                        url={url}
                        rich={true}
                        style={{width: 250, height: 200, margin: "auto"}}
                        onReady={() => {}}
                        onPlay={()=>{
                            onRequestSelectionPlay(null, crtIndex, true);
                        }}
                        onPause={()=>{
                            onRequestSelectionPlay(null, crtIndex, false);
                        }}
                        disableAutoPlay={true}
                        onFinish={() => {
                            // Handle autoPlay here
                            if(selection && selection.selection && selection.selection[crtIndex+1]){
                                onRequestSelectionPlay(null, crtIndex+1, true);
                            }
                        }}
                    />
                }
                <Paper zDepth={1} style={Editor.styles.paper}>
                <Table
                    style={Editor.styles.table}
                    selectable={true}
                    multiSelectable={false}
                    onRowSelection={this.onRowsSelected.bind(this)}
                >
                    <TableBody
                        displayRowCheckbox={false}
                        stripedRows={false}
                        deselectOnClickaway={false}
                    >
                    {selection && selection.selection.map( (n, index) => {
                        let leftCol = index + 1;
                        let rowStyle = Editor.styles.row;
                        if(node && (n.getPath() === node.getPath())){
                            if(selectionPlaying){
                                leftCol = <span className={"mdi mdi-pause"} style={Editor.styles.leftColIcon} onClick={() => {onRequestSelectionPlay(null, index, false)}}/>;
                            } else {
                                leftCol = <span className={"mdi mdi-play"}  style={Editor.styles.leftColIcon} onClick={() => {onRequestSelectionPlay(null, index, true)}}/>;
                            }
                            rowStyle = Editor.styles.rowSelected;
                        }
                        return (
                            <TableRow key={index}>
                                <TableRowColumn style={{...Editor.styles.leftCol, ...rowStyle}}>{leftCol}</TableRowColumn>
                                <TableRowColumn style={rowStyle}>{n.getLabel()}</TableRowColumn>
                            </TableRow>
                        )
                    })}
                    </TableBody>
                </Table>
                </Paper>
            </div>
        );
    }
}

function guid() {
    return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
}

function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
        .toString(16)
        .substring(1);
}

const {withSelection, withMenu, withLoader, withErrors, withControls} = PydioHOCs;

const editors = pydio.Registry.getActiveExtensionByType("editor")
const conf = editors.filter(({id}) => id === 'editor.soundmanager')[0]

const getSelectionFilter = (node) => conf.mimes.indexOf(node.getAjxpMime()) > -1

const getSelection = (node) => new Promise((resolve, reject) => {
    let selection = [];

    node.getParent().getChildren().forEach((child) => selection.push(child));
    selection = selection.filter(getSelectionFilter)

    resolve({
        selection,
        currentIndex: selection.reduce((currentIndex, current, index) => current === node && index || currentIndex, 0)
    })
})

const getTime = function(nMSec,bAsString) {

  // convert milliseconds to mm:ss, return as object literal or string
  var nSec = Math.floor(nMSec/1000),
      min = Math.floor(nSec/60),
      sec = nSec-(min*60);
  // if (min === 0 && sec === 0) return null; // return 0:00 as null
  return (bAsString?(min+':'+(sec<10?'0'+sec:sec)):{'min':min,'sec':sec});

};

export default compose(
    withSelection(getSelection),
    connect()
)(Editor)
