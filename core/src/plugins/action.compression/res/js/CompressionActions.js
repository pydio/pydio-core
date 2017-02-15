(function (global) {

    let pydio = global.pydio;

    class Callbacks{

        static compressUI(){
            var crtDir = pydio.getContextHolder().getContextNode().getPath();
            var userSelection = pydio.getUserSelection();
            if (!userSelection.isEmpty()) {
                var loadFunc = function (oForm) {
                    if (userSelection.isEmpty()) {
                        return;
                    }
                    var archive_nameInput = oForm.down('input[id="archive_name"]');
                    var changeDuplicateArchiveName = function (name, extension) {
                        var nameLastIndexOf = name.lastIndexOf("-");
                        var tmpFileName = name.substr(0, nameLastIndexOf);
                        var compteurFileName = name.substr(nameLastIndexOf + 1);
                        if (nameLastIndexOf == -1) {
                            tmpFileName = name;
                            compteurFileName = 1;
                        }
                        while(userSelection.fileNameExists(name + extension)){
                            name = tmpFileName + "-" + compteurFileName;
                            compteurFileName ++;
                            if(compteurFileName > 20){
                                break;
                            }
                        }
                        archive_nameInput.setValue(name + extension);
                        return name;
                    };
                    var archive_name = archive_nameInput.getValue();
                    var archiveTypeSelect = oForm.down('select[id="type_archive"]');
                    if(window.multipleFilesDownloadEnabled){
                        archiveTypeSelect.insert({top:'<option value=".zip">ZIP</option>'});
                    }
                    var archiveExtension = archiveTypeSelect.getValue();
                    if (userSelection.isUnique()) {
                        if (userSelection.getUniqueNode().isLeaf() == true) {
                            archive_name = getBaseName(userSelection.getUniqueFileName()).split(".").shift();
                        } else {
                            archive_name = getBaseName(userSelection.getUniqueFileName());
                        }
                    } else if (crtDir.length == 1) {
                        archive_name = "Archive";
                    } else {
                        archive_name = getBaseName(crtDir);
                    }
                    var updateFormAndArchiveName = function (){
                        var archiveExtension = archiveTypeSelect.getValue();
                        if(archiveExtension == ".zip"){
                            oForm.setAttribute("action", "compress");
                            oForm.down("#compression_form").setAttribute("action", "compress");
                            oForm.down('input[name="get_action"]').value = "compress";
                        }else{
                            oForm.setAttribute("action", "compression");
                            oForm.down("#compression_form").setAttribute("action", "compression");
                            oForm.down('input[name="get_action"]').value = "compression";
                        }
                        changeDuplicateArchiveName(archive_name, archiveExtension);
                    };
                    updateFormAndArchiveName();
                    archiveTypeSelect.observe("change", updateFormAndArchiveName);
                    archive_nameInput.observe("change", function () {
                        archive_name = archive_nameInput.getValue().slice(0, -archiveTypeSelect.getValue().length);
                        changeDuplicateArchiveName(archive_name, archiveTypeSelect.getValue());
                    });
                };
                var closeFunc = function(){
                    userSelection.updateFormOrUrl(modal.getForm());
                    PydioApi.getClient().submitForm(modal.getForm(), true);
                    hideLightBox();
                };
                modal.showDialogForm('Compress selection to ...', 'compression_form', loadFunc, closeFunc);
            }

        }
        
        static extract(){
            var crtDir = pydio.getContextHolder().getContextNode().getPath();
            var userSelection = pydio.getUserSelection();
            if (!userSelection.isEmpty()) {
                var file = userSelection.getFileNames();
                var dir = userSelection.getContextNode().getPath();
                var connexion = new Connexion();
                connexion.setParameters({get_action : "extraction", file : file, currentDir : dir});
                connexion.sendAsync();
                connexion.onComplete=function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                };
            }
        }
    }

    global.CompressionActions = {
        Callbacks: Callbacks
    };

})(window);