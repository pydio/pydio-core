module.exports = function(grunt) {

    const {Externals} = require('../gui.ajax/res/js/dist/libdefs.js');

    grunt.initConfig({
        babel: {
            options: {},

            dist: {
                files: [
                    {
                        expand: true,
                        cwd: 'res/js/',
                        src: ['**/*.js'],
                        dest: 'res/build/PydioNotifications/',
                        ext: '.js'
                    }
                ]
            }
        },
        browserify: {
            ui : {
                options: {
                    external: Externals,
                    browserifyOptions:{
                        standalone: 'PydioNotifications',
                        debug:true
                    }
                },
                files: {
                    'res/build/PydioNotifications.js':'res/build/PydioNotifications/index.js'
                }
            }
        },
        watch: {
            js: {
                files: [
                    "res/**/*"
                ],
                tasks: ['babel', 'browserify:ui'],
                options: {
                    spawn: false
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.registerTask('default', ['babel', 'browserify:ui']);

};
