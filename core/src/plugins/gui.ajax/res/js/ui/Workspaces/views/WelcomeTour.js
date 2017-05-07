import {Component} from 'react'
import {Divider} from 'material-ui'
import Pydio from 'pydio'
import TourGuide from './TourGuide'
const {PydioContextConsumer} = Pydio.requireLib('boot')
const DOMUtils = require('pydio/util/dom')

class Scheme extends Component {

    render(){
        let style = {
            position:'relative',
            fontSize: 24,
            width: this.props.dimension || 100,
            height: this.props.dimension || 100,
            backgroundColor: '#ECEFF1',
            color: '#607d8b',
            borderRadius: '50%',
            margin: '0 auto'
        };
        return (
            <div style={{...style, ...this.props.style}}>{this.props.children}</div>
        );
    }

}

class IconScheme extends Component {

    constructor(props){
        super(props);
        this.state = {icon: props.icon || props.icons[0], index: 0};
    }

    componentDidMount(){
        this.componentWillReceiveProps(this.props);
    }

    componentWillReceiveProps(nextProps){
        const {icon, icons} = nextProps;
        if(this._interval) clearInterval(this._interval);

        let state;
        if(icon) {
            state = {icon: icon};
        } else if(icons){
            state = {icon: icons[0], index: 0};
            this._interval = setInterval(()=>{
                this.nextIcon();
            }, 1700);
        }
        this.setState(state);
    }

    nextIcon(){
        const {icons} = this.props;
        let next = this.state.index + 1 ;
        if(next > icons.length - 1 ) next = 0;
        this.setState({index: next, icon:icons[next]});
    }

    componentWillUnmount(){
        if(this._interval) clearInterval(this._interval);
    }

    render(){
        const {icon} = this.state;
        return (
            <Scheme dimension={80}>
                <span className={"mdi mdi-" + icon} style={{position: 'absolute', top: 14, left: 14, fontSize:50}}/>
            </Scheme>
        );
    }

}

class CreateMenuCard extends Component{

    componentDidMount(){
        setTimeout(() => {
            this.props.pydio.notify('tutorial-open-create-menu');
        }, 950);
    }

    render(){
        return (
            <div>
                <p>Start adding new files or folders to the current workspace.</p>
                <IconScheme icons={['file-plus', 'folder-plus']}/>
            </div>
        );
    }

}
CreateMenuCard = PydioContextConsumer(CreateMenuCard);


class InfoPanelCard extends Component{

    componentDidMount(){
        this._int = setInterval(() => {
            this.setState({closed:!(this.state && this.state.closed)});
        }, 1500)
    }
    componentWillUnmount(){
        if(this._int) clearInterval(this._int);
    }

    render(){
        let leftStyle = {width: 24, transition:DOMUtils.getBeziersTransition(), transform:'scaleX(1)', transformOrigin:'right'};
        if(this.state && this.state.closed){
            leftStyle = {...leftStyle, width: 0, transform:'scaleX(0)'};
        }

        return (
            <div>
                <p>Here, you will find many information about current selection: file information, sharing status, user-defined metadata, etc.</p>
                <Scheme style={{fontSize: 10, padding: 25}} dimension={130}>
                    <div style={{boxShadow:'2px 2px 0px #CFD8DC', display:'flex'}}>
                        <div style={{backgroundColor:'white', flex:3}}>
                            <div><span className="mdi mdi-folder"/> Folder 1 </div>
                            <div style={{backgroundColor: '#03a9f4', color: 'white'}}><span className="mdi mdi-folder"/>  Folder 2</div>
                            <div><span className="mdi mdi-file"/> File 3</div>
                            <div><span className="mdi mdi-file"/> File 4</div>
                        </div>
                        <div style={leftStyle}>
                            <div style={{backgroundColor: '#edf4f7', padding: 4, height: '100%', fontSize: 17}}><span className="mdi mdi-information-variant"/></div>
                        </div>
                    </div>
                </Scheme>
                <p>You can close this panel by using the <span className="mdi mdi-information" style={{fontSize: 18, color: '#5c7784'}}/> button in the display toolbar.</p>
            </div>
        );

    }

}

InfoPanelCard = PydioContextConsumer(InfoPanelCard);

class UserWidgetCard extends Component{

    render(){
        const iconStyle = {
            display: 'inline-block',
            textAlign: 'center',
            fontSize: 17,
            lineHeight: '20px',
            backgroundColor: '#ECEFF1',
            color: '#607D8B',
            borderRadius: '50%',
            padding: '5px 6px',
            width: 30,
            height: 30,
            marginRight: 5
        };
        return (
            <div>
                <p>
                    <span className="mdi mdi-book-open-variant" style={iconStyle}/> Directory of all the users accessing
                    to the platform. Create your own users, and constitute <b>teams</b> that can be used to share resources.
                </p>
                <Divider/>
                <p>
                    <span className="mdi mdi-bell-outline" style={iconStyle}/> Alerts panel will inform you when a user with whom you shared some
                    resources did access it. They can be sent to you directly by email.
                </p>
                <Divider/>
                <p>
                    <span className="mdi mdi-dots-vertical" style={iconStyle}/> Access to other options : managing your profile and
                    security features, browse all the resources you have shared, sign out of the platform.
                </p>
                <Divider/>
                <p>
                    <span className="mdi mdi-home-variant" style={iconStyle}/> Go back to the welcome panel with this button
                </p>
            </div>
        );
    }

}


UserWidgetCard = PydioContextConsumer(UserWidgetCard);

class WelcomeTour extends Component{

    constructor(props, context){
        super(props, context);
        this.state = {started: !(props.pydio.user && !props.pydio.user.getPreference('gui_preferences', true)['UserAccount.WelcomeModal.Shown'])};
    }

    discard(pref='WelcomeComponent.Pydio8.TourGuide.FSTemplate'){
        const {user} = this.props.pydio;
        let guiPrefs = user.getPreference('gui_preferences', true);
        guiPrefs[pref] = true;
        user.setPreference('gui_preferences', guiPrefs, true);
        user.savePreference('gui_preferences');
    }

    componentDidMount(){
        if(!this.state.started){
            pydio.UI.openComponentInModal('UserAccount', 'WelcomeModal', {
                onRequestStart:(skip) => {
                    this.discard('UserAccount.WelcomeModal.Shown');
                    if(skip) this.discard();
                    this.setState({started: true, skip: skip});
                }
            });
        }
    }

    render(){

        if(!this.state.started || this.state.skip){
            return null;
        }

        const tourguideSteps = [
            {
                title:'Add resources',
                text : <CreateMenuCard/>,
                selector:'#create-button-menu',
                position:'left',
                style:{width:220}
            },
            {
                title:'Display Options',
                text : <div><p>This toolbar allows you to change the display: switch to thumbnails or detail mode depending on your usage, and sort files by name, date, etc...</p><IconScheme icons={['view-list', 'view-grid', 'view-carousel', 'sort-ascending', 'sort-descending']}/></div>,
                selector:'#display-toolbar',
                position:'left'
            },
            {
                title:'Info Panel',
                text : <InfoPanelCard/>,
                selector:'#info_panel',
                position:'left'
            },
            {
                title:'User Cartouche',
                text : <UserWidgetCard/>,
                selector:'.user-widget',
                position:'right',
                style:{width: 320}
            }
        ];
        const callback = (data) => {
            if(data.type === 'step:after' && data.index === tourguideSteps.length - 1 ){
                this.discard();
            }
        };
        return (
            <TourGuide
                ref="joyride"
                steps={tourguideSteps}
                run={true} // or some other boolean for when you want to start it
                autoStart={true}
                debug={false}
                callback={callback}
                type='continuous'
            />
        );


    }

}

export {WelcomeTour as default}