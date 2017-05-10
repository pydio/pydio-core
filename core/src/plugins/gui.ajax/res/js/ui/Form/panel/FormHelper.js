const React = require('react')
import Manager from '../manager/Manager'
const {AsyncComponent} = require('pydio').requireLib('boot')

/**
 * Display a form companion linked to a given input.
 * Props: helperData : contains the pluginId and the whole paramAttributes
 */
export default React.createClass({

    propTypes:{
        helperData:React.PropTypes.object,
        close:React.PropTypes.func.isRequired
    },

    closeHelper:function(){
        this.props.close();
    },

    render: function(){
        let helper;
        if(this.props.helperData){
            const helpersCache = Manager.getHelpersCache();
            const pluginHelperNamespace = helpersCache[this.props.helperData['pluginId']]['namespace'];
            helper = (
                <div>
                    <div className="helper-title">
                        <span className="helper-close mdi mdi-close" onClick={this.closeHelper}></span>
                        Pydio Companion
                    </div>
                    <div className="helper-content">
                        <AsyncComponent
                            {...this.props.helperData}
                            namespace={pluginHelperNamespace}
                            componentName="Helper"
                            paramName={this.props.helperData['paramAttributes']['name']}
                        />
                    </div>
                </div>);
        }
        return <div className={'pydio-form-helper' + (helper?' helper-visible':' helper-empty')} style={{zIndex:1}}>{helper}</div>;
    }

});