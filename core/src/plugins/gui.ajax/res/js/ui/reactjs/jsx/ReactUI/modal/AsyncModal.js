import MessagesConsumerMixin from '../MessagesConsumerMixin'
import AsyncComponent from '../AsyncComponent'

/**
 * Specific AsyncComponent for Modal Dialog
 */
export default React.createClass({

    getInitialState:function(){
        return {
            async:true,
            componentData:null,
            open: !!this.props.open,
            actions:[],
            title:null
        }
    },

    componentWillReceiveProps: function(nextProps){
        var componentData = nextProps.componentData;
        var state = {
            componentData:componentData,
            async:true,
            actions:[],
            title:null,
            open: !!nextProps.open
        };
        if(componentData && (!componentData instanceof Object || !componentData['namespace'])){
            state['async'] = false;
            this.initModalFromComponent(componentData);
        }
        if(this.refs.modalAsync){
            this.refs.modalAsync.loadFired = false;
        }
        this.setState(state);
    },

    show: function(){
        this.setState({open: true});
    },

    hide:function(){
        this.setState({open: false});
    },

    onActionsUpdate:function(component){
        if(component.getButtons){
            this.setState({actions:component.getButtons()});
        }
    },

    onTitleUpdate:function(component){
        if(component.getTitle){
            this.setState({title:component.getTitle()});
        }
    },

    onDialogClassNameUpdate:function(component){
        if(component.getDialogClassName){
            this.setState({className:component.getDialogClassName()});
        }
    },

    initModalFromComponent:function(component){
        if(component.getButtons){
            let buttons = component.getButtons();
            if(buttons && buttons.length){
                this.setState({actions:component.getButtons()});
            }
        }else if(component.getSubmitCallback || component.getCancelCallback || component.getNextCallback){
            let actions = [];
            if(component.getCancelCallback){
                actions.push(
                    <MaterialUI.FlatButton
                        key="cancel"
                        label={window.pydio.MessageHash['49']}
                        primary={false}
                        onTouchTap={component.getCancelCallback()}
                    />);
            }
            if(component.getSubmitCallback){
                actions.push(<MaterialUI.FlatButton
                    label={window.pydio.MessageHash['48']}
                    primary={true}
                    keyboardFocused={true}
                    onTouchTap={component.getSubmitCallback()}
                />);
            }
            if(component.getNextCallback){
                actions.push(<MaterialUI.FlatButton
                    label="Nextw"
                    primary={true}
                    keyboardFocused={true}
                    onTouchTap={component.getNextCallback()}
                />);
            }
            this.setState({actions: actions});
        }
        if(component.getTitle){
            this.setState({title:component.getTitle()});
        }
        if(component.getDialogClassName){
            this.setState({className:component.getDialogClassName()});
        }
        if(component.setModal){
            component.setModal(this);
        }
        if(component.isModal){
            this.setState({modal:component.isModal()});
        }else{
            this.setState({modal:false});
        }
    },

    render: function(){
        var modalContent;
        if(this.state.componentData){
            if(this.state.async){
                modalContent = (
                    <PydioReactUI.AsyncComponent
                        {...this.props}
                        namespace={this.state.componentData.namespace}
                        componentName={this.state.componentData.compName}
                        ref="modalAsync"
                        onLoad={this.initModalFromComponent}
                        dismiss={this.hide}
                        actionsUpdated={this.onActionsUpdate}
                        titleUpdated={this.onTitleUpdate}
                        classNameUpdated={this.onDialogClassNameUpdate}
                        modalData={{modal:this, payload: this.state.componentData['payload']}}
                    />
                );
            }else{
                modalContent = this.state.componentData;
            }
        }
        return (
            <MaterialUI.Dialog
                ref="dialog"
                title={this.state.title}
                actions={this.state.actions}
                modal={this.state.modal}
                className={this.state.className}
                open={this.state.open}
                onRequestClose={this.hide}
                contentClassName={this.state.className}
                repositionOnUpdate={true}
            >{modalContent}</MaterialUI.Dialog>
        );
    }

});

