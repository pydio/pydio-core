module.exports = function(grunt) {
    grunt.initConfig({
        clean: {
            dist: {
                src: ['../../data/cache/plugins_*'],
                options: {
                    force: true
                },
            }
        },
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
        watch: {
            js: {
                files: [
                    "res/**/*"
                ],
                tasks: ['babel', 'clean'],
                options: {
                    spawn: false
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-clean')
    grunt.registerTask('default', ['babel']);

};
