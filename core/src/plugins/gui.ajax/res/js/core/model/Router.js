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
