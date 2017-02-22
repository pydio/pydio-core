module.exports = function(grunt) {
    grunt.initConfig({
        babel: {
            options: {},

            dist: {
                files: [
                    {
                        expand: true,
                        cwd: 'res/js',
                        src: ['**/*.js'],
                        dest: 'res/build',
                        ext: '.js'
                    }
                ]
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
                    "dashboard.css": "dashboard.less"
                }
            }
        },
        watch: {
            js: {
                files: [
                    "res/js/**/*"
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
    /*grunt.loadNpmTasks('assemble-less');*/
    grunt.registerTask('default', ['babel']);

};
