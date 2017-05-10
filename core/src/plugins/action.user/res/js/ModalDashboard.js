const React = require('react')
const Pydio = require('pydio')
const {ActionDialogMixin, SubmitButtonProviderMixin, AsyncComponent} = Pydio.requireLib('boot')
const {Tabs, Tab, FontIcon, FlatButton} = require('material-ui')
import ProfilePane from './ProfilePane'
import ComponentConfigParser from './ComponentConfigParser'

let ModalDashboard = React.createClass({

    mixins: [
        ActionDialogMixin,
        SubmitButtonProviderMixin
    ],

    getDefaultProps: function(){
        return {
            dialogTitle: '',
            dialogSize: 'md',
            dialogPadding: false,
            dialogIsModal: false,
            dialogScrollBody: false
        };
    },

    submit: function(){
        this.dismiss();
    },

    getDefaultButtons: function(){
        return [<FlatButton label="Close" onTouchTap={this.props.onDismiss}/>];
    },

    getButtons: function(updater){
        this._updater = updater;
        if(this.refs['profile']){
            return this.refs['profile'].getButtons(this._updater);
        }else{
            return this.getDefaultButtons();
        }
    },

    onTabChange: function(value){
        if(!this._updater) return;
        if(value && this.refs[value] && this.refs[value].getButtons){
            this._updater(this.refs[value].getButtons(this._updater));
        }else{
            this._updater(this.getDefaultButtons());
        }
    },

    render: function(){

        const buttonStyle = {
            textTransform: 'none'
        };
        let tabs = [
            (<Tab key="account" label={this.props.pydio.MessageHash['user_dash.43']} icon={<FontIcon className="mdi mdi-account"/>} buttonStyle={buttonStyle} value="profile">
                <ProfilePane {...this.props} ref="profile"/>
            </Tab>)
        ];

        ComponentConfigParser.getAccountTabs(this.props.pydio).map(function(tab){
            tabs.push(
                <Tab key={tab.id} label={this.props.pydio.MessageHash[tab.tabInfo.label]} icon={<FontIcon className={tab.tabInfo.icon}/>} buttonStyle={buttonStyle} value={tab.id}>
                    <AsyncComponent
                        ref={tab.id}
                        {...this.props}
                        {...tab.paneInfo}
                    />
                </Tab>
            );
        }.bind(this));

        return (
            <Tabs
                style={{display:'flex', flexDirection:'column', width:'100%'}}
                tabItemContainerStyle={{minHeight:72}}
                contentContainerStyle={{overflowY:'auto', minHeight: 350}}
                onChange={this.onTabChange}
            >
                {tabs}
            </Tabs>
        );

    }

});

export {ModalDashboard as default}