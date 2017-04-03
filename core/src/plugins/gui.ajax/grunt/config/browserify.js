module.exports = function(grunt, options){

    const {PydioCoreRequires,LibRequires,Externals} = require('../../res/js/dist.js');

    options.libName = grunt.option('libName');

    return {
        boot: {
            options:{
                alias:[
                    './res/js/core/http/Connexion.js:pydio/http/connexion',
                    './res/js/core/PydioBootstrap.js:pydio-bootstrap'
                ],
                browserifyOptions: {
                    debug: true
                }
            },
            files: {
                'res/js/vendor/nodejs/boot.prod.js': 'res/js/vendor/nodejs/boot.js',
            }
        },
        core: {
            options:{
                alias: Object.keys(PydioCoreRequires).map(function(key){
                    return './res/js/core/' + key + ':' + PydioCoreRequires[key];
                }),
                browserifyOptions: {
                    debug: true
                }
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
        },
        lib: {
            options: {
                browserifyOptions: {
                    debug: true
                },
                external:Externals
            },
            files: {
                'res/js/ui/reactjs/build/Pydio<%= libName %>.js':'res/js/ui/reactjs/build/<%= libName %>/index.js'
            }
        }
    };
}

