var gui_ajax_core = [
    'res/js/vendor/modernizr/modernizr.min.js',
    'res/js/core/lang/Observable.js',
    'res/js/core/lang/Logger.js',
    'res/js/core/util/LangUtils.js',
    'res/js/core/util/FuncUtils.js',
    'res/js/core/util/XMLUtils.js',
    'res/js/core/util/PathUtils.js',
    'res/js/core/util/HasherUtils.js',
    'res/js/core/util/PassUtils.js',
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
    'res/js/vendor/scriptaculous/src/slider.js',
    'res/js/vendor/prototype/cssfx.js',
    'res/js/vendor/prototype/proto.scroller.js',
    'res/js/vendor/prototype/accordion.js',
    'res/js/vendor/webfx/xtree.js',
    'res/js/vendor/webfx/ajxptree.js',
    'res/js/vendor/chosen/event.simulate.js',
    'res/js/vendor/chosen/chosen.proto.js',
    'res/js/ui/prototype/util/he.js',
    'res/js/core/model/User.js',
    'res/js/core/http/ResourcesManager.js',
    'res/js/core/model/RemoteNodeProvider.js',
    'res/js/core/model/EmptyNodeProvider.js',
    'res/js/core/model/Repository.js',
    'res/js/core/http/PydioApi.js',
    'res/js/core/http/PydioUsersApi.js',
    'res/js/core/http/MetaCacheService.js',
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
    'res/js/ui/prototype/class.UserWidget.js',
    'res/js/ui/prototype/class.LogoWidget.js',
    'res/js/ui/prototype/class.TreeSelector.js',
    'res/js/ui/prototype/class.SliderInput.js',
    'res/js/ui/prototype/class.ActionsToolbar.js',
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
    'res/js/ui/prototype/class.AjxpReactDialogLoader.js',
    'res/js/ui/prototype/class.PydioUI.js',
    'res/js/core/Pydio.js'
];
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
        copy: {
            debug: {
                expand: true,
                src: 'node_modules/he/he.js',
                dest: 'res/js/ui/prototype/util',
                flatten:true
            },
            dndpatch: {
                expand: true,
                src: 'res/js/vendor/dnd-html5-backend-patch/NativeDragSources.js',
                dest: 'node_modules/react-dnd-html5-backend/lib/',
                flatten:true
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
                    'res/js/pydio.min.js': gui_ajax_core
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
                        cwd: 'node_modules/material-ui-legacy/src/',
                        src: ['**/*.js', '**/*.jsx'],
                        dest: 'node_modules/material-ui-legacy/lib/',
                        ext: '.js'
                    }]
            },
            pydio:{
                files: [
                    {
                        expand: true,
                        cwd: 'res/js/ui/reactjs/jsx',
                        src: ['**/*.js'],
                        dest: 'res/js/ui/reactjs/build/',
                        ext: '.js'
                    }
                ]
            }
        },
        browserify: {
            dist: {
                files: {
                    'res/js/vendor/nodejs/bundle.prod.js': 'res/js/vendor/nodejs/export.js',
                    'res/js/vendor/nodejs/bundle.legacy.prod.js': 'res/js/vendor/nodejs/export.legacy.js'
                }
            },
            ui : {
                files: {
                    'res/js/ui/reactjs/build/PydioReactUI.js':'res/js/ui/reactjs/build/ReactUI/index.js',
                    'res/js/ui/reactjs/build/PydioComponents.js':'res/js/ui/reactjs/build/Components/index.js',
                    'res/js/ui/reactjs/build/PydioWorkspaces.js':'res/js/ui/reactjs/build/Workspaces/index.js'
                }
            }
        },
        less: {
            development: {
                options: {
                    plugins: [
                        new (require('less-plugin-autoprefix'))({browsers: ["last 2 versions, > 10%"]})
                    ]
                },
                files: {
                    "res/themes/orbit/css/pydio.css": "res/themes/orbit/css/pydio.less",
                    "res/themes/material/css/pydio.css": "res/themes/material/css/pydio.less"
                }
            }
        },
        watch: {
            es6 : {
                files:[
                    'res/js/es6/*.es6',
                    'res/js/es6/**/*.es6'
                ],
                tasks:['babel:dist'],
                options:{
                    spawn:false
                }
            },
            core: {
                files: gui_ajax_core,
                tasks: ['babel:dist', 'uglify:js'],
                options: {
                    spawn: false
                }
            },
            pydio:{
                files: [
                    'res/js/ui/reactjs/jsx/**/*.js'
                ],
                tasks:['babel:pydio','browserify:ui'],
                options: {
                    spawn: false
                }
            },
            styles_orbit: {
                files: ['res/themes/orbit/css/**/*.less'],
                tasks: ['less', 'cssmin'],
                options: {
                    nospawn: true
                }
            },
            styles_material: {
                files: ['res/themes/material/css/**/*.less'],
                tasks: ['less', 'cssmin'],
                options: {
                    nospawn: true
                }
            }
        },
        cssmin: {
            options: {
                shorthandCompacting: false,
                roundingPrecision: -1
            },
            target: {
                files: {
                    'res/themes/orbit/css/allz.css': [
                        'res/themes/orbit/css/pydio.css',
                        'res/themes/common/css/animate-custom.css',
                        'res/themes/common/css/chosen.css',
                        'res/themes/common/css/media.css'
                    ],
                    'res/themes/material/css/allz.css': [
                        'res/themes/material/css/pydio.css',
                        'res/themes/common/css/animate-custom.css',
                        'res/themes/common/css/chosen.css',
                        'res/themes/common/css/media.css'
                    ]
                }
            }
        },
        hub: {
            all: {
                options:{
                    concurrent: 80,
                    allowSelf:true
                },
                src: ['../*/Gruntfile.js'],
                tasks: ['default']
            }
        }
    });
    grunt.loadNpmTasks('grunt-env');
    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-run');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-hub');
    grunt.loadNpmTasks('assemble-less');
    grunt.registerTask('type:js', [
        'copy:debug',
        'babel:dist',
        'uglify:js',
        'babel:materialui',
        'babel:pydio',
        'env:dev',
        'browserify',
        'env:build',
        'uglify:nodejs'
    ]);
    grunt.registerTask('type:css', [
        'cssmin'
    ]);
    grunt.registerTask('default', [
        'type:js',
        'type:css'
    ]);
    grunt.registerTask('build-core', [
        'babel:dist',
        'uglify:js'
    ]);
};
