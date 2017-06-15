module.exports = function(grunt) {

    const {Externals} = require('../gui.ajax/res/js/dist/libdefs');

    grunt.initConfig({
        babel: {
            options: {},

            dist: {
                files: [
                    {
                        expand: true,
                        cwd: 'res/js/',
                        src: ['**/*.js'],
                        dest: 'res/build/',
                        ext: '.js'
                    }
                ]
            }
        },
        browserify: {
            ui : {
                options: {
                    external:Externals
                },
                files: {
                    'res/build/AdminComponents.js'  : 'res/build/AdminComponents/index.js',
                    'res/build/AdminWorkspaces.js'  : 'res/build/AdminWorkspaces/index.js',
                    'res/build/AdminPeople.js'      : 'res/build/AdminPeople/index.js',
                    'res/build/AdminLogs.js'        : 'res/build/AdminLogs/index.js',
                    'res/build/AdminPlugins.js'     : 'res/build/AdminPlugins/index.js',
                    'res/build/AdminScheduler.js'   : 'res/build/AdminScheduler/index.js',
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
                    "res/css/rolesEditor.css": "res/css/rolesEditor.less",
                    "res/css/ajxp_admin.css": "res/css/ajxp_admin.less",

                }
            }
        },
        watch: {
            js: {
                files: [
                    "res/js/**/*"
                ],
                tasks: ['babel', 'browserify'],
                options: {
                    spawn: false
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('assemble-less');
    grunt.registerTask('default', ['babel', 'browserify', 'less']);
    grunt.registerTask('type:js', ['babel', 'browserify']);
    grunt.registerTask('type:css', ['less']);

};