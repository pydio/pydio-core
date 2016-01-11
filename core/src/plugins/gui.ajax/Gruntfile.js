module.exports = function(grunt) {
    grunt.initConfig({
        env: {
            build: {
                NODE_ENV: 'production',
                DEST: 'dist'
            },
            dev: {
                NODE_ENV: 'development',
                DEST: 'tmp'
            }
        },
        uglify: {
            options: {
                mangle: false,
                compress: {
                    hoist_funs: false
                }
            },
            js: {
                files: {
                    'res/js/pydio.min.js': [
                        'res/js/vendor/modernizr/modernizr.min.js',
                        'res/js/core/lang/Observable.js',
                        'res/js/core/lang/Logger.js',
                        'res/js/core/util/LangUtils.js',
                        'res/js/core/util/XMLUtils.js',
                        'res/js/core/util/PathUtils.js',
                        'res/js/core/util/HasherUtils.js',
                        'res/js/core/util/CookiesManager.js',
                        'res/js/core/model/Router.js',
                        'res/js/core/model/AjxpNode.js',
                        'res/js/ui/prototype/util/ajxp_utils.js',
                        'res/js/ui/prototype/interfaces/class.IAjxpNodeProvider.js',
                        'res/js/ui/prototype/interfaces/class.IAjxpWidget.js',
                        'res/js/ui/prototype/interfaces/class.IActionProvider.js',
                        'res/js/ui/prototype/interfaces/class.IFocusable.js',
                        'res/js/ui/prototype/interfaces/class.IContextMenuable.js',
                        'res/js/ui/prototype/class.AjxpPane.js',
                        'res/js/vendor/prototype/webfx.selectable.js',
                        'res/js/vendor/prototype/webfx.sortable.js',
                        'res/js/vendor/prototype/proto.menu.js',
                        'res/js/vendor/prototype/splitter.js',
                        'res/js/vendor/prototype/cookiejar.js',
                        'res/js/vendor/prototype/protopass.js',
                        'res/js/vendor/prototype/resizable.js',
                        'res/js/vendor/prototype/es6compat.js',
                        'res/js/vendor/leightbox/lightbox.js',
                        'res/js/vendor/scriptaculous/src/builder.js',
                        'res/js/vendor/scriptaculous/src/effects.js',
                        'res/js/vendor/scriptaculous/src/dragdrop.js',
                        'res/js/vendor/scriptaculous/src/controls.js',
                        'res/js/vendor/scriptaculous/src/slider.js',
                        'res/js/vendor/prototype/cssfx.js',
                        'res/js/vendor/prototype/proto.scroller.js',
                        'res/js/vendor/prototype/carousel.js',
                        'res/js/vendor/prototype/accordion.js',
                        'res/js/vendor/webfx/xtree.js',
                        'res/js/vendor/webfx/ajxptree.js',
                        'res/js/vendor/chosen/event.simulate.js',
                        'res/js/vendor/chosen/chosen.proto.js',
                        'res/js/core/model/User.js',
                        'res/js/core/http/ResourcesManager.js',
                        'res/js/core/model/RemoteNodeProvider.js',
                        'res/js/core/model/EmptyNodeProvider.js',
                        'res/js/core/model/Repository.js',
                        'res/js/core/model/BackgroundTasksManager.js',
                        'res/js/core/http/PydioApi.js',
                        'res/js/core/model/Action.js',
                        'res/js/core/model/Controller.js',
                        'res/js/core/model/PydioDataModel.js',
                        'res/js/core/model/Registry.js',
                        'res/js/ui/prototype/class.AjxpDraggable.js',
                        'res/js/ui/prototype/class.AjxpSortable.js',
                        'res/js/ui/prototype/class.AjxpTabulator.js',
                        'res/js/ui/prototype/class.VisibilityToggler.js',
                        'res/js/ui/prototype/class.AjxpSimpleTabs.js',
                        'res/js/ui/prototype/class.RepositorySelect.js',
                        'res/js/ui/prototype/class.RepositorySimpleLabel.js',
                        'res/js/ui/prototype/class.Breadcrumb.js',
                        'res/js/ui/prototype/class.LocationBar.js',
                        'res/js/ui/prototype/class.UserWidget.js',
                        'res/js/ui/prototype/class.LogoWidget.js',
                        'res/js/ui/prototype/class.AjxpAutoCompleter.js',
                        'res/js/ui/prototype/class.AjxpUsersCompleter.js',
                        'res/js/ui/prototype/class.TreeSelector.js',
                        'res/js/ui/prototype/class.SliderInput.js',
                        'res/js/ui/prototype/class.ActionsToolbar.js',
                        'res/js/ui/prototype/class.BackgroundManagerPane.js',
                        'res/js/ui/prototype/class.HeaderResizer.js',
                        'res/js/ui/prototype/class.PreviewFactory.js',
                        'res/js/ui/prototype/class.FilesList.js',
                        'res/js/ui/prototype/class.FoldersTree.js',
                        'res/js/ui/prototype/class.SearchEngine.js',
                        'res/js/ui/prototype/class.FetchedResultPane.js',
                        'res/js/ui/prototype/class.InfoPanel.js',
                        'res/js/ui/prototype/class.PropertyPanel.js',
                        'res/js/ui/prototype/class.AbstractEditor.js',
                        'res/js/ui/prototype/class.Modal.js',
                        'res/js/ui/prototype/class.BookmarksBar.js',
                        'res/js/ui/prototype/class.FormManager.js',
                        'res/js/ui/prototype/class.DataModelProperty.js',
                        'res/js/ui/prototype/class.MultiDownloader.js',
                        'res/js/ui/prototype/class.ActivityMonitor.js',
                        'res/js/ui/prototype/class.AjxpReactComponent.js',
                        'res/js/ui/prototype/class.PydioUI.js',
                        'res/js/core/Pydio.js'
                    ]
                }
            },
            nodejs: {
                files: {
                    'res/js/vendor/nodejs/bundle.prod.min.js': ['res/js/vendor/nodejs/bundle.prod.js'],
                    'res/js/vendor/nodejs/bundle.legacy.min.js': ['res/js/vendor/nodejs/bundle.legacy.prod.js']
                }
            }
        },
        babel: {
            options: {
                loose: 'all'
            },
            dist: {
                files: [
                    {
                        mode: {loose: true},
                        expand: true,
                        cwd: 'res/js/es6/',
                        src: ['**/*.es6'],
                        dest: 'res/js/core/',
                        ext: '.js'
                    }
                ]
            },
            materialui: {
                files: [
                    {
                        mode: {loose: false},
                        expand: true,
                        cwd: 'node_modules/material-ui/src/',
                        src: ['**/*.js', '**/*.jsx'],
                        dest: 'node_modules/material-ui/lib/',
                        ext: '.js'
                    }]
            }
        },
        browserify: {
            dist: {
                files: {
                    'res/js/vendor/nodejs/bundle.prod.js': 'res/js/vendor/nodejs/export.js',
                    'res/js/vendor/nodejs/bundle.legacy.prod.js': 'res/js/vendor/nodejs/export.legacy.js'
                }
            }
        },
        watch: {
            js: {
                files: [
                    'res/js/vendor/modernizr/modernizr.min.js',
                    'res/js/core/lang/Observable.js',
                    'res/js/core/lang/Logger.js',
                    'res/js/core/util/LangUtils.js',
                    'res/js/core/util/XMLUtils.js',
                    'res/js/core/util/PathUtils.js',
                    'res/js/core/util/HasherUtils.js',
                    'res/js/core/util/CookiesManager.js',
                    'res/js/core/model/Router.js',
                    'res/js/core/model/AjxpNode.js',
                    'res/js/ui/prototype/util/ajxp_utils.js',
                    'res/js/ui/prototype/interfaces/class.IAjxpNodeProvider.js',
                    'res/js/ui/prototype/interfaces/class.IAjxpWidget.js',
                    'res/js/ui/prototype/interfaces/class.IActionProvider.js',
                    'res/js/ui/prototype/interfaces/class.IFocusable.js',
                    'res/js/ui/prototype/interfaces/class.IContextMenuable.js',
                    'res/js/ui/prototype/class.AjxpPane.js',
                    'res/js/vendor/prototype/webfx.selectable.js',
                    'res/js/vendor/prototype/webfx.sortable.js',
                    'res/js/vendor/prototype/proto.menu.js',
                    'res/js/vendor/prototype/splitter.js',
                    'res/js/vendor/prototype/cookiejar.js',
                    'res/js/vendor/prototype/protopass.js',
                    'res/js/vendor/prototype/resizable.js',
                    'res/js/vendor/prototype/es6compat.js',
                    'res/js/vendor/leightbox/lightbox.js',
                    'res/js/vendor/scriptaculous/src/builder.js',
                    'res/js/vendor/scriptaculous/src/effects.js',
                    'res/js/vendor/scriptaculous/src/dragdrop.js',
                    'res/js/vendor/scriptaculous/src/controls.js',
                    'res/js/vendor/scriptaculous/src/slider.js',
                    'res/js/vendor/prototype/cssfx.js',
                    'res/js/vendor/prototype/proto.scroller.js',
                    'res/js/vendor/prototype/carousel.js',
                    'res/js/vendor/prototype/accordion.js',
                    'res/js/vendor/webfx/xtree.js',
                    'res/js/vendor/webfx/ajxptree.js',
                    'res/js/vendor/chosen/event.simulate.js',
                    'res/js/vendor/chosen/chosen.proto.js',
                    'res/js/core/model/User.js',
                    'res/js/core/http/ResourcesManager.js',
                    'res/js/core/model/RemoteNodeProvider.js',
                    'res/js/core/model/EmptyNodeProvider.js',
                    'res/js/core/model/Repository.js',
                    'res/js/core/model/BackgroundTasksManager.js',
                    'res/js/core/http/PydioApi.js',
                    'res/js/core/model/Action.js',
                    'res/js/core/model/Controller.js',
                    'res/js/core/model/PydioDataModel.js',
                    'res/js/core/model/Registry.js',
                    'res/js/ui/prototype/class.AjxpDraggable.js',
                    'res/js/ui/prototype/class.AjxpSortable.js',
                    'res/js/ui/prototype/class.AjxpTabulator.js',
                    'res/js/ui/prototype/class.VisibilityToggler.js',
                    'res/js/ui/prototype/class.AjxpSimpleTabs.js',
                    'res/js/ui/prototype/class.RepositorySelect.js',
                    'res/js/ui/prototype/class.RepositorySimpleLabel.js',
                    'res/js/ui/prototype/class.Breadcrumb.js',
                    'res/js/ui/prototype/class.LocationBar.js',
                    'res/js/ui/prototype/class.UserWidget.js',
                    'res/js/ui/prototype/class.LogoWidget.js',
                    'res/js/ui/prototype/class.AjxpAutoCompleter.js',
                    'res/js/ui/prototype/class.AjxpUsersCompleter.js',
                    'res/js/ui/prototype/class.TreeSelector.js',
                    'res/js/ui/prototype/class.SliderInput.js',
                    'res/js/ui/prototype/class.ActionsToolbar.js',
                    'res/js/ui/prototype/class.BackgroundManagerPane.js',
                    'res/js/ui/prototype/class.HeaderResizer.js',
                    'res/js/ui/prototype/class.PreviewFactory.js',
                    'res/js/ui/prototype/class.FilesList.js',
                    'res/js/ui/prototype/class.FoldersTree.js',
                    'res/js/ui/prototype/class.SearchEngine.js',
                    'res/js/ui/prototype/class.FetchedResultPane.js',
                    'res/js/ui/prototype/class.InfoPanel.js',
                    'res/js/ui/prototype/class.PropertyPanel.js',
                    'res/js/ui/prototype/class.AbstractEditor.js',
                    'res/js/ui/prototype/class.Modal.js',
                    'res/js/ui/prototype/class.BookmarksBar.js',
                    'res/js/ui/prototype/class.FormManager.js',
                    'res/js/ui/prototype/class.DataModelProperty.js',
                    'res/js/ui/prototype/class.MultiDownloader.js',
                    'res/js/ui/prototype/class.ActivityMonitor.js',
                    'res/js/ui/prototype/class.AjxpReactComponent.js',
                    'res/js/ui/prototype/class.PydioUI.js',
                    'res/js/core/Pydio.js'
                ],
                tasks: ['uglify'],
                options: {
                    spawn: false
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-env');
    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-run');
    grunt.registerTask('default', [
        'babel:dist',
        'uglify:js',
        'babel:materialui',
//    'run:materialui',
        'env:build',
        'browserify',
        'env:dev',
        'uglify:nodejs'
    ]);
};
