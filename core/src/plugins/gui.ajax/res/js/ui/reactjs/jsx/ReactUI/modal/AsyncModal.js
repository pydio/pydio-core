import AsyncComponent from '../AsyncComponent'
import PydioContextConsumerMixin from '../PydioContextConsumerMixin'

/**
 * Specific AsyncComponent for Modal Dialog
 */
export default React.createClass({

    mixins:[PydioContextConsumerMixin],

    propTypes: {
        size: React.PropTypes.oneOf(['xs', 'sm', 'md', 'lg']),
        padding: React.PropTypes.bool
    },

    sizes: {
        'xs': {width: 120},
        'sm': {width: 210},
        'md': {width: 420},
        'lg': {width: 720}
    },

    styles: {
        dialogRoot: {
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',

            padding: '0px !important'
        },
        dialogContent: {
            position: 'relative',
            paddingTop: 0,
            paddingBottom: 0,
            transform: ""
        },
        dialogBody: {
            paddingTop: 0,
            paddingBottom: 0
        },
        dialogTitle: {
        }
    },

    getInitialState:function(){
        return {
            async: true,
            componentData: null,
            open: !!this.props.open,
            actions: [],
            title: null,
            size: this.props.size || 'md',
            padding: !!this.props.padding
        }
    },

    componentWillReceiveProps: function(nextProps) {

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

    initModalFromComponent: function(component) {

        if(component.getButtons) {
            let buttons = component.getButtons();
            if(buttons && buttons.length){
                this.setState({actions:component.getButtons()});
            }
        } else if(component.getSubmitCallback || component.getCancelCallback || component.getNextCallback) {
            let actions = [];
            if(component.getCancelCallback){
                actions.push(
                    <MaterialUI.FlatButton
                        key="cancel"
                        label={this.context.getMessage('49')}
                        primary={false}
                        onTouchTap={component.getCancelCallback()}
                    />);
            }
            if(component.getSubmitCallback){
                actions.push(<MaterialUI.FlatButton
                    label={this.context.getMessage('48')}
                    primary={true}
                    keyboardFocused={true}
                    onTouchTap={component.getSubmitCallback()}
                />);
            }
            if(component.getNextCallback){
                actions.push(<MaterialUI.FlatButton
                    label="Next"
                    primary={true}
                    keyboardFocused={true}
                    onTouchTap={component.getNextCallback()}
                />);
            }
            this.setState({actions: actions});
        }
        if(component.getTitle){
            this.setState({title: component.getTitle()});
        }
        if(component.getSize){
            this.setState({size: component.getSize()});
        }
        if(component.getPadding){
            this.setState({padding: component.getPadding()});
        }
        if(component.scrollBody && component.scrollBody()){
            this.setState({scrollBody:true});
        }else{
            this.setState({scrollBody:false});
        }
        if(component.setModal){
            component.setModal(this);
        }
        if(component.isModal){
            this.setState({modal: component.isModal()});
        }else{
            this.setState({modal:false});
        }

    },

    render: function(){

        var modalContent;

        const { state, sizes, styles } = this
        const { async, componentData, title, actions, modal, className, open, size, padding, scrollBody } = state

        if (componentData) {
            if(async) {
                modalContent =
                    <PydioReactUI.AsyncComponent
                        {...this.props}
                        namespace={componentData.namespace}
                        componentName={componentData.compName}
                        ref="modalAsync"
                        onLoad={this.initModalFromComponent}
                        dismiss={this.hide}
                        modalData={{modal:this, payload: componentData['payload']}}
                    />
            } else {
                modalContent = componentData;
            }
        }

        let dialogRoot = {...styles.dialogRoot}
        let dialogBody = {...styles.dialogBody, display:'flex'}
        let dialogContent = {...styles.dialogContent, width: sizes[size].width, minWidth: sizes[size].width, maxWidth: sizes[size].width}
        let dialogTitle = {...styles.dialogTitle}

        if (!padding) {
            dialogRoot = {...dialogRoot, padding: 0}
            dialogBody = {...dialogBody, padding: 0}
            dialogContent = {...dialogContent, padding: 0}
        }

        if (title === "") {
            dialogTitle = {...dialogTitle, display: 'none'}
        }

        return (
            <MaterialUI.Dialog
                ref="dialog"
                title={title}
                actions={actions}
                modal={modal}
                className={className}
                open={open}
                contentClassName={className}
                repositionOnUpdate={false}
                autoScrollBodyContent={scrollBody}

                contentStyle={dialogContent}
                bodyStyle={dialogBody}
                titleStyle={dialogTitle}
                style={dialogRoot}
            >{modalContent}</MaterialUI.Dialog>
        );
    }
});
