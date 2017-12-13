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
import React from 'react'
import XMLUtils from 'pydio/util/xml'
import PydioDataModel from 'pydio/model/data-model'
import FilePreview from '../views/FilePreview'
const {muiThemeable} = require('material-ui/styles')
const Color = require('color')
const {RefreshIndicator, IconButton, Popover} = require('material-ui')
const Pydio = require('pydio')
const {NodeListCustomProvider, SimpleList} = Pydio.requireLib("components")

class BookmarksList extends React.Component {

    constructor(props) {
        super(props)

        let providerProperties = {
            get_action:"search_by_keyword",
            connexion_discrete:true,
            field:"ajxp_bookmarked",
        };
        const dataModel = PydioDataModel.RemoteDataModelFactory(providerProperties, 'Notifications');
        const rNode = dataModel.getRootNode();
        rNode.observe('loading', ()=>{this.setState({loading: true})});
        rNode.observe('loaded', ()=>{this.setState({loading: false})});

        this._smObs = function(event){
            if(XMLUtils.XPathSelectSingleNode(event, 'tree/reload_bookmarks')) {
                if(this.state.open){
                    rNode.reload(null, true);
                } else {
                    rNode.clear();
                }
            }
        }.bind(this);
        this.props.pydio.observe("server_message", this._smObs);

        let activeRepo;
        const hasIndexer = !! XMLUtils.XPathSelectSingleNode(props.pydio.getXmlRegistry(), "plugins/indexer");
        if(this.props.pydio.user && hasIndexer){
            activeRepo = props.pydio.user.activeRepository;
        }

        this.state = {
            open: false,
            dataModel:dataModel,
            rootNode: rNode,
            activeRepository: activeRepo
        };
    }

    componentWillUnmount() {
        if(this._smObs){
            this.props.pydio.stopObserving("server_message", this._smObs);
        }
    }

    componentWillReceiveProps(nextProps){
        if(nextProps.pydio.user && nextProps.pydio.user.activeRepository !== this.state.activeRepository ){
            this.state.rootNode.clear();
            const hasIndexer = !! XMLUtils.XPathSelectSingleNode(nextProps.pydio.getXmlRegistry(), "plugins/indexer");
            if(hasIndexer) {
                this.setState({activeRepository: nextProps.pydio.user.activeRepository});
            } else {
                this.setState({activeRepository: null});
            }
        }
    }

    handleTouchTap(event) {
        // This prevents ghost click.
        event.preventDefault();
        this.state.rootNode.load();
        this.setState({
            open: true,
            anchorEl: event.currentTarget,
        });
    }

    handleRequestClose() {
        this.setState({
            open: false,
        });
    }

    renderIcon(node) {
        return (
            <FilePreview
                loadThumbnail={true}
                node={node}
                pydio={this.props.pydio}
                rounded={true}
            />
        );
    }

    renderSecondLine(node) {
        return node.getPath();
    }

    entryClicked(node) {
        this.handleRequestClose();
        this.props.pydio.goTo(node);
    }

    render() {

        if(!this.state.activeRepository){
            return null;
        }
        const mainColor = Color(this.props.muiTheme.palette.primary1Color);
        let loader;
        if(this.state.loading){
            loader = (
                <div style={{height: 200, backgroundColor:mainColor.lightness(97).rgb().toString()}}>
                    <RefreshIndicator
                        size={40}
                        left={140}
                        top={40}
                        status="loading"
                        style={{}}
                    />
                </div>
            );
        }

        return (
            <span>
                <IconButton
                    onTouchTap={this.handleTouchTap.bind(this)}
                    iconClassName={"userActionIcon mdi mdi-bookmark-check"}
                    tooltip={this.props.pydio.MessageHash['147']}
                    className="userActionButton"
                />
                <Popover
                    open={this.state.open}
                    anchorEl={this.state.anchorEl}
                    anchorOrigin={{horizontal: 'left', vertical: 'bottom'}}
                    targetOrigin={{horizontal: 'left', vertical: 'top'}}
                    onRequestClose={this.handleRequestClose.bind(this)}
                    style={{width:320}}
                    zDepth={2}

                >
                    {loader}
                    {!this.state.loading &&
                        <NodeListCustomProvider
                            ref="list"
                            className={'files-list ' + (this.props.listClassName || '')}
                            hideToolbar={true}
                            pydio={this.props.pydio}
                            elementHeight={SimpleList.HEIGHT_TWO_LINES + 2}
                            heightAutoWithMax={(this.props.listOnly? null : 500)}
                            presetDataModel={this.state.dataModel}
                            reloadAtCursor={true}
                            actionBarGroups={[]}
                            entryRenderIcon={this.renderIcon.bind(this)}
                            entryRenderSecondLine={this.renderSecondLine.bind(this)}
                            nodeClicked={this.entryClicked.bind(this)}
                            emptyStateProps={{
                                style:{paddingTop: 20, paddingBottom: 20},
                                iconClassName:'mdi mdi-bookmark-outline',
                                primaryTextId:'145',
                                secondaryTextId:'482',
                                ...this.props.emptyStateProps
                            }}
                        />
                    }
                </Popover>
            </span>
        );
    }

}


BookmarksList = muiThemeable()(BookmarksList);
export {BookmarksList as default}
