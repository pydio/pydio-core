const React = require('react')

let UploadDialog = React.createClass({

    mixins:[
        PydioReactUI.ActionDialogMixin,
        PydioReactUI.SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: 'Upload',
            dialogSize: 'lg',
            dialogPadding: false,
            dialogIsModal: true
        };
    },

    submit(){
        this.dismiss();
    },

    render: function(){
        let tabs = [];
        let uploaders = this.props.pydio.Registry.getActiveExtensionByType("uploader");
        const dismiss = () => {this.dismiss()};

        uploaders.sort(function(objA, objB){
            return objA.order - objB.order;
        });

        uploaders.map(function(uploader){
            if(uploader.moduleName) {
                let parts = uploader.moduleName.split('.');
                tabs.push(
                    <MaterialUI.Tab label={uploader.xmlNode.getAttribute('label')} key={uploader.id}>
                        <PydioReactUI.AsyncComponent
                            pydio={this.props.pydio}
                            namespace={parts[0]}
                            componentName={parts[1]}
                            onDismiss={dismiss}
                        />
                    </MaterialUI.Tab>
                );
            }
        });

        return (
            <MaterialUI.Tabs>
                {tabs}
            </MaterialUI.Tabs>
        );
    }

});

export {UploadDialog as default}