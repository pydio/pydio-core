import {compose} from 'redux';

import AsyncComponent from './AsyncComponent'
import BackgroundImage from './BackgroundImage'

// Animations
const originStyles = {opacity: 0}
const targetStyles = {opacity: 1}
const enterAnimation = {stiffness: 350, damping: 28}

let Template = ({style, id, pydio, children}) => {
    const userIsActive = ()=>{pydio.notify('user_activity')};
    return (
        <div
            style={style}
            id={id}
            onMouseMove={userIsActive}
            onMouseOver={userIsActive}
            onKeyDown={userIsActive}>{children}</div>
    );
}

Template = compose (
    PydioHOCs.Animations.makeTransition(originStyles, targetStyles, enterAnimation)
)(Template)

class TemplateBuilder extends React.Component {

    render() {

        let pydio = this.props.pydio;
        let containerId = this.props.containerId;

        let components = [];
        let style = {
            display: "flex",
            flex: 1
        };

        if(this.props.imageBackgroundFromConfigs){
            if(BackgroundImage.SESSION_IMAGE){
                style = BackgroundImage.SESSION_IMAGE;
            }else{
                style = BackgroundImage.getImageBackgroundFromConfig(this.props.imageBackgroundFromConfigs);
                BackgroundImage.SESSION_IMAGE = style;
            }
        }

        let parts = XMLUtils.XPathSelectNodes(pydio.getXmlRegistry(), "client_configs/template_part[@component]");
        parts.map(function(node){
            if(node.getAttribute("theme") && node.getAttribute("theme") != pydio.Parameters.get("theme")){
                return;
            }
            if(containerId !== node.getAttribute("ajxpId")){
                return;
            }

            let namespace = node.getAttribute("namespace");
            let componentName = node.getAttribute("component");


            let props = {};
            if(node.getAttribute("props")){
                props = JSON.parse(node.getAttribute("props"));
            }
            props['pydio'] = pydio;

            components.push(
                <AsyncComponent
                    key={namespace}
                    namespace={namespace}
                    componentName={componentName}
                    noLoader={true}
                    style={style}
                    {...props}
                />
            );
        }.bind(this));

        return <Template style={style} id={this.props.containerId} pydio={pydio}>{components}</Template>
    }
}

TemplateBuilder.propTypes = {
    pydio: React.PropTypes.instanceOf(Pydio),
    containerId:React.PropTypes.string
}



export default TemplateBuilder
