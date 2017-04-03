module.exports = function(grunt, options){

    options.libName = grunt.option('libName');

    return {
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
        },
        lib:{
            files: {
                'res/js/ui/reactjs/build/Pydio<%= libName%>.min.js':'res/js/ui/reactjs/build/Pydio<%= libName%>.js',
            }
        }
    };
};