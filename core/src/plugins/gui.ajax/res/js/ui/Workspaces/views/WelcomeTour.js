import {Component} from 'react'
import Pydio from 'pydio'
import TourGuide from './TourGuide'

class WelcomeTour extends Component{

    constructor(props, context){
        super(props, context);
        if(props.pydio.WelcomeComponentPydio8TourGuideStarted){
            this.state = {started: true};
        }else{
            this.state = {started: false};
        }
    }

    componentDidMount(){
        if(!this.state.started){
            pydio.UI.openComponentInModal('UserAccount', 'WelcomeModal', {
                onRequestStart:(skip) => {this.setState({started: true, skip: skip})}
            });
        }
    }

    render(){

        if(!this.state.started || this.state.skip){
            return null;
        }

        const tourguideSteps = [
            {
                title:'Files List',
                text : <div>This list shows all files and folders belonging to the current workspace. If it is empty, we will learn how to add a file just after.</div>,
                selector:'.main-files-list',
                position:'left'
            },
            {
                title:'Create Menu',
                text : 'This menu allows you to add new files to your workspace',
                selector:'#create-button-menu',
                position:'bottom'
            },
            {
                title:'Info Panel',
                text : 'Here, you will find many information about current selection',
                selector:'#info_panel',
                position:'left'
            },
            {
                title:'Display Options',
                text : 'This toolbar allows you to change the display: switch to thumbnails or detail mode depending on your usage, and sort files by name, date, etc...',
                selector:'#display-toolbar',
                position:'left'
            },
            {
                title:'Address Book',
                text : 'Find and create users to share files and folders with them.' ,
                selector:'.user-widget-toolbar',
                position:'right'
            },
            {
                title:'Alerts',
                text : 'You find here the same information as in your welcome screen' ,
                selector:'.alertsButton',
                position:'right'
            },
            {
                title:'Home',
                text : 'Back to the previous screen. That\'s it for now!',
                selector:'.backToHomeButton',
                position:'right'
            }
        ];
        const callback = (data) => {
            if(data.type === 'step:after' && data.index === tourguideSteps.length - 1 ){
                // Update preferences
                let guiPrefs = this.props.pydio.user.getPreference('gui_preferences', true);
                guiPrefs['WelcomeComponent.Pydio8.TourGuide.FSTemplate'] = true;
                this.props.pydio.user.setPreference('gui_preferences', guiPrefs, true);
                this.props.pydio.user.savePreference('gui_preferences');
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