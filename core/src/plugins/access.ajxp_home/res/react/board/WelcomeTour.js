import {Component} from 'react'
import Pydio from 'pydio'
const {AsyncComponent} = Pydio.requireLib('boot');

class WelcomeTour extends Component{

    constructor(props, context){
        super(props, context);
        this.state = {started: false};
    }

    componentDidMount(){
        if(!this.state.started){
            pydio.UI.openComponentInModal('UserAccount', 'WelcomeModal', {
                onRequestStart:(skip) => {this.setState({started: true})}
            });
        }
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
                title:'My History',
                text : <div><div>Here, you will find all the recent, workspaces, files and folders that you may have opened or visited.</div><div>Just click on an item to access it directly.</div></div>,
                selector:'#history-block',
                position:'top'
            },
            {
                title:'My Alerts',
                text : 'When you share a file or folder, you can get notified if another user accessed it. You can also watch any specific folders inside a workspace.',
                selector:'#alerts-block',
                position:'top'
            },
            {
                title:'Search',
                text : 'Use this search form to find files or folders in any workspace. Only the first 5 results are show, enter a workspace to get more results, and more search options.',
                selector:'.top_search_form',
                position:'bottom'
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
                    text : 'Now let\'s enter a workspace and learn how it is organized. Ready ?' ,
                    selector:'.workspace-entry',
                    position:'right'
                }
            ])
        }

        const callback = (data) => {
            if(data.type === 'step:after' && data.index === tourguideSteps.length - 1 ){

                const repoList = this.props.pydio.user.getRepositoriesList();
                let guiPrefs = this.props.pydio.user.getPreference('gui_preferences', true);
                guiPrefs['WelcomeComponent.Pydio8.TourGuide.Welcome'] = true;
                this.props.pydio.user.setPreference('gui_preferences', guiPrefs, true);
                this.props.pydio.user.savePreference('gui_preferences');

                this.props.pydio.WelcomeComponentPydio8TourGuideStarted = true;
                try{
                    const repoKey = repoList.keys().next().value;
                    this.props.pydio.triggerRepositoryChange(repoKey);
                }catch(e){}
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