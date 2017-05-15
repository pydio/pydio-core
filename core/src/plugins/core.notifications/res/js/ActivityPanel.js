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
import Pydio from 'pydio';

const { NodeListCustomProvider } = Pydio.requireLib('components');
const { InfoPanelCard, FilePreview } = Pydio.requireLib('workspaces');
const { Animations } = Pydio.requireLib('hoc');
const { PydioContextConsumer } = Pydio.requireLib('boot');

const Template =  Animations.makeTransition(
    {opacity: 0.3},
    {opacity: 1}
)((props) => <div {...props} style={{padding: 0}} />)

class ActivityPanel extends React.Component {

    static get EventsIcons() {
        return {
            'add'       : 'folder-plus',
            'add-file'  : 'folder-upload',
            'delete'    : 'delete',
            'change'    : 'pencil',
            'rename'    : 'rename-box',
            'view'      : 'eye',
            'copy'      : 'content-copy',
            'move'      : 'folder-move',
            'copy_to'   : 'folder-move',
            'copy_from' : 'folder-move',
            'move_from' : 'folder-move',
            'move_to'   : 'folder-move'
        }
    }

    static get styles() {
        return {
            roundedIconContainer: {
                borderRadius: "50%",
                margin: 15,
                height: 40,
                width: 40,
                lineHeight: '40px',
                display: 'flex',
                alignItems: 'center',
                justifyContent:'center'
            },
            roundedIconMimeFont: {
                fontSize: 24,
                textAlign: "center"
            },
            timeline: {
                position: 'absolute',
                top: 0,
                left: 33,
                bottom: 0,
                width: 4,
                backgroundColor: '#eceff1'
            }
        }
    }

    constructor(props) {
        super(props)
        if(props.pydio && !props.pydio.user || props.pydio.user.activeRepository === 'inbox'){
            this.state = {empty: true};
        }else{
            this.state = {
                empty: true,
                dataModel: this.initDataModel(this.props.node)
            }
        }

    }

    initDataModel(node) {
        const dataModel = PydioDataModel.RemoteDataModelFactory(this.getProviderProperties(node), "Activity");
        dataModel.getRootNode().observe('loaded', () => {
            this.setState({empty: !dataModel.getRootNode().getChildren().size});
        });
        dataModel.getRootNode().load();
        return dataModel;
    }

    componentWillReceiveProps(nextProps) {
        if(nextProps.node !== this.props.node){
            if(nextProps.pydio && nextProps.pydio.user && nextProps.pydio.user.activeRepository === 'inbox'){
                this.setState({empty: true});
                return;
            }
            this.setState({
                dataModel: this.initDataModel(nextProps.node)
            }, () => {
                if(this.refs.provider) this.refs.provider.reload();
            });
        }
    }

    getProviderProperties(node) {

        return {
            "get_action":"get_my_feed",
            "connexion_discrete":true,
            "format":"xml",
            "current_repository":"true",
            "feed_type":"notif",
            "limit":(node.isLeaf() || node.isRoot() ? 18 : 4),
            "path":(node.isLeaf() || node.isRoot()?node.getPath():node.getPath()+'/'),
            "merge_description":"true",
            "description_as_label":node.isLeaf()?"true":"false",
            "cache_service":{
                "metaStreamName":"files.activity" + node.getPath(),
                "expirationPolicy":MetaCacheService.EXPIRATION_MANUAL_TRIGGER
            }
        };
    }

    renderIconFile(node) {
        let fileNode = new AjxpNode(node.getMetadata().get('real_path'), node.isLeaf(), node.getLabel());
        fileNode.setMetadata(node.getMetadata());
        return (
            <div style={{position:'relative'}}>
                <div style={{...ActivityPanel.styles.timeline, bottom: -1}}/>
                <FilePreview
                    node={fileNode}
                    style={ActivityPanel.styles.roundedIconContainer}
                    mimeFontStyle={ActivityPanel.styles.roundedIconMimeFont}
                    loadThumbnail={true}
                />
            </div>
        );
    }

    renderTimelineEntry(props) {

        const {node, isFirst} = props;
        let action = node.getMetadata().get('event_action');
        if(action === 'add' && node.isLeaf()){
            action = 'add-file';
        }

        const {timeline} = ActivityPanel.styles;

        if(isFirst){
            timeline['top'] = 34;
        }

        return (
            <div className="ajxp_node_leaf material-list-entry material-list-entry-2-lines" style={{borderBottom: 0}}>
                <div style={{position:'relative'}} className="material-list-icon">
                    <div style={timeline}/>
                    <FilePreview
                        node={node}
                        style={ActivityPanel.styles.roundedIconContainer}
                        mimeClassName={"mimefont mdi mdi-" + ActivityPanel.EventsIcons[action]}
                        mimeFontStyle={ActivityPanel.styles.roundedIconMimeFont}
                        loadThumbnail={false}
                    />
                </div>
                <div className="material-list-text">
                    <div className="material-list-line-1" style={{whiteSpace:'normal', lineHeight: '24px'}}>{node.getMetadata().get('event_description')}</div>
                    <div className="material-list-line-2">{node.getMetadata().get('short_date')}</div>
                </div>
            </div>

        );
    }

    renderFirstLineLeaf(node) {
        return <div style={{whiteSpace:'normal', lineHeight: '24px'}}>{node.getMetadata().get('event_description')}</div>
    }

    renderSecondLine(node) {
        return <div style={{whiteSpace:'normal'}}>{node.getMetadata().get('event_description')}</div>;
    }

    renderActions(node) {
        const {pydio} = this.props;
        const open = function(){
            pydio.goTo(node.getMetadata().get('real_path'));
        };
        return <MaterialUI.IconButton
            iconClassName="mdi mdi-arrow-right"
            onTouchTap={open}
            iconStyle={{color: 'rgba(0,0,0,0.23)',iconHoverColor: 'rgba(0,0,0,0.63)'}}/>
    }

    render() {

        if(this.state.empty){
            return null;
        }
        const {pydio, node, getMessage} = this.props;

        let renderIcon = this.renderIconFile;
        let renderFirstLine = null;
        let renderCustomEntry = null;
        let renderSecondLine = this.renderSecondLine;
        let nodeClicked = (node) => {
            pydio.goTo(node.getMetadata().get('real_path'));
        };
        if(node.isLeaf()){
            renderCustomEntry = this.renderTimelineEntry;
            renderFirstLine = null;
            renderSecondLine = null;
            renderIcon = null;
            nodeClicked = () => {};
        }

        let label = node.isLeaf() ? getMessage('notification_center.11') : getMessage('notification_center.10');
        let root = false;
        if(node === pydio.getContextHolder().getRootNode()){
            label = getMessage('notification_center.9');
            root = true;
        }

        return (
            <InfoPanelCard title={label} icon="pulse" iconColor="#F57C00" style={this.props.style}>
                <Template>
                    <NodeListCustomProvider
                        pydio={pydio}
                        className="files-list"
                        elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES + 2}
                        heightAutoWithMax={root ? 420 : 320}
                        presetDataModel={this.state.dataModel}
                        actionBarGroups={[]}
                        ref="provider"
                        hideToolbar={true}
                        renderCustomEntry={renderCustomEntry}
                        entryRenderIcon={renderIcon}
                        entryRenderFirstLine={renderFirstLine}
                        entryRenderSecondLine={renderSecondLine}
                        nodeClicked={nodeClicked}
                        defaultSortingInfo={{attribute : 'event_time',sortType:'number',direction : 'desc'}}
                        verticalScroller={true}
                    />
                </Template>
            </InfoPanelCard>
        );
    }
}

ActivityPanel = PydioContextConsumer(ActivityPanel)
export {ActivityPanel as default}