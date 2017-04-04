module.exports = function(grunt){
    grunt.registerTask('maincss', [
        'copy:mfb',
        'rename',
        'symlink',
        'cssmin'
    ]);
};