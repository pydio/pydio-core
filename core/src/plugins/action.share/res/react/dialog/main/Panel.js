const React = require('react');
const {Tabs, Tab, Toggle} = require('material-ui');
import HeaderPanel from './Header'
import PublicLinkPanel from '../public/Panel'
import UsersPanel from '../users/Panel'
import AdvancedPanel from '../advanced/Panel'
import ButtonsComputer from './ButtonsComputer'
const {ActionDialogMixin} = require('pydio').requireLib('boot')
const ShareModel = require('pydio').requireLib('ReactModelShare')
const {PaletteModifier} = require('pydio').requireLib('hoc')

let MainPanel = React.createClass({

    mixins:[ ActionDialogMixin ],

    getDefaultProps: function(){
        return {
            dialogTitle:'',
            dialogIsModal:false,
            dialogPadding:false
        };
    },

    propTypes: {
        pydio       : React.PropTypes.instanceOf(Pydio).isRequired,
        selection   : React.PropTypes.instanceOf(PydioDataModel).isRequired,
        readonly    : React.PropTypes.bool
    },

    childContextTypes: {
        messages:React.PropTypes.object,
        getMessage:React.PropTypes.func,
        isReadonly:React.PropTypes.func
    },

    getChildContext: function() {
        const messages = this.props.pydio.MessageHash;
        return {
            messages: messages,
            getMessage: function(messageId, namespace='share_center'){
                try{
                    return messages[namespace + (namespace?".":"") + messageId] || messageId;
                }catch(e){
                    return messageId;
                }
            },
            isReadonly: function(){
                return this.props.readonly;
            }.bind(this)
        };
    },

    modelUpdated: function(eventData){
        if(this.isMounted()){
            this.setState({
                status: eventData.status,
                model:eventData.model
            });
        }
    },

    getInitialState: function(){
        return {
            status: 'idle',
            mailerData: false,
            model: new ShareModel(this.props.pydio, this.props.selection.getUniqueNode(), this.props.selection)
        };
    },

    componentDidMount: function(){
        this.state.model.observe("status_changed", this.modelUpdated);
        this.state.model.initLoad();
    },

    componentWillUnmount: function(){
        if(this.buttonsComputer) this.buttonsComputer.stop();
    },

    componentWillReceiveProps: function(nextProps){
        if(nextProps.selection && nextProps.selection !== this.props.selection){
            let nextModel = new ShareModel(this.props.pydio, nextProps.selection.getUniqueNode(), nextProps.selection);
            this.setState({model:nextModel, status:'idle', mailerData: false}, () => {this.componentDidMount()});
        }
    },

    getButtons:function(updater){

        this.buttonsComputer = new ButtonsComputer(this.props.pydio, this.state.model, updater, this.dismiss.bind(this), this.getMessage.bind(this));
        this.buttonsComputer.start();
        return this.buttonsComputer.getButtons();

    },

    showMailer:function(subject, message, users = [], hash = null){
        if(ShareModel.forceMailerOldSchool()){
            subject = encodeURIComponent(subject);
            global.location.href = "mailto:custom-email@domain.com?Subject="+subject+"&Body="+message;
            return;
        }
        global.ResourcesManager.loadClassesAndApply(['PydioMailer'], function(){
            this.setState({
                mailerData: {
                    subject:subject,
                    message:message,
                    users:users,
                    hash: hash,
                    enableIdentification:false,
                    crippleIdentificationKeys:false,
                    identifiedOnly:false
                }
            });
        }.bind(this));
    },

    toggleMailerData:function(data){
        this.setState({mailerData: {...this.state.mailerData, ...data}});
    },

    dismissMailer:function(){
        this.setState({mailerData:false});
    },

    mailerProcessPost: function(Email, users, subject, message, link, callback){
        const {model, mailerData} = this.state;
        const {crippleIdentificationKeys, identifiedOnly, hash} = mailerData;
        const client = PydioApi.getClient();
        let shareLabels = {}, shareMails = {};
        Object.keys(users).forEach((u) => {
            const k = crippleIdentificationKeys ? Math.random().toString(36).substring(7) : u;
            shareLabels[k] = users[u].getLabel();
            shareMails[k] = u;
        });
        // Store keys
        client.request({
            get_action: 'share_link_update_target_users',
            hash: hash,
            json_users: JSON.stringify(shareLabels),
            restrict: identifiedOnly ? 'true' : 'false'
        }, () => {
            const email = new Email();
            const originalLink = model.getPublicLink(hash);
            const regexp = new RegExp(originalLink, 'g');
            Object.keys(shareMails).forEach((u) => {
                const newLink = originalLink + '?u='  + u;
                const newMessage = message.replace(regexp, newLink);
                email.addTarget(shareMails[u], subject, newMessage);
            });
            email.post(callback);
        })
    },

    getMessage: function(key, namespace = 'share_center'){
        return this.props.pydio.MessageHash[namespace + (namespace?'.':'') + key];
    },

    render: function(){
        var model = this.state.model;
        var panels = [];
        var showMailer = ShareModel.mailerActive() ? this.showMailer : null;
        var auth = ShareModel.getAuthorizations(this.props.pydio);
        if((model.getNode().isLeaf() && auth.file_public_link) || (!model.getNode().isLeaf() && auth.folder_public_link)){
            var publicLinks = model.getPublicLinks();
            if(publicLinks.length){
                var linkData = publicLinks[0];
            }
            let pubLabel = this.getMessage(121);
            if(model.hasPublicLink()){
                pubLabel = <span>{pubLabel} <span className="mdi mdi-check"></span></span>
            }
            panels.push(
                <Tab key="public-link" label={pubLabel}>
                    <PublicLinkPanel
                        showMailer={showMailer}
                        linkData={linkData}
                        pydio={this.props.pydio}
                        shareModel={model}
                        authorizations={auth}
                        style={{height: '100%', overflowY: 'auto'}}
                    />
                </Tab>
            );
        }
        if( (model.getNode().isLeaf() && auth.file_workspaces) || (!model.getNode().isLeaf() && auth.folder_workspaces)){
            var users = model.getSharedUsers();
            var ocsUsers = model.getOcsLinks();
            var totalUsers = users.length + ocsUsers.length;
            panels.push(
                <Tab key="target-users" label={this.getMessage(249, '') + (totalUsers?' ('+totalUsers+')':'')}>
                    <UsersPanel
                        showMailer={showMailer}
                        shareModel={model}
                        pydio={this.props.pydio}
                        style={{height: '100%', overflowY: 'auto'}}
                    />
                </Tab>
            );
        }
        if(panels.length > 0){
            panels.push(
                <Tab key="share-permissions" label={this.getMessage(486, '')}>
                    <AdvancedPanel
                        showMailer={showMailer}
                        pydio={this.props.pydio}
                        shareModel={model}
                        style={{height: '100%', overflowY: 'auto'}}
                    />
                </Tab>
            );
        }
        let mailer;
        if(this.state.mailerData){
            const {mailerData} = this.state;
            let customizeMessagePane;
            console.log(mailerData);
            if(mailerData.hash){
                const style = mailerData.enableIdentification ? {padding:'10px 20px', backgroundColor: '#ECEFF1'} : {padding:'10px 20px 0'};
                customizeMessagePane = (
                    <div style={style}>
                        <Toggle label={this.getMessage(235)} toggled={mailerData.enableIdentification} onToggle={(e, c) => {this.toggleMailerData({enableIdentification:c})} }/>
                        {mailerData.enableIdentification &&
                        <Toggle label={"-- " + this.getMessage(236)} toggled={mailerData.identifiedOnly} onToggle={(e, c) => {this.toggleMailerData({identifiedOnly:c})} }/>
                        }
                        {mailerData.enableIdentification &&
                        <Toggle label={"-- " + this.getMessage(237)} toggled={mailerData.crippleIdentificationKeys} onToggle={(e, c) => {this.toggleMailerData({crippleIdentificationKeys:c})} }/>
                        }
                    </div>
                );
            }
            mailer = (<PydioMailer.Pane
                {...mailerData}
                onDismiss={this.dismissMailer}
                overlay={true}
                className="share-center-mailer"
                panelTitle={this.props.pydio.MessageHash["share_center.45"]}
                additionalPaneTop={customizeMessagePane}
                processPost={mailerData.enableIdentification ? this.mailerProcessPost : null}
            />);
        }

        return (
            <Content
                {...this.props}
                model={this.state.model}
                panels={panels}
                mailer={mailer}
            />
        );

        return(
            <div className="react_share_form" style={{width: 420, display:'flex', flexDirection:'column'}}>
                <HeaderPanel {...this.props} shareModel={this.state.model}/>
                <Tabs {...tabStyles} >{panels}</Tabs>
                {mailer}
            </div>
        );
    }

});

class Content extends React.Component{

    render(){

        const tabStyles = {
            style : {
                flex: 1,
                overflow: 'hidden',
                display:'flex',
                flexDirection: 'column'
            },
            contentContainerStyle : {
                flex: 1,
                overflow: 'hidden'
            },
            tabTemplateStyle: {
                height: '100%',
                backgroundColor: '#fafafa'
            }
        }

        return(
            <div className="react_share_form" style={{width: 420, display:'flex', flexDirection:'column'}}>
                <HeaderPanel {...this.props} shareModel={this.props.model}/>
                <Tabs {...tabStyles} >{this.props.panels}</Tabs>
                {this.props.mailer}
            </div>
        );
    }

}

Content = PaletteModifier({primary1Color:'#4aceb0'})(Content);

export {MainPanel as default}