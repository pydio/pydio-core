module.exports = function(grunt){
    grunt.registerTask('mainjs', [
        'copy:dndpatch',
        'rename',
        // CORE
        'babel:core',
        'env:build',
        'browserify:boot',
        'browserify:core',
        'browserify:dist',
        'env:dev',
        'uglify:core',
        'uglify:nodejs',
        // UI
        'compilelibs'

//        'env:build',
//        'babel:materialui',
//        'babel:pydio',
//        'browserify:dist',
//        'browserify:ui',
//        'env:dev',
//        'uglify:nodejs',
//        'uglify:ui'
    ]);
};