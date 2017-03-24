import AsyncComponent from '../AsyncComponent'
import PydioContextConsumerMixin from '../PydioContextConsumerMixin'

/**
 * Specific AsyncComponent for Modal Dialog
 */
export default React.createClass({

    mixins:[PydioContextConsumerMixin],

    propTypes: {
        size: React.PropTypes.oneOf(['xxs', 'xs', 'sm', 'md', 'lg', 'xl']),
        padding: React.PropTypes.bool,
        bgBlur: React.PropTypes.bool
    },

    sizes: {
        'xxs': {width: 120},
        'xs': {width: 210},
        'sm': {width: 320},
        'md': {width: 420},
        'lg': {width: 720},
        'xl': {width: '80%'}
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

    blurStyles:{
        overlayStyle:{
            backgroundColor: 'rgba(0,0,0,0)'
        },
        dialogContent: {

        },
        dialogTitle:{
            color: 'rgba(255,255,255,0.9)'
        },
        dialogBody:{
            color: 'rgba(255,255,255,0.9)'
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
            padding: !!this.props.padding,
            blur: false
        }
    },

    componentWillUnmount: function(){
        this.deactivateResizeObserver();
    },

    activateResizeObserver: function(){
        if(this._resizeObserver) return;
        this._resizeObserver = () => {this.computeBackgroundData()};
        DOMUtils.observeWindowResize(this._resizeObserver);
        this.computeBackgroundData();
    },

    deactivateResizeObserver: function(){
        if(this._resizeObserver){
            DOMUtils.stopObservingWindowResize(this._resizeObserver);
            this._resizeObserver = null;
        }
    },

    componentWillReceiveProps: function(nextProps) {

        var componentData = nextProps.componentData;
        var state = {
            componentData:componentData,
            async:true,
            actions:[],
            title:null,
            open: !!nextProps.open,
            blur: !!nextProps.blur
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

    updateButtons(actions){
        this.setState({actions: actions});
    },

    initModalFromComponent: function(component) {

        if(component.getButtons) {
            const buttons = component.getButtons(this.updateButtons.bind(this));
            if(buttons && buttons.length){
                this.setState({actions:buttons});
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
        if(component.useBlur){
            this.setState({blur: component.useBlur()}, () => {this.activateResizeObserver()});
        }else{
            this.setState({blur: false}, () => {this.deactivateResizeObserver()});
        }

    },

    computeBackgroundData: function(){
        const pydioMainElement = document.getElementById(window.pydio.Parameters.get('MAIN_ELEMENT'));
        const reference = pydioMainElement.querySelector('div[data-reactroot]');
        if(!reference){
            return;
        }
        const url = window.getComputedStyle(reference).getPropertyValue('background-image');

        let backgroundImage = new Image();
        backgroundImage.src = url.replace(/"/g,"").replace(/url\(|\)$/ig, "");

        let oThis = this;
        backgroundImage.onload = function() {
            const width = this.width;
            const height = this.height;

            const screenWidth = DOMUtils.getViewportWidth();
            const screenHeight = DOMUtils.getViewportHeight();

            const imageRatio = width/height;
            const coverRatio = screenWidth/screenHeight;

            let coverHeight, scale, coverWidth;
            if (imageRatio >= coverRatio) {
                coverHeight = screenHeight;
                scale = (coverHeight / height);
                coverWidth = width * scale;
            } else {
                coverWidth = screenWidth;
                scale = (coverWidth / width);
                coverHeight = height * scale;
            }
            let cover = coverWidth + 'px ' + coverHeight + 'px';
            oThis.setState({
                backgroundImage: url,
                backgroundSize: cover
            });
        };
    },

    render: function(){

        var modalContent;

        const { state, sizes, styles, blurStyles } = this
        const { async, componentData, title, actions, modal, open, size, padding, scrollBody, blur } = state
        let { className } = state;

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
        let overlayStyle;

        if (!padding) {
            dialogRoot = {...dialogRoot, padding: 0}
            dialogBody = {...dialogBody, padding: 0}
            dialogContent = {...dialogContent, padding: 0}
        }

        if (title === "") {
            dialogTitle = {...dialogTitle, display: 'none'}
        }

        if(blur){

            overlayStyle = {...blurStyles.overlayStyle};
            dialogContent = {...dialogContent, ...blurStyles.dialogContent};
            dialogBody = {...dialogBody, ...blurStyles.dialogBody};
            dialogTitle = {...dialogTitle, ...blurStyles.dialogTitle};
            className = className ? className + ' dialogRootBlur' : 'dialogRootBlur';
            dialogRoot = {...dialogRoot, backgroundImage:'url()', backgroundPosition:'center center', backgroundSize:'cover'}

            if(state.backgroundImage){
                const styleBox = (
                    <style dangerouslySetInnerHTML={{
                        __html: [
                            '.react-mui-context div[data-reactroot].dialogRootBlur > div > div.dialogRootBlur:before {',
                            '  background-image: '+state.backgroundImage+';',
                            '  background-size: '+state.backgroundSize+';',
                            '}'
                        ].join('\n')
                    }}>
                    </style>
                );
                modalContent = <span>{styleBox}{modalContent}</span>;
            }

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
                onRequestClose={() => {this.setState({open:false})}}

                contentStyle={dialogContent}
                bodyStyle={dialogBody}
                titleStyle={dialogTitle}
                style={dialogRoot}
                overlayStyle={overlayStyle}
            >{modalContent}</MaterialUI.Dialog>
        );
    }
});
