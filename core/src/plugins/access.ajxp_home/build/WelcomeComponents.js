"use strict";

(function (global) {

    var HomeWorkspaceLegendPanel = React.createClass({
        displayName: "HomeWorkspaceLegendPanel",

        setWorkspace: function setWorkspace(ws) {
            this.refs.legend.setWorkspace(ws);
        },
        render: function render() {
            return React.createElement(
                "div",
                { id: "home_center_panel" },
                React.createElement(
                    "div",
                    { id: "logo_div" },
                    React.createElement(ConfigLogo, { pydio: this.props.pydio, pluginName: "gui.ajax", pluginParameter: "CUSTOM_DASH_LOGO" })
                ),
                React.createElement(HomeWorkspaceLegend, {
                    ref: "legend",
                    onShowLegend: this.props.onShowLegend,
                    onHideLegend: this.props.onHideLegend,
                    onOpenLink: this.props.onOpenLink })
            );
        }
    });

    var ConfigLogo = React.createClass({
        displayName: "ConfigLogo",

        render: function render() {
            var logo = this.props.pydio.Registry.getPluginConfigs(this.props.pluginName).get(this.props.pluginParameter);
            var url;
            if (!logo) {
                logo = this.props.pydio.Registry.getDefaultImageFromParameters(this.props.pluginName, this.props.pluginParameter);
            }
            if (logo) {
                if (logo.indexOf("plugins/") === 0) {
                    url = logo;
                } else {
                    url = this.props.pydio.Parameters.get("ajxpServerAccess") + "&get_action=get_global_binary_param&binary_id=" + logo;
                }
            }
            return React.createElement("img", { src: url });
        }
    });

    var TutorialPane = React.createClass({
        displayName: "TutorialPane",

        componentDidMount: function componentDidMount() {
            $("videos_pane").select("div.tutorial_load_button").invoke("observe", "click", function (e) {
                var t = Event.findElement(e, "div.tutorial_load_button");
                try {
                    var main = t.up("div.tutorial_legend");
                    if (main.next("img")) {
                        main.insert({ after: "<iframe className=\"tutorial_video\" width=\"640\" height=\"360\" frameborder=\"0\" allowfullscreen src=\"" + main.readAttribute("data-videosrc") + "\"></iframe>" });
                        main.next("img").remove();
                    }
                } catch (e) {}
            });
        },

        closePane: function closePane() {
            React.unmountComponentAtNode(document.getElementById("tutorial_panel"));
        },

        render: function render() {
            var configs = pydio.getPluginConfigs("access.ajxp_home");
            var htmlMessage = function htmlMessage(id) {
                return { __html: MessageHash[id] };
            };
            return React.createElement(
                "div",
                { id: "videos_pane", className: "skipSibling" },
                React.createElement("div", { onClick: this.closePane, className: "icon-remove-sign" }),
                React.createElement(
                    "div",
                    { className: "tutorial_title" },
                    MessageHash["user_home.56"]
                ),
                React.createElement(
                    "div",
                    { id: "tutorial_dl_apps_pane" },
                    React.createElement(
                        "div",
                        { id: "dl_pydio_cont" },
                        React.createElement(
                            "div",
                            { id: "dl_pydio_for" },
                            MessageHash["user_home.57"]
                        ),
                        React.createElement(
                            "div",
                            { id: "dl_pydio_android" },
                            React.createElement("a", { href: configs.get("URL_APP_ANDROID"), target: "_blank", className: "icon-mobile-phone" }),
                            React.createElement("a", { href: configs.get("URL_APP_ANDROID"), target: "_blank", className: "icon-android" }),
                            React.createElement(
                                "div",
                                null,
                                MessageHash["user_home.58"]
                            )
                        ),
                        React.createElement(
                            "div",
                            { id: "dl_pydio_ios" },
                            React.createElement("a", { href: configs.get("URL_APP_IOSAPPSTORE"), target: "_blank", className: "icon-tablet" }),
                            React.createElement("a", { href: configs.get("URL_APP_IOSAPPSTORE"), target: "_blank", className: "icon-apple" }),
                            React.createElement(
                                "div",
                                null,
                                MessageHash["user_home.59"]
                            )
                        ),
                        React.createElement(
                            "div",
                            { id: "dl_pydio_mac" },
                            React.createElement("a", { href: configs.get("URL_APP_SYNC_MAC"), target: "_blank", className: "icon-desktop" }),
                            React.createElement("a", { href: configs.get("URL_APP_SYNC_MAC"), target: "_blank", className: "icon-apple" }),
                            React.createElement(
                                "div",
                                null,
                                MessageHash["user_home.60"]
                            )
                        ),
                        React.createElement(
                            "div",
                            { id: "dl_pydio_win" },
                            React.createElement("a", { href: configs.get("URL_APP_SYNC_WIN"), target: "_blank", className: "icon-laptop" }),
                            React.createElement("a", { href: configs.get("URL_APP_SYNC_WIN"), target: "_blank", className: "icon-windows" }),
                            React.createElement(
                                "div",
                                null,
                                MessageHash["user_home.61"]
                            )
                        )
                    )
                ),
                React.createElement(
                    "div",
                    { className: "tutorial_legend", "data-videosrc": "//www.youtube.com/embed/80kq-T6bQO4?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5" },
                    React.createElement("span", { dangerouslySetInnerHTML: htmlMessage("user_home.62") }),
                    React.createElement(
                        "div",
                        { className: "tutorial_load_button" },
                        React.createElement("i", { className: "icon-youtube-play" }),
                        " Play Video"
                    )
                ),
                React.createElement("img", { className: "tutorial_video", src: "https://img.youtube.com/vi/80kq-T6bQO4/0.jpg" }),
                React.createElement(
                    "div",
                    { className: "tutorial_legend", "data-videosrc": "//www.youtube.com/embed/ZuVKsIa4XdU?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5" },
                    React.createElement("div", { dangerouslySetInnerHTML: htmlMessage("user_home.63") }),
                    React.createElement(
                        "div",
                        { className: "tutorial_load_button" },
                        React.createElement("i", { className: "icon-youtube-play" }),
                        " Play Video"
                    )
                ),
                React.createElement("img", { className: "tutorial_video", src: "https://img.youtube.com/vi/ZuVKsIa4XdU/0.jpg" }),
                React.createElement(
                    "div",
                    { className: "tutorial_legend", "data-videosrc": "//www.youtube.com/embed/MEHCN64RoTY?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5" },
                    React.createElement("div", { dangerouslySetInnerHTML: htmlMessage("user_home.64") }),
                    React.createElement(
                        "div",
                        { className: "tutorial_load_button" },
                        React.createElement("i", { className: "icon-youtube-play" }),
                        " Play Video"
                    )
                ),
                React.createElement("img", { className: "tutorial_video", src: "https://img.youtube.com/vi/MEHCN64RoTY/0.jpg" }),
                React.createElement(
                    "div",
                    { className: "tutorial_legend", "data-videosrc": "//www.youtube.com/embed/ot2Nq-RAnYE?list=PLxzQJCqzktEYnIChsR5h3idjAxgBssnt5" },
                    React.createElement("div", { dangerouslySetInnerHTML: htmlMessage("user_home.66") }),
                    React.createElement(
                        "div",
                        { className: "tutorial_load_button" },
                        React.createElement("i", { className: "icon-youtube-play" }),
                        " Play Video"
                    )
                ),
                React.createElement("img", { className: "tutorial_video", src: "https://img.youtube.com/vi/ot2Nq-RAnYE/0.jpg" }),
                React.createElement(
                    "div",
                    { className: "tutorial_more_videos_cont" },
                    React.createElement(
                        "a",
                        { className: "tutorial_more_videos_button", href: "http://pyd.io/end-user-tutorials/", target: "_blank" },
                        React.createElement("i", { className: "icon-youtube-play" }),
                        React.createElement("span", { dangerouslySetInnerHTML: htmlMessage("user_home.65") })
                    )
                )
            );

            //<div dangerouslySetInnerHTML={content()}></div>
        }

    });

    var HomeWorkspaceUserCartridge = React.createClass({
        displayName: "HomeWorkspaceUserCartridge",

        clickDisconnect: function clickDisconnect() {
            this.props.controller.fireAction("logout");
        },

        clickConnect: function clickConnect() {
            this.props.controller.fireAction("login");
        },

        showGettingStarted: function showGettingStarted() {
            this.props.controller.fireAction("open_tutorial_pane");
        },

        render: function render() {
            var userLabel = this.props.user.getPreference("USER_DISPLAY_NAME") || this.props.user.id;
            var loginLink = "";
            if (this.props.controller.getActionByName("logout") && this.props.user.id != "guest") {
                var parts = MessageHash["user_home.67"].replace("%s", userLabel).split("%logout");
                loginLink = React.createElement(
                    "small",
                    null,
                    parts[0],
                    React.createElement(
                        "span",
                        { id: "disconnect_link", onClick: this.clickDisconnect },
                        React.createElement(
                            "a",
                            null,
                            this.props.controller.getActionByName("logout").options.text.toLowerCase()
                        )
                    ),
                    parts[1]
                );
            } else if (this.props.user.id == "guest" && this.props.controller.getActionByName("login")) {
                loginLink = React.createElement(
                    "small",
                    null,
                    "You can ",
                    React.createElement(
                        "a",
                        { id: "disconnect_link", onClick: this.clickConnect },
                        "login"
                    ),
                    " if you are not guest."
                );
            }

            var gettingStartedBlock = "";
            if (this.props.enableGettingStarted) {
                gettingStartedBlock = React.createElement(
                    "small",
                    null,
                    React.createElement(
                        "span",
                        { onClick: this.showGettingStarted },
                        MessageHash["user_home.55"].replace("<a>", "").replace("</a>", "")
                    )
                );
            }

            return React.createElement(
                "div",
                { id: "welcome" },
                MessageHash["user_home.40"].replace("%s", userLabel),
                loginLink,
                gettingStartedBlock
            );
        }

    });

    var HomeWorkspacesList = React.createClass({
        displayName: "HomeWorkspacesList",

        render: function render() {
            var workspacesNodes = [];
            var sharedNodes = [];
            this.props.workspaces.forEach((function (v) {
                if (v.getAccessType().startsWith("ajxp_")) return;
                var node = React.createElement(HomeWorkspaceItem, { ws: v,
                    key: v.getId(),
                    onHoverLink: this.props.onHoverLink,
                    onOutLink: this.props.onOutLink,
                    onOpenLink: this.props.onOpenLink,
                    openOnDoubleClick: this.props.openOnDoubleClick
                });
                if (v.owner !== "") {
                    sharedNodes.push(node);
                } else {
                    workspacesNodes.push(node);
                }
            }).bind(this));
            var titleNode = workspacesNodes.length ? React.createElement(
                "li",
                { className: "ws_selector_title" },
                React.createElement(
                    "h3",
                    null,
                    MessageHash[468]
                )
            ) : "";
            var titleSharedNode = sharedNodes.length ? React.createElement(
                "li",
                { className: "ws_selector_title" },
                React.createElement(
                    "h3",
                    null,
                    MessageHash[469]
                )
            ) : "";
            return React.createElement(
                "ul",
                { id: "workspaces_list" },
                titleNode,
                workspacesNodes,
                titleSharedNode,
                sharedNodes
            );
        }
    });

    var HomeWorkspaceItem = React.createClass({
        displayName: "HomeWorkspaceItem",

        onHoverLink: function onHoverLink(event) {
            this.props.onHoverLink(event, this.props.ws);
        },
        onClickLink: function onClickLink(event) {
            if (!this.props.openOnDoubleClick) {
                this.props.onOpenLink(event, this.props.ws);
            }
        },
        onDoubleClickLink: function onDoubleClickLink(event) {
            if (this.props.openOnDoubleClick) {
                this.props.onOpenLink(event, this.props.ws);
            }
        },
        render: function render() {
            var letters = this.props.ws.getLabel().split(" ").map(function (word) {
                return word.substr(0, 1);
            }).join("");
            return React.createElement(
                "li",
                { onMouseOver: this.onHoverLink, onMouseOut: this.props.onOutLink, onTouchTap: this.onClickLink, onClick: this.onClickLink, onDoubleClick: this.onDoubleClickLink },
                React.createElement(
                    "span",
                    { className: "letter_badge" },
                    letters
                ),
                React.createElement(
                    "h3",
                    null,
                    this.props.ws.getLabel()
                ),
                React.createElement(
                    "h4",
                    null,
                    this.props.ws.getDescription()
                )
            );
        }
    });

    var HomeWorkspaceLegend = React.createClass({
        displayName: "HomeWorkspaceLegend",

        getInitialState: function getInitialState() {
            return { workspace: null };
        },
        enterWorkspace: function enterWorkspace(event) {
            this.props.onOpenLink(event, this.state.workspace, this.refs.save_ws_choice.getDOMNode().checked);
        },
        componentWillUnmount: function componentWillUnmount() {
            if (window["homeWorkspaceTimer"]) {
                window.clearTimeout(window["homeWorkspaceTimer"]);
            }
        },
        setWorkspace: function setWorkspace(ws) {
            if (!this._internalCache) {
                this._internalCache = new Map();
                this._repoInfosLoading = new Map();
            }
            this._internalState = ws;
            if (!ws) {
                bufferCallback("homeWorkspaceTimer", 7000, (function () {
                    this.setState({ workspace: null });
                    this.props.onHideLegend();
                }).bind(this));
                return;
            }
            // check the cache and re-render?
            var repoId = ws.getId();
            if (!this._repoInfosLoading.get(repoId) && !this._internalCache.get(repoId)) {
                this.props.onShowLegend(ws);
                this._repoInfosLoading.set(repoId, "loading");
                PydioApi.getClient().request({
                    get_action: "load_repository_info",
                    tmp_repository_id: repoId,
                    collect: "true"
                }, (function (transport) {
                    this._repoInfosLoading["delete"](repoId);
                    if (transport.responseJSON) {
                        var data = transport.responseJSON;
                        this._internalCache.set(repoId, data);
                        if (this._internalState == ws) {
                            this.setState({ workspace: ws, data: data });
                        }
                    }
                }).bind(this));
            } else if (this._internalCache.get(repoId)) {
                this.props.onShowLegend(ws);
                this.setState({ workspace: ws, data: this._internalCache.get(repoId) });
            }
        },
        render: function render() {
            if (!this.state.workspace) {
                return React.createElement("div", { id: "ws_legend", className: "empty_ws_legend" });
            }
            var blocks = [];
            var data = this.state.data;
            if (data["core.users"] && data["core.users"]["internal"] != undefined && data["core.users"]["external"] != undefined) {
                blocks.push(React.createElement(
                    HomeWorkspaceLegendInfoBlock,
                    { key: "core.users", badgeTitle: MessageHash[527], iconClass: "icon-group" },
                    MessageHash[531],
                    " ",
                    data["core.users"]["internal"],
                    React.createElement("br", null),
                    MessageHash[532],
                    " ",
                    data["core.users"]["external"]
                ));
            }
            if (data["meta.quota"]) {
                blocks.push(React.createElement(
                    HomeWorkspaceLegendInfoBlock,
                    { key: "meta.quota", badgeTitle: MessageHash["meta.quota.4"], iconClass: "icon-dashboard" },
                    parseInt(100 * data["meta.quota"]["usage"] / data["meta.quota"]["total"]),
                    "%",
                    React.createElement("br", null),
                    React.createElement(
                        "small",
                        null,
                        roundSize(data["meta.quota"]["total"], MessageHash["byte_unit_symbol"])
                    )
                ));
            }
            if (data["core.notifications"] && data["core.notifications"][0]) {
                blocks.push(React.createElement(
                    HomeWorkspaceLegendInfoBlock,
                    { key: "notifications", badgeTitle: MessageHash[4], iconClass: "icon-calendar" },
                    data["core.notifications"][0]["short_date"]
                ));
            }

            return React.createElement(
                "div",
                { id: "ws_legend" },
                this.state.workspace.getLabel(),
                React.createElement(
                    "small",
                    null,
                    this.state.workspace.getDescription()
                ),
                React.createElement(
                    "div",
                    { className: "repoInfo" },
                    blocks
                ),
                React.createElement(
                    "div",
                    { style: { lineHeight: "0.5em" } },
                    React.createElement("input", { type: "checkbox", ref: "save_ws_choice", id: "save_ws_choice" }),
                    React.createElement(
                        "label",
                        { htmlFor: "save_ws_choice" },
                        MessageHash["user_home.41"]
                    ),
                    React.createElement(
                        "a",
                        { onClick: this.enterWorkspace },
                        MessageHash["user_home.42"]
                    )
                )
            );
        }
    });

    var HomeWorkspaceLegendInfoBlock = React.createClass({
        displayName: "HomeWorkspaceLegendInfoBlock",

        render: function render() {
            return React.createElement(
                "div",
                { className: "repoInfoBadge" },
                React.createElement(
                    "div",
                    { className: "repoInfoTitle" },
                    this.props.badgeTitle
                ),
                React.createElement("span", { className: this.props.iconClass }),
                this.props.children
            );
        }
    });

    var UserDashboard = React.createClass({
        displayName: "UserDashboard",

        switchToWorkspace: function switchToWorkspace(repoId, save) {
            if (!repoId) return;
            if (save) {
                PydioApi.getClient().request({
                    "PREFERENCES_DEFAULT_START_REPOSITORY": repoId,
                    "get_action": "custom_data_edit"
                }, (function () {
                    this.props.pydio.user.setPreference("DEFAULT_START_REPOSITORY", repoId, false);
                }).bind(this));
            }
            this.props.pydio.triggerRepositoryChange(repoId);
        },
        onShowLegend: function onShowLegend() {
            // PROTO STUFF!
            $("home_center_panel").addClassName("legend_visible");
        },
        onHideLegend: function onHideLegend() {
            // PROTO STUFF!
            $("home_center_panel").removeClassName("legend_visible");
        },
        onHoverLink: function onHoverLink(event, ws) {
            this.refs.legend.setWorkspace(ws);
        },
        onOutLink: function onOutLink(event, ws) {
            this.refs.legend.setWorkspace(null);
        },
        onOpenLink: function onOpenLink(event, ws, save) {
            this.switchToWorkspace(ws.getId(), save);
        },
        render: function render() {
            var simpleClickOpen = this.props.pydio.getPluginConfigs("access.ajxp_home").get("SIMPLE_CLICK_WS_OPEN");
            var enableGettingStarted = this.props.pydio.getPluginConfigs("access.ajxp_home").get("ENABLE_GETTING_STARTED");
            return React.createElement(
                "div",
                { className: "horizontal_layout vertical_fit" },
                React.createElement(
                    "div",
                    { id: "home_left_bar", className: "vertical_layout" },
                    React.createElement(HomeWorkspaceUserCartridge, { style: { minHeight: "94px" },
                        controller: this.props.pydio.getController(),
                        user: this.props.pydio.user,
                        enableGettingStarted: enableGettingStarted
                    }),
                    React.createElement(
                        "div",
                        { id: "workspaces_center", className: "vertical_layout vertical_fit" },
                        React.createElement(HomeWorkspacesList, { className: "vertical_layout vertical_fit",
                            workspaces: this.props.pydio.user.repositories,
                            active: this.props.pydio.user.active,
                            openOnDoubleClick: !simpleClickOpen,
                            onHoverLink: this.onHoverLink,
                            onOutLink: this.onOutLink,
                            onOpenLink: this.onOpenLink
                        })
                    )
                ),
                React.createElement(HomeWorkspaceLegendPanel, { ref: "legend",
                    pydio: this.props.pydio,
                    onShowLegend: this.onShowLegend,
                    onHideLegend: this.onHideLegend,
                    onOpenLink: this.onOpenLink
                }),
                this.props.children
            );
        }

    });

    var WelcomeComponents = global.WelcomeComponents || {};
    WelcomeComponents.UserDashboard = UserDashboard;
    WelcomeComponents.TutorialPane = TutorialPane;
    global.WelcomeComponents = WelcomeComponents;
})(window);