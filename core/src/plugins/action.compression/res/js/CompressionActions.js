/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */

(function (global) {

    let pydio = global.pydio;

    let CompressionDialog = React.createClass({

        mixins:[
            PydioReactUI.ActionDialogMixin,
            PydioReactUI.CancelButtonProviderMixin,
            PydioReactUI.SubmitButtonProviderMixin
        ],

        getDefaultProps: function(){
            let formats = ['zip', 'tar', 'tar.gz', 'tar.bz2'];
            if(!global.pydio.Parameters.get('multipleFilesDownloadEnabled')){
                formats.pop();
            }
            return {
                dialogTitleId: 313,
                legendId: 314,
                dialogIsModal: true,
                formats: formats
            };
        },

        getInitialState: function(){

            let baseName;
            const {userSelection} = this.props;
            if(userSelection.isUnique()){
                baseName = PathUtils.getBasename(userSelection.getUniqueFileName());
                if(!userSelection.hasDir()) baseName = baseName.substr(0, baseName.lastIndexOf("\."));
            }else{
                baseName = PathUtils.getBasename(userSelection.getContextNode().getPath());
                if(baseName == "") baseName = "Archive";
            }
            let defaultCompression = this.props.formats[0];


            return {
                archiveBase:baseName,
                compression:defaultCompression,
                fileName: this.buildUniqueFileName(baseName, defaultCompression)
            }
        },

        buildUniqueFileName: function(base, extension){
            var index=1;
            let result = base;
            var buff = base;
            while(this.props.userSelection.fileNameExists(result + '.' + extension, true)){
                result = buff + "-" + index; index ++ ;
            }
            return result;
        },

        textFieldChange: function(event, newValue){
            this.setState({
                archiveBase:newValue,
                fileName: this.buildUniqueFileName(newValue, this.state.compression)
            });
        },

        selectFieldChange: function(event, index, payload){
            console.log(payload);
            this.setState({
                compression:payload,
                fileName: this.buildUniqueFileName(this.state.archiveBase, payload)
            });
        },

        submit(){
            const client = PydioApi.getClient();
            client.postSelectionWithAction(this.state.compression === 'zip' ? 'compress' : 'compression',
                function(transp){
                    client.parseXmlMessage(transp.responseXML);
                    this.dismiss();
                }.bind(this),
                this.props.userSelection,
                {
                    type_archive: this.state.compression,
                    archive_name: this.state.fileName + '.' + this.state.compression
                }
            );
        },

        render: function(){
            const formatMenus = this.props.formats.map(function(f){
                return <MaterialUI.MenuItem value={f} primaryText={'.' + f}/>
            });

            const messages = pydio.MessageHash;
            const {compression, fileName} = this.state;
            const flStyle = {
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis'
            };

            return (
                <div style={{display:'flex'}}>
                    <MaterialUI.TextField style={{width: 210, marginRight: 10}} onChange={this.textFieldChange} value={fileName} floatingLabelText={messages['compression.4']}  floatingLabelStyle={flStyle}/>
                    <MaterialUI.SelectField style={{width: 160}} onChange={this.selectFieldChange} value={compression} floatingLabelText={messages['compression.3']} floatingLabelStyle={flStyle}>{formatMenus}</MaterialUI.SelectField>
                </div>
            );
        }

    });

    class Callbacks{

        static compressUI(){
            var userSelection = pydio.getUserSelection();
            if(!pydio.Parameters.get('multipleFilesDownloadEnabled')){
                return;
            }
            pydio.UI.openComponentInModal('CompressionActions', 'CompressionDialog', {userSelection:userSelection});

        }


        static extract(){
            var userSelection = pydio.getUserSelection();
            if (!userSelection.isEmpty()) {
                PydioApi.getClient().postSelectionWithAction('extraction', function(transport){
                    PydioApi.getClient().parseXmlMessage(transport.responseXML);
                }, userSelection);

            }
        }
    }

    global.CompressionActions = {
        CompressionDialog: CompressionDialog,
        Callbacks: Callbacks
    };

})(window);