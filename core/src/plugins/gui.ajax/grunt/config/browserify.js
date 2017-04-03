module.exports = function(grunt, options){

    const {PydioCoreRequires,LibRequires,Externals} = require('../../res/js/dist.js');

    options.libName = grunt.option('libName');

    return {
        boot: {
            options:{
                alias:[
                    './res/build/core/http/Connexion.js:pydio/http/connexion',
                    './res/build/core/PydioBootstrap.js:pydio-bootstrap'
                ],
                browserifyOptions: {
                    debug: true
                }
            },
            files: {
                'res/build/boot.prod.js': 'res/js/vendor/nodejs/boot.js',
            }
        },
        core: {
            options:{
                alias: Object.keys(PydioCoreRequires).map(function(key){
                    return './res/build/core/' + key + ':' + PydioCoreRequires[key];
                }),
                browserifyOptions: {
                    debug: true
                }
            },
            files: {
                'res/build/PydioCore.js': 'res/build/core/index.js',
            }
        },
        dist: {
            options: {
                alias: LibRequires.map(k => k + ':')
            },
            files: {
                'res/build/bundle.prod.js': 'res/js/vendor/nodejs/export.js',
                'res/build/bundle.legacy.prod.js': 'res/js/vendor/nodejs/export.legacy.js'
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
                'res/build/Pydio<%= libName %>.js':'res/build/ui/<%= libName %>/index.js'
            }
        }
    };
}

