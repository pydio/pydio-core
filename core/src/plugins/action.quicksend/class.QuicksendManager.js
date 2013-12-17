/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 *
 */
Class.create("QuicksendManager", AjxpPane, {

    initialize: function($super, oFormObject, options){

        $super(oFormObject, options);
        QuicksendManager.INSTANCE = this;
        oFormObject.down('#big_upload_button').observe("click", function(){
            QuicksendManager.INSTANCE.applyUpload();
        });

    },

    applyUpload: function(){

        var uploaders = ajaxplorer.getActiveExtensionByType("uploader");
        if(uploaders.length){
            var uploader = uploaders[0];
            if(ajaxplorer.actionBar.getActionByName("trigger_remote_copy")){
                modal.setCloseAction(function(){
                    ajaxplorer.fireContextRefresh();
                    var bgManager = ajaxplorer.actionBar.bgManager;
                    bgManager.queueAction("trigger_remote_copy", new Hash(), "Copying files to server");
                    bgManager.next();
                });
            }
            if(uploader.dialogOnOpen){
                uploader.resourcesManager.load();
                var dialogOnOpen = new Function("oForm", uploader.dialogOnOpen);
            }
            if(uploader.dialogOnComplete){
                uploader.resourcesManager.load();
                var dialogOnComplete = new Function("oForm", uploader.dialogOnComplete);
            }
            var original = ajxpBootstrap.parameters.get('ajxpServerAccess');
            var origSecure = Connexion.SECURE_TOKEN;
            window.ajxpServerAccessPath = ajxpBootstrap.parameters.set('ajxpServerAccess',  original + '&tmp_repository_id=0');
            Connexion.SECURE_TOKEN  = origSecure + '&tmp_repository_id=0';
            document.observeOnce('ajaxplorer:longtask_finished', function(){
                var connex = new Connexion();
                connex.setParameters({
                    get_action:'share',
                    dir:'/',
                    file:ajaxplorer.getContextHolder().getRootNode().getChildren()[0].getPath()
                });
                connex.onComplete = function(transport){
                    var linkURL = transport.responseText;
                    if(ajaxplorer.hasPluginOfType("mailer")){
                        var s, message;
                        s = 'A user from %s shared a file with you: ';
                        if(s) s = s.replace("%s", ajaxplorer.appTitle);
                        message = s + "\n\n " + linkURL;
                        var mailer = new AjxpMailer();
                        var usersList = null;
                        ajaxplorer.disableAllKeyBindings();
                        var mailerPane = mailer.buildMailPane('A user from %s shared a file with you: '.replace("%s", ajaxplorer.appTitle), message, usersList, 'Send the weblink by email');
                        mailerPane.setStyle({width:'420px'});
                        modal.showSimpleModal(
                            $('content_pane'),
                            mailerPane,
                            function(){
                                mailer.postEmail();
                                ajaxplorer.enableAllKeyBindings();
                                return true;
                            },function(){
                                ajaxplorer.enableAllKeyBindings();
                                return true;
                            });
                    }
                    ajaxplorer.fireContextRefresh();
                };
                connex.sendAsync();

                ajxpBootstrap.parameters.set('ajxpServerAccess', original);
                window.ajxpServerAccessPath = original;
                Connexion.SECURE_TOKEN  = origSecure;
            });
            modal.prepareHeader(MessageHash['action.quicksend.3'], '', 'icon-upload-alt');
            modal.showDialogForm('Upload', uploader.formId, dialogOnOpen, null, dialogOnComplete, true, true);
        }

    }


});