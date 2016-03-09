/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */
(function(global) {

    const STATUS_PENDING  = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_DECLINED = 4;
    const STATUS_LOCAL    = 0;
    const STATUS_ANSWERED = [STATUS_LOCAL, STATUS_ACCEPTED, STATUS_DECLINED];

    const CLASS_NAMES = {
        "accept": "icon-plus-sign ajxp_icon_span",
        "decline": "icon-remove-sign ajxp_icon_span",
        "view": "icon-signin ajxp_icon_span"
    }

    // Messages
    var ContextConsumerMixin = {
        contextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func,
            isReadonly:React.PropTypes.func
        }
    };

    /*****************************
    /* Main Panel
    /*
    /* Entry Point for the UI
    /*****************************/
    var MainPanel = React.createClass({

        propTypes: {
            closeAjxpDialog: React.PropTypes.func.isRequired,
            pydio:React.PropTypes.instanceOf(Pydio).isRequired,
            selection:React.PropTypes.instanceOf(PydioDataModel).isRequired,
            readonly:React.PropTypes.bool
        },

        childContextTypes: {
            messages:React.PropTypes.object,
            getMessage:React.PropTypes.func
        },

        getChildContext: function() {
            var messages = this.props.pydio.MessageHash;
            return {
                messages: messages,
                getMessage: function(messageId, namespace='share_center'){
                    try{
                        return messages[namespace + (namespace?".":"") + messageId] || messageId;
                    }catch(e){
                        return messageId;
                    }
                }
            };
        },

        refreshDialogPosition:function(){
            global.pydio.UI.modal.refreshDialogPosition();
        },

        sharesUpdated: function() {
            if(this.isMounted()) {
                this.setState({
                    shares: this.state.shares
                }, function(){
                    this.refreshDialogPosition();
                }.bind(this));
            }
        },

        getInitialState: function(){
            return {
                shares: new ReactModel.ShareNotificationList(this.props.pydio)
            };
        },

        componentDidMount: function(){
            ReactDispatcher.ShareNotificationDispatcher.getClient().observe('status_changed', this.sharesUpdated);
        },

        componentWillUnmount: function() {
            ReactDispatcher.ShareNotificationDispatcher.getClient().stopObserving('status_changed', this.sharesUpdated);
        },

        clicked: function() {
            this.props.closeAjxpDialog();
        },

        getMessage: function(key, namespace = 'share_center') {
            return this.props.pydio.MessageHash[namespace + (namespace?'.':'') + key];
        },

        render: function() {
            var shares = this.state.shares,
                pendingShares = shares && shares.getSharesByStatus(STATUS_PENDING) || [],
                answeredShares = shares && shares.getSharesByStatus(STATUS_ANSWERED) || [];

            return(
                <div className="left-panel">
                    <div className="section-title">Pending shares</div>
                    <Shares {...this.props} shares={pendingShares}/>

                    <div className="section-title">History</div>
                    <Shares {...this.props} shares={answeredShares}/>
                </div>
            );
        }
    });

    /*****************************
    /* Shares Collection
    /*****************************/
    var Shares = React.createClass({

        propTypes:{
            shares: React.PropTypes.instanceOf(ReactModel.ShareNotificationList)
        },

        render: function(){
            var shares = this.props.shares.map(function(u) {
                return <Share {...this.props} share={u} />
            }.bind(this));

            return (
                <div style={{padding:'0 16px 10px'}}>
                    {shares}
                </div>
            );
        }
    });

    /*****************************
    /* Share
    /*****************************/
    var Share = React.createClass({
        propTypes: {
            share: React.PropTypes.instanceOf(ReactModel.ShareNotification)
        },

        render: function() {
            var share = this.props.share,
                owner = share.getOwner(),
                label = share.getLabel(),
                crDate = share.getFormattedDate();

            return (
                <div style={{marginBottom:'5px'}}>
                    <div className="pull-left" style={{width: '90%'}}>
                        <div className="workspace-label">{label}</div>
                        <div className="workspace-link">{owner} > {crDate}</div>
                    </div>
                    <div className="pull-left" style={{width: '10%'}}>
                        <Links {...this.props} share={share} />
                    </div>
                    <div style={{clear: 'both'}} />
                </div>
            );
        }
    });

    /*********************************
    /* Links Collection for a Share
    /*********************************/
    var Links = React.createClass({
        propTypes: {
            share: React.PropTypes.instanceOf(ReactModel.ShareNotification)
        },

        render: function () {
            var share = this.props.share,
                actions = share && share.getActions().map(function (action) {
                    return (
                        <Link {...this.props} share={share} action={action} />
                    );
                }.bind(this));

            return (
                <div>{actions}</div>
            );
        }
    });

    /*********************************
    /* Link for a Share
    /*********************************/
    var Link = React.createClass({
        propTypes: {
            share: React.PropTypes.instanceOf(ReactModel.ShareNotification),
            action: React.PropTypes.object
        },

        triggerModelClick: function () {
            var options = this.props.action.options,
                actionType = options.get_action;

            if (actionType == 'switch_repository') {
                this.props.pydio.triggerRepositoryChange(options.repository_id);
            } else {
                this.props.share.loadAction(options);
            }
        },

        render: function () {
            var share = this.props.share,
                action = this.props.action,
                className = CLASS_NAMES[action.id] || "";

            return (
                <a className={className} onClick={this.triggerModelClick} title={action.message} />
            );
        }
    });

    var EventNamespace = global.ShareNotificationUI || {};
    EventNamespace.MainPanel = MainPanel;
    global.ShareNotificationUI = EventNamespace;

})(window);