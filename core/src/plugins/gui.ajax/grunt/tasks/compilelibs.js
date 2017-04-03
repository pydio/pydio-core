module.exports = function(grunt, options){

    grunt.registerTask('compilelib', 'Process lib through babel, browserify and uglify', function(n){
        grunt.task.run('babel:lib');
        grunt.task.run(['env:build', 'browserify:lib', 'env:dev']);
        grunt.task.run('uglify:lib');
    });

    grunt.registerTask('compilelibs', 'Process all libs through babel, browserify and uglify', function(n){
        grunt.option('galvanizeConfig', [
            {configs:{libName:'Workspaces', alias:'pydio/ui/workspaces'}},
            {configs:{libName:'HOCs', alias:'pydio/ui/hoc'}},
            {configs:{libName:'ReactUI', alias:'pydio/ui/boot'}},
            {configs:{libName:'Form', alias:'pydio/ui/form'}},
            {configs:{libName:'CoreActions', alias:'pydio/actions/core'}},
            {configs:{libName:'Components', alias:'pydio/ui/components'}}
        ]);
        grunt.task.run('galvanize:compilelib');
    });
}