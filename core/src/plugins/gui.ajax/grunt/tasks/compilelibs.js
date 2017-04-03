module.exports = function(grunt, options){

    grunt.registerTask('compilelib', 'Process lib through babel, browserify and uglify', function(n){
        grunt.task.run('babel:lib');
        grunt.task.run(['env:build', 'browserify:lib', 'env:dev']);
        grunt.task.run('uglify:lib');
    });

    grunt.registerTask('compilelibs', 'Process all libs through babel, browserify and uglify', function(n){
        grunt.option('galvanizeConfig', [
            {configs:{libName:'Workspaces'}},
            {configs:{libName:'HOCs'}},
            {configs:{libName:'ReactUI'}},
            {configs:{libName:'Form'}},
            {configs:{libName:'CoreActions'}},
            {configs:{libName:'Components'}}
        ]);
        grunt.task.run('galvanize:compilelib');
    });
}