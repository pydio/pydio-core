module.exports = function(grunt) {
    grunt.initConfig({
        babel: {
            options: {},

            dist: {
                files: [
                    {
                        expand: true,
                        cwd: 'res/react',
                        src: ['**/*.js'],
                        dest: 'res/build',
                        ext: '.js'
                    }
                ]
            }
        },
        watch: {
            js: {
                files: [
                    "res/react/*.js"
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