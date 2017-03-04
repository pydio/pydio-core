var gui_ajax_boot = [
    'res/js/vendor/es6/browser-polyfill.js',
    'res/js/vendor/nodejs/boot.prod.js',
    'res/js/core/http/Connexion.js',
    'res/js/core/PydioBootstrap.js'
];

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
    'res/js/core/util/DOMUtils.js',
    'res/js/core/util/CookiesManager.js',
    'res/js/core/util/PeriodicalExecuter.js',
    'res/js/core/model/Router.js',
    'res/js/core/model/AjxpNode.js',
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
                    'res/js/pydio.min.js': gui_ajax_core,
                    'res/js/pydio.boot.min.js': gui_ajax_boot
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
                    'res/js/vendor/nodejs/bundle.legacy.prod.js': 'res/js/vendor/nodejs/export.legacy.js',
                    'res/js/vendor/nodejs/boot.prod.js': 'res/js/vendor/nodejs/boot.js'
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
            },
            manifests: {
                files: ['../*/manifest.xml'],
                tasks: ['clean:cache']
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
        clean: {
            options: {
                force: true
            },
            cache: ['../../data/cache/plugins_*.*']
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
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.registerTask('type:js', [
        'babel:dist',
        'uglify:js',
        'babel:materialui',
        'babel:pydio',
//        'env:build',
        'browserify',
        'env:dev',
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
