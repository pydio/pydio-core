module.exports = function(grunt){
    grunt.registerTask('type:css', [
        'copy:mfb',
        'rename',
        'symlink',
        'cssmin'
    ]);
};