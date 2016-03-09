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
"use strict";

var _extends = Object.assign || function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; };

(function (global) {

    var STATUS_PENDING = 1;
    var STATUS_ACCEPTED = 2;
    var STATUS_DECLINED = 4;
    var STATUS_ANSWERED = [STATUS_ACCEPTED, STATUS_DECLINED];

    var CLASS_NAMES = {
        "accept": "icon-plus-sign ajxp_icon_span",
        "decline": "icon-remove-sign ajxp_icon_span",
        "view": "icon-signin ajxp_icon_span"
    };

    // Messages
    var ContextConsumerMixin = {
        contextTypes: {
            messages: React.PropTypes.object,
            getMessage: React.PropTypes.func,
            isReadonly: React.PropTypes.func
        }
    };

    /*****************************
    /* Main Panel
    /*
    /* Entry Point for the UI
    /*****************************/
    var MainPanel = React.createClass({
        displayName: "MainPanel",

        propTypes: {
            closeAjxpDialog: React.PropTypes.func.isRequired,
            pydio: React.PropTypes.instanceOf(Pydio).isRequired,
            selection: React.PropTypes.instanceOf(PydioDataModel).isRequired,
            readonly: React.PropTypes.bool
        },

        childContextTypes: {
            messages: React.PropTypes.object,
            getMessage: React.PropTypes.func
        },

        getChildContext: function getChildContext() {
            var messages = this.props.pydio.MessageHash;
            return {
                messages: messages,
                getMessage: function getMessage(messageId) {
                    var namespace = arguments.length <= 1 || arguments[1] === undefined ? 'share_center' : arguments[1];

                    try {
                        return messages[namespace + (namespace ? "." : "") + messageId] || messageId;
                    } catch (e) {
                        return messageId;
                    }
                }
            };
        },

        refreshDialogPosition: function refreshDialogPosition() {
            global.pydio.UI.modal.refreshDialogPosition();
        },

        sharesUpdated: function sharesUpdated() {
            if (this.isMounted()) {
                this.setState({
                    shares: this.state.shares
                }, (function () {
                    this.refreshDialogPosition();
                }).bind(this));
            }
        },

        getInitialState: function getInitialState() {
            return {
                shares: new ReactModel.ShareNotificationList(this.props.pydio)
            };
        },

        componentDidMount: function componentDidMount() {
            ReactDispatcher.ShareNotificationDispatcher.getClient().observe('status_changed', this.sharesUpdated);
        },

        clicked: function clicked() {
            this.props.closeAjxpDialog();
        },

        getMessage: function getMessage(key) {
            var namespace = arguments.length <= 1 || arguments[1] === undefined ? 'share_center' : arguments[1];

            return this.props.pydio.MessageHash[namespace + (namespace ? '.' : '') + key];
        },

        render: function render() {
            var shares = this.state.shares,
                pendingShares = shares && shares.getSharesByStatus(STATUS_PENDING) || [],
                answeredShares = shares && shares.getSharesByStatus(STATUS_ANSWERED) || [];

            return React.createElement(
                "div",
                { className: "left-panel" },
                React.createElement(
                    "div",
                    { className: "section-title" },
                    "Pending shares"
                ),
                React.createElement(Shares, _extends({}, this.props, { shares: pendingShares })),
                React.createElement(
                    "div",
                    { className: "section-title" },
                    "History"
                ),
                React.createElement(Shares, _extends({}, this.props, { shares: answeredShares }))
            );
        }
    });

    /*****************************
    /* Shares Collection
    /*****************************/
    var Shares = React.createClass({
        displayName: "Shares",

        propTypes: {
            shares: React.PropTypes.instanceOf(ReactModel.ShareNotificationList)
        },

        render: function render() {
            var shares = this.props.shares.map((function (u) {
                return React.createElement(Share, _extends({}, this.props, { share: u }));
            }).bind(this));

            return React.createElement(
                "div",
                { style: { padding: '0 16px 10px' } },
                shares
            );
        }
    });

    /*****************************
    /* Share
    /*****************************/
    var Share = React.createClass({
        displayName: "Share",

        propTypes: {
            share: React.PropTypes.instanceOf(ReactModel.ShareNotification)
        },

        getInitialState: function getInitialState() {
            return {
                share: this.props.share
            };
        },

        render: function render() {
            var share = this.state.share,
                owner = share.getOwner(),
                label = share.getLabel(),
                crDate = share.getFormattedDate();

            return React.createElement(
                "div",
                { style: { marginBottom: '5px' } },
                React.createElement(
                    "div",
                    { className: "pull-left", style: { width: '90%' } },
                    React.createElement(
                        "div",
                        { className: "workspace-label" },
                        label
                    ),
                    React.createElement(
                        "div",
                        { className: "workspace-link" },
                        owner,
                        " > ",
                        crDate
                    )
                ),
                React.createElement(
                    "div",
                    { className: "pull-left", style: { width: '10%' } },
                    React.createElement(Links, _extends({}, this.props, { share: share }))
                ),
                React.createElement("div", { style: { clear: 'both' } })
            );
        }
    });

    /*********************************
    /* Links Collection for a Share
    /*********************************/
    var Links = React.createClass({
        displayName: "Links",

        propTypes: {
            share: React.PropTypes.instanceOf(ReactModel.ShareNotification)
        },

        render: function render() {
            var share = this.props.share,
                actions = share && share.getActions().map((function (action) {
                return React.createElement(Link, _extends({}, this.props, { share: share, action: action }));
            }).bind(this));

            return React.createElement(
                "div",
                null,
                actions
            );
        }
    });

    /*********************************
    /* Link for a Share
    /*********************************/
    var Link = React.createClass({
        displayName: "Link",

        propTypes: {
            share: React.PropTypes.instanceOf(ReactModel.ShareNotification),
            action: React.PropTypes.object
        },

        triggerModelClick: function triggerModelClick() {
            var options = this.props.action.options,
                actionType = options.get_action;

            if (actionType == 'switch_repository') {
                this.props.pydio.triggerRepositoryChange(options.repository_id);
            } else {
                this.props.share.loadAction(options);
            }
        },

        render: function render() {
            var share = this.props.share,
                action = this.props.action,
                className = CLASS_NAMES[action.id] || "";

            return React.createElement("a", { className: className, onClick: this.triggerModelClick, title: action.message });
        }
    });

    var EventNamespace = global.ShareNotificationUI || {};
    EventNamespace.MainPanel = MainPanel;
    global.ShareNotificationUI = EventNamespace;
})(window);
