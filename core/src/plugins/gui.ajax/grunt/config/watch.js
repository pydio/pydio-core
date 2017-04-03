const {PydioCoreRequires} = require('../../res/js/dist/libdefs.js');

module.exports = {
    es6 : {
        files:[
            'res/js/es6/*.es6',
            'res/js/es6/**/*.es6'
        ],
            tasks:['babel:dist', 'browserify:core'],
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
};