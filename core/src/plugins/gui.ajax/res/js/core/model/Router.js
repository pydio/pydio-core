/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var Router = function Router(pydioObject) {
    _classCallCheck(this, Router);

    if (!window.Backbone || !window.Backbone.Router) return;

    var WorkspaceRouter = Backbone.Router.extend({
        routes: {
            ":workspace/*path": "switchToWorkspace"
        },
        switchToWorkspace: function switchToWorkspace(workspace, path) {
            if (!pydioObject.user) {
                return;
            }
            if (path) path = '/' + path;else path = '/';
            var repos = pydioObject.user.getRepositoriesList();
            workspace = workspace.replace("ws-", "");

            var object;
            repos.forEach(function (value) {
                if (value.getSlug() == workspace) {
                    object = value;
                }
            });
            if (!object) return;

            if (pydioObject.repositoryId != object.getId()) {
                if (path) {
                    pydioObject._initLoadRep = path;
                }
                //hideLightBox();
                pydioObject.triggerRepositoryChange(object.getId());
            } else if (path) {
                window.setTimeout(function () {
                    //hideLightBox();
                    pydioObject.goTo(path);
                }, 100);
            }
            pydio.notify("routechange", { workspace: workspace, path: path });
        }

    });

    this.router = new WorkspaceRouter();
    var appRoot = pydioObject.Parameters.get('APPLICATION_ROOT');
    if (appRoot && appRoot != "/") {
        Backbone.history.start({
            pushState: true,
            root: appRoot
        });
    } else {
        Backbone.history.start({
            pushState: true
        });
    }
    var navigate = (function (repList, repId) {
        if (repId === false) {
            this.router.navigate("/.");
        } else {
            var repositoryObject = repList.get(repId);
            if (repositoryObject) {
                var slug = repositoryObject.getSlug();
                if (!repositoryObject.getAccessType().startsWith("ajxp_")) {
                    slug = "ws-" + slug;
                }
                if (pydioObject.getContextNode()) {
                    slug += pydioObject.getContextNode().getPath();
                }
                this.router.navigate(slug);
            }
        }
    }).bind(this);

    if (pydioObject.user) {
        navigate(pydioObject.user.getRepositoriesList(), pydioObject.user.getActiveRepository());
    }
    pydioObject.observe("repository_list_refreshed", function (event) {
        var repList = event.list;
        var repId = event.active;
        navigate(repList, repId);
    });
    pydioObject.getContextHolder().observe("context_changed", (function (event) {
        if (!pydioObject.user) return;
        var repoList = pydioObject.user.getRepositoriesList();
        var activeRepo = repoList.get(pydioObject.user.getActiveRepository());
        if (activeRepo) {
            var slug = activeRepo.getSlug();
            if (!activeRepo.getAccessType().startsWith("ajxp_")) {
                slug = "ws-" + slug;
            }
            var path = pydioObject.getContextNode().getPath();
            this.router.navigate(slug + path);
        }
    }).bind(this));
};
