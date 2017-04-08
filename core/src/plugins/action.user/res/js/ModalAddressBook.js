const React = require('react')
const Pydio = require('pydio')
const {ActionDialogMixin} = Pydio.requireLib('boot')
const {AddressBook} = Pydio.requireLib('components')
const {AppBar} = require('material-ui')

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
            dialogScrollBody: false
        };
    },

    submit: function(){
        this.dismiss();
    },

    render: function(){

        return (
            <div style={{width:'100%', display:'flex', flexDirection:'column'}}>
                <AppBar
                    title={this.props.pydio.MessageHash['user_dash.1']}
                    showMenuIconButton={false}
                    iconClassNameRight="mdi mdi-close"
                    onRightIconButtonTouchTap={()=>{this.dismiss()}}
                    style={{flexShrink:0}}
                />
                <AddressBook
                    mode="book"
                    {...this.props}
                    style={{width:'100%', flex: 1}}
                />
            </div>
        );

    }

});

export {ModalAddressBook as default}