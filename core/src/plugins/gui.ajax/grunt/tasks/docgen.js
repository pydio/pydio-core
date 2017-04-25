const docGen = require('react-docgen');
const resolver = require('react-docgen/dist/resolver/findAllComponentDefinitions').default
const {PydioCoreRequires} = require('../../res/js/dist/libdefs')
const PydioUILibs = {
    Form: 'form',
    ReactUI: 'boot',
    Components: 'components',
    HOCs: 'hoc',
    Workspaces: 'workspaces'
};

module.exports = function(grunt){
    grunt.registerMultiTask('docgen', 'Generate docs for react components', function () {
        //grunt.log.writeln(this.data.files + ': ' + this.target);

        let allDocs = {};

        this.data.files.forEach((patternObject) => {

            grunt.log.writeln('Expanding ' + patternObject.cwd);
            const allFiles = grunt.file.expand(patternObject, patternObject.src);
            grunt.log.writeln('Found ' + allFiles.length + ' files');

            allFiles.forEach((filePath) => {

                if(filePath.indexOf('build/') > -1) return;

                try{
                    let doc = docGen.parse(grunt.file.read(patternObject.cwd + '/' + filePath), resolver);

                    filePath = filePath.replace('../', '').replace('res/react/', '').replace('res/js/', '').replace('/react/', '/');
                    const pluginId = filePath.split('/').shift();
                    if(pluginId === 'gui.ajax'){
                        filePath = filePath.replace('ui/', '');
                        const className = filePath.split('/').pop().replace('.js', '');
                        Object.keys(PydioCoreRequires).forEach((coreLibFile) => {
                            if(filePath.endsWith(coreLibFile)) {
                                doc['require'] = "const "+className+" = require('"+PydioCoreRequires[coreLibFile]+"')";
                            }
                        });
                        Object.keys(PydioUILibs).forEach((uiLibFile) => {
                            if(filePath.indexOf(uiLibFile + '/') > -1) {
                                doc[0]['require'] = "const {"+className+"} = require('pydio').requireLib('"+PydioUILibs[uiLibFile]+"')";
                                //grunt.log.writeln(doc[0]['require']);
                            }
                        });
                    }
                    if(!allDocs[pluginId]){
                        allDocs[pluginId] = {};
                    }
                    allDocs[pluginId][filePath.replace(pluginId + '/', '')] = doc;
                    grunt.log.writeln('[OK] Parsed ' + filePath + ' successfully');
                }catch(e){
                    grunt.verbose.writeln('[SKIP] Skipping ' + filePath + ' (' + e.message + ')');
                }
            });

            //grunt.log.writeln('All Docs' + Object.keys(allDocs));
            grunt.file.write(patternObject.dest, JSON.stringify(allDocs, null, 2));
            grunt.log.writeln('File ' + patternObject.dest + ' written');

        });
    });
}
