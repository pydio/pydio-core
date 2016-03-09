module.exports = function(grunt) {
    grunt.initConfig({
        babel: {
            options: {},

            dist: {
                files: [
                    {
                        expand: true,
                        cwd: 'res/',
                        src: ['**/*.es6'],
                        dest: 'res/',
                        ext: '.js'
                    }
                ]
            }
        },
        watch: {
            js: {
                files: [
                    "res/*.es6"
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