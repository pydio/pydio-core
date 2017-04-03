module.exports = function(grunt){
    grunt.registerTask('mainjs', [
        'copy',
        'rename',
        'symlink',
        'babel:dist',
        'env:build',
        'browserify:boot',
        'browserify:core',
        'env:dev',
        'uglify:js',
        'babel:materialui',
        'babel:pydio',
        'env:build',
        'browserify:dist',
        'browserify:ui',
        'env:dev',
        'uglify:nodejs',
        'uglify:ui'
    ]);
};