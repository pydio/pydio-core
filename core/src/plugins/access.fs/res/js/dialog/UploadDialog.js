const React = require('react')
const {ActionDialogMixin, SubmitButtonProviderMixin} = require('pydio').requireLib('boot')

let UploadDialog = React.createClass({

    mixins:[
        ActionDialogMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: '',
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

        uploaders.map((uploader) => {
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