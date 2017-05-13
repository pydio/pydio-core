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

export default class NotificationsPanel extends React.Component {

    constructor(props) {
        super(props)

        let providerProperties = {
            get_action:"get_my_feed",
            connexion_discrete:true,
            format:"xml",
            feed_type:"alert",
            merge_description:"false"
        };
        let repositoryScope = 'all';
        if(!(pydio && pydio.user && pydio.user.activeRepository === 'ajxp_home')){
            providerProperties['current_repository'] = 'true';
            repositoryScope = pydio.user.activeRepository;
        }
        const dataModel = PydioDataModel.RemoteDataModelFactory(providerProperties, 'Notifications');
        const rNode = dataModel.getRootNode();
        rNode.observe("loaded", function(){
            const unread = parseInt(rNode.getMetadata().get('unread_notifications_count')) || 0;
            this.setState({unreadStatus: unread}, this.onStatusChange.bind(this));
        }.bind(this));
        rNode.load();

        if(repositoryScope === 'all'){
            this._pe = new PeriodicalExecuter(() => {rNode.reload(null, true)}, 8);
        }else{
            this._smObs = function(event){
                if(XMLUtils.XPathSelectSingleNode(event, 'tree/reload_user_feed')) {
                    rNode.reload(null, true);
                }
            }.bind(this);
        }
        this.props.pydio.observe("server_message", this._smObs);

        this.state = {
            open: false,
            dataModel:dataModel,
            repositoryScope: repositoryScope,
            unreadStatus: 0
        };
    }

    onStatusChange(){
        if(this.props.onUnreadStatusChange){
            this.props.onUnreadStatusChange(this.state.unreadStatus);
        }
    }

    componentWillUnmount() {
        if(this._smObs){
            this.props.pydio.stopObserving("server_message", this._smObs);
        }else if(this._pe){
            this._pe.stop();
        }
    }

    handleTouchTap(event) {
        // This prevents ghost click.
        event.preventDefault();
        if(this.state.unreadStatus){
            this.updateAlertsLastRead();
        }
        this.setState({
            open: true,
            anchorEl: event.currentTarget,
            unreadStatus: 0
        }, this.onStatusChange.bind(this));
    }

    handleRequestClose() {
        this.setState({
            open: false,
        });
    }

    renderIcon(node) {
        return (
            <PydioWorkspaces.FilePreview
                loadThumbnail={true}
                node={node}
                pydio={this.props.pydio}
                rounded={true}
            />
        );
    }

    renderSecondLine(node) {
        return node.getMetadata().get('event_description');
    }

    renderActions(node) {
        const touchTap = function(event){
            event.stopPropagation();
            this.dismissAlert(node);
        }.bind(this);
        return <MaterialUI.IconButton
            iconClassName="mdi mdi-close"
            onClick={touchTap}
            style={{width: 36, height: 36, padding: 6}}
            iconStyle={{color: 'rgba(0,0,0,.23)', hoverColor:'rgba(0,0,0,.73)'}}
        />;
    }

    entryClicked(node) {
        this.handleRequestClose();
        this.props.pydio.goTo(node);
    }

    dismissAlert(node) {
        const alertId = node.getMetadata().get('alert_id');
        const occurences = node.getMetadata().get('event_occurence');
        PydioApi.getClient().request({
            get_action:'dismiss_user_alert',
            alert_id:alertId,
            // Warning, occurrences parameter expects 2 'r'
            occurrences:occurences
        }, function(t){
            this.refs.list.reload();
            this.setState({unreadStatus: 0}, this.onStatusChange.bind(this))
        }.bind(this));
    }

    updateAlertsLastRead() {
        PydioApi.getClient().request({
            get_action          : 'update_alerts_last_read',
            repository_scope    : this.state.repositoryScope
        }, (transp) => {
            this.setState({unreadStatus: 0}, this.onStatusChange.bind(this));
        });
    }

    render() {

        const LIST = (
            <PydioComponents.NodeListCustomProvider
                ref="list"
                className={'files-list ' + (this.props.listClassName || '')}
                hideToolbar={true}
                pydio={this.props.pydio}
                elementHeight={PydioComponents.SimpleList.HEIGHT_TWO_LINES + 2}
                heightAutoWithMax={(this.props.listOnly? null : 500)}
                presetDataModel={this.state.dataModel}
                reloadAtCursor={true}
                actionBarGroups={[]}
                entryRenderIcon={this.renderIcon.bind(this)}
                entryRenderSecondLine={this.renderSecondLine.bind(this)}
                entryRenderActions={this.renderActions.bind(this)}
                nodeClicked={this.entryClicked.bind(this)}
                emptyStateProps={{
                    style:{paddingTop: 20, paddingBottom: 20},
                    iconClassName:'mdi mdi-bell-off',
                    primaryTextId:'notification_center.14',
                    secondaryTextId:'notification_center.15',
                    ...this.props.emptyStateProps
                }}
            />
        );

        if(this.props.listOnly){
            return LIST;
        }

        return (
            <span>
                <MaterialUI.Badge
                    badgeContent={this.state.unreadStatus}
                    secondary={true}
                    style={this.state.unreadStatus  ? {padding: '0 24px 0 0'} : {padding: 0}}
                    badgeStyle={!this.state.unreadStatus ? {display:'none'} : null}
                >
                    <MaterialUI.IconButton
                    onTouchTap={this.handleTouchTap.bind(this)}
                    iconClassName={this.props.iconClassName || "icon-bell"}
                    tooltip={this.props.pydio.MessageHash['notification_center.4']}
                    className="userActionButton alertsButton"
                />
                </MaterialUI.Badge>
                <MaterialUI.Popover
                    open={this.state.open}
                    anchorEl={this.state.anchorEl}
                    anchorOrigin={{horizontal: 'left', vertical: 'bottom'}}
                    targetOrigin={{horizontal: 'left', vertical: 'top'}}
                    onRequestClose={this.handleRequestClose.bind(this)}
                    style={{width:320}}
                    zDepth={2}

                >
                    {LIST}
                </MaterialUI.Popover>
            </span>
        );
    }

}
