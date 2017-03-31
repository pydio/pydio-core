module.exports = function(grunt) {

    const {PydioCoreRequires,LibRequires,Externals} = require('./res/js/dist.js');

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
            },
            mfb: {
                expand: true,
                src: 'node_modules/react-mfb/mfb.css',
                dest: 'res/mui/',
                flatten:true
            }
        },
        rename: {
            mfb: {
                files: [
                    {src: ['res/mui/mfb.css'], dest: 'res/mui/mfb.less'},
                ]
            }
        },
        symlink: {
            options: {
                overwrite: false,
                force: true
            },
            expanded: {
                files : [
                    {
                        expand: true,
                        overwrite: false,
                        cwd: 'node_modules/material-ui-legacy/src',
                        src: ['less'],
                        dest : 'res/mui/'
                    }
                ]
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
            boot: {
                options:{
                    alias:[
                        './res/js/core/http/Connexion.js:pydio/http/connexion',
                        './res/js/core/PydioBootstrap.js:pydio-bootstrap'
                    ]
                },
                files: {
                    'res/js/vendor/nodejs/boot.prod.js': 'res/js/vendor/nodejs/boot.js',
                }
            },
            core: {
                options:{
                    alias: Object.keys(PydioCoreRequires).map(function(key){
                        return './res/js/core/' + key + ':' + PydioCoreRequires[key];
                    })
                },
                files: {
                    'res/js/core/PydioCore.js': 'res/js/core/index.js',
                }
            },
            dist: {
                options: {
                    alias: LibRequires.map(k => k + ':')
                },
                files: {
                    'res/js/vendor/nodejs/bundle.prod.js': 'res/js/vendor/nodejs/export.js',
                    'res/js/vendor/nodejs/bundle.legacy.prod.js': 'res/js/vendor/nodejs/export.legacy.js'
                }
            },
            ui : {
                options: {
                    browserifyOptions: {
                        debug: true
                    },
                    external:Externals
                },
                files: {
                    'res/js/ui/reactjs/build/PydioReactUI.js':'res/js/ui/reactjs/build/ReactUI/index.js',
                    'res/js/ui/reactjs/build/PydioComponents.js':'res/js/ui/reactjs/build/Components/index.js',
                    'res/js/ui/reactjs/build/PydioHOCs.js':'res/js/ui/reactjs/build/HighOrderComponents/index.js',
                    'res/js/ui/reactjs/build/PydioWorkspaces.js':'res/js/ui/reactjs/build/Workspaces/index.js'
                }
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
                        'res/js/core/PydioCore.js'
                    ],
                    'res/js/pydio.boot.min.js': [
                        'res/js/vendor/es6/browser-polyfill.js',
                        'res/js/vendor/nodejs/boot.prod.js'
                    ]
                }
            },
            nodejs: {
                files: {
                    'res/js/vendor/nodejs/bundle.prod.min.js': ['res/js/vendor/nodejs/bundle.prod.js'],
                    'res/js/vendor/nodejs/bundle.legacy.min.js': ['res/js/vendor/nodejs/bundle.legacy.prod.js']
                }
            },
            ui:{
                files: {
                    'res/js/ui/reactjs/build/PydioReactUI.min.js':'res/js/ui/reactjs/build/PydioReactUI.js',
                    'res/js/ui/reactjs/build/PydioComponents.min.js':'res/js/ui/reactjs/build/PydioComponents.js',
                    'res/js/ui/reactjs/build/PydioHOCs.min.js':'res/js/ui/reactjs/build/PydioHOCs.js',
                    'res/js/ui/reactjs/build/PydioWorkspaces.min.js':'res/js/ui/reactjs/build/PydioWorkspaces.js',
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
                files: Object.keys(PydioCoreRequires).map(key => 'res/js/core/' + key),
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
            styles_material: {
                files: ['res/themes/material/css/**/*.less', 'res/themes/common/css/**/*.less'],
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
    grunt.loadNpmTasks('grunt-contrib-symlink');
    grunt.loadNpmTasks('grunt-contrib-rename');
    grunt.loadNpmTasks('grunt-run');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-hub');
    grunt.loadNpmTasks('assemble-less');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.registerTask('type:js', [
        'copy',
        'rename',
        'symlink',
        'env:build',
        'browserify:boot',
        'browserify:core',
        'env:dev',
        'babel:dist',
        'uglify:js',
        'babel:materialui',
        'babel:pydio',
        'env:build',
        'browserify:dist',
        'browserify:ui',
        'env:dev',
        'uglify:nodejs',
        'uglify:ui'
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
