import {Component} from 'react'
import Pydio from 'pydio'
const {AsyncComponent} = Pydio.requireLib('boot');

class WelcomeTour extends Component{

    constructor(props, context){
        super(props, context);
        this.state = {started: !(props.pydio.user && !props.pydio.user.getPreference('gui_preferences', true)['UserAccount.WelcomeModal.Shown'])};
    }

    componentDidMount(){
        if(!this.state.started){
            pydio.UI.openComponentInModal('UserAccount', 'WelcomeModal', {
                onRequestStart:(skip) => {
                    this.discard('UserAccount.WelcomeModal.Shown');
                    if(skip) {
                        this.discard();
                    }else{
                        this.setState({started: true});
                    }
                }
            });
        }
    }

    discard(pref = 'WelcomeComponent.Pydio8.TourGuide.Welcome'){
        const {user} =  this.props.pydio;
        let guiPrefs = user.getPreference('gui_preferences', true);
        guiPrefs[pref] = true;
        user.setPreference('gui_preferences', guiPrefs, true);
        user.savePreference('gui_preferences');
    }

    render(){

        if(!this.state.started){
            return null;
        }

        let tourguideSteps = [
            {
                title:'My Workspaces',
                text : 'Workspaces are top-level folders helping you organize your data. They may be personal to you, or shared accross many people.',
                selector:'.user-workspaces-list',
                position:'right'
            },
            {
                title:'My Alerts',
                text : 'When you share a file or folder, you can get notified if another user accessed it. You can also watch any specific folders inside a workspace.',
                selector:'.alertsButton',
                position:'right'
            },
            {
                title:'Search',
                text : 'Use this search form to find files or folders in any workspace. Only the first 5 results are show, enter a workspace to get more results, and more search options.',
                selector:'.home-search-form',
                position:'bottom'
            },
            {
                title:'My History',
                text : <div><div>When no search results are displayed, you will find here all the recent workspaces, files and folders that you may have opened or visited.</div><div>Just click on an item to access it directly.</div></div>,
                selector:'#history-block',
                position:'top'
            },
            {
                title:'Widgets',
                text : 'You can add or remove widgets here. You can also reorder them by simply dragging them around.',
                selector:'.dashboard-layout',
                position:'left'
            },
        ];

        if(this.props.pydio.user && this.props.pydio.user.getRepositoriesList().size){
            tourguideSteps = tourguideSteps.concat([
                {
                    title:'Open a workspace',
                    text : 'At the first connection, your history is probably empty. Enter a workspace once you are ready to add files.' ,
                    selector:'.workspace-entry',
                    position:'right'
                }
            ])
        }

        const callback = (data) => {
            if(data.type === 'step:after' && data.index === tourguideSteps.length - 1 ){
                this.discard();
            }
        };
        return (
            <AsyncComponent
                namespace="PydioWorkspaces"
                componentName="TourGuide"
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