const React = require('react')
const Pydio = require('pydio')
const {ActionDialogMixin} = Pydio.requireLib('boot')
const {ModalAppBar, AddressBook} = Pydio.requireLib('components')

const ModalAddressBook = React.createClass({

    mixins: [
        ActionDialogMixin,
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: '',
            dialogSize: 'xl',
            dialogPadding: false,
            dialogIsModal: false,
            dialogScrollBody: true
        };
    },

    submit: function(){
        this.dismiss();
    },

    render: function(){

        return (
            <div style={{width:'100%', display:'flex', flexDirection:'column'}}>
                <ModalAppBar
                    title={this.props.pydio.MessageHash['user_dash.1']}
                    showMenuIconButton={false}
                    iconClassNameRight="mdi mdi-close"
                    onRightIconButtonTouchTap={()=>{this.dismiss()}}
                />
                <AddressBook
                    mode="book"
                    {...this.props}
                    style={{width:'100%', flexGrow: 1, height:'auto'}}
                />
            </div>
        );

    }

});

export {ModalAddressBook as default}