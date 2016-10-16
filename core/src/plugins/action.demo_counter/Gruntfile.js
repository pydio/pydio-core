module.exports = function(grunt) {
    grunt.initConfig({
        babel: {
            options: {},

            dist: {
                files: [
                    {
                        expand: true,
                        cwd: 'react/',
                        src: ['**/*.js'],
                        dest: 'build/',
                        ext: '.js'
                    }
                ]
            }
        },
        watch: {
            js: {
                files: [
                    "react/**/*"
                ],
                tasks: ['babel'],
                options: {
                    spawn: false
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.registerTask('default', ['babel']);

};