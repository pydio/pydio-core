const React = require('react')
const ReactMUI = require('material-ui-legacy')
const LangUtils = require('pydio/util/lang')
const PydioApi = require('pydio/http/api')
const {Tabs, Tab, Paper} = require('material-ui')
import GroupSwitchPanel from './GroupSwitchPanel'
import ReplicationPanel from './ReplicationPanel'
import FormManager from '../manager/Manager'


/**
 * Main form constructor
 * @param parameters Set of Pydio StandardForm parameters
 * @param values Set of values (object)
 * @param onParameterChange Trigger unitary function when one form input changes.
 * @param onChange Send all form values onchange, including eventually the removed ones (for dynamic panels)
 * @param disabled
 * @param binary_context a string added to image inputs upload/downlaod operations
 * @param depth  by default 0, subform will see their zDepth increased by one.
 * @param header Add an additional header component (added inside first subpanel)
 * @param footer  Add an additional footer component (added inside last subpanel).
 * @param additionalPanes Add full panes at the top or the bottom
 * @param tabs group form panels in tabs instead of displaying multiple papers.
 * @param setHelperData Pass helper data
 * @param checkHasHelper Function to check if a given parameter has an associated helper.
 */
export default React.createClass({

    _hiddenValues:{},
    _internalValid:null,
    _parametersMetadata:null,

    propTypes:{
        parameters:React.PropTypes.array.isRequired,
        values:React.PropTypes.object,
        onParameterChange:React.PropTypes.func,
        onChange:React.PropTypes.func,
        onValidStatusChange:React.PropTypes.func,
        disabled:React.PropTypes.bool,
        binary_context:React.PropTypes.string,
        depth:React.PropTypes.number,

        /* Display Options */
        header:React.PropTypes.object,
        footer:React.PropTypes.object,
        additionalPanes:React.PropTypes.shape({
            top:React.PropTypes.array,
            bottom:React.PropTypes.array
        }),
        tabs:React.PropTypes.array,
        onTabChange:React.PropTypes.func,
        accordionizeIfGroupsMoreThan:React.PropTypes.number,
        onScrollCallback:React.PropTypes.func,
        limitToGroups:React.PropTypes.array,
        skipFieldsTypes:React.PropTypes.array,

        /* Helper Options */
        setHelperData:React.PropTypes.func,
        checkHasHelper:React.PropTypes.func,
        helperTestFor:React.PropTypes.string

    },

    externallySelectTab:function(index){
        this.setState({tabSelectedIndex: index});
    },

    getInitialState: function(){
        if(this.props.onTabChange) return {tabSelectedIndex:0};
        return {};
    },

    getDefaultProps:function(){
        return { depth:0, values:{} };
    },

    componentWillReceiveProps: function(newProps){
        if(JSON.stringify(newProps.parameters) !== JSON.stringify(this.props.parameters)){
            this._internalValid = null;
            this._hiddenValues = {};
            this._parametersMetadata = {};
        }
    },

    getValues:function(){
        return this.props.values;//LangUtils.mergeObjectsRecursive(this._hiddenValues, this.props.values);
    },

    onParameterChange: function(paramName, newValue, oldValue, additionalFormData=null){
        // Update writeValues
        var newValues = LangUtils.deepCopy(this.getValues());
        if(this.props.onParameterChange) {
            this.props.onParameterChange(paramName, newValue, oldValue, additionalFormData);
        }
        if(additionalFormData){
            if(!this._parametersMetadata) this._parametersMetadata = {};
            this._parametersMetadata[paramName] = additionalFormData;
        }
        newValues[paramName] = newValue;
        var dirty = true;
        this.onChange(newValues, dirty);
    },

    onChange:function(newValues, dirty, removeValues){
        if(this.props.onChange) {
            //newValues = LangUtils.mergeObjectsRecursive(this._hiddenValues, newValues);
            for(var key in this._hiddenValues){
                if(this._hiddenValues.hasOwnProperty(key) && newValues[key] === undefined && (!removeValues || removeValues[key] == undefined)){
                    newValues[key] = this._hiddenValues[key];
                }
            }
            this.props.onChange(newValues, dirty, removeValues);
        }
        this.checkValidStatus(newValues);
    },

    onSubformChange:function(newValues, dirty, removeValues){
        var values = LangUtils.mergeObjectsRecursive(this.getValues(), newValues);
        if(removeValues){
            for(var k in removeValues){
                if(removeValues.hasOwnProperty(k) && values[k] !== undefined){
                    delete values[k];
                    if(this._hiddenValues[k] !== undefined){
                        delete this._hiddenValues[k];
                    }
                }
            }
        }
        this.onChange(values, dirty, removeValues);
    },

    onSubformValidStatusChange:function(newValidValue, failedMandatories){
        if((newValidValue !== this._internalValid || this.props.forceValidStatusCheck) && this.props.onValidStatusChange) {
            this.props.onValidStatusChange(newValidValue, failedMandatories);
        }
        this._internalValid = newValidValue;
    },

    applyButtonAction: function(parameters, callback){
        if(this.props.applyButtonAction){
            this.props.applyButtonAction(parameters, callback);
            return;
        }
        parameters = LangUtils.mergeObjectsRecursive(parameters, this.getValuesForPOST(this.getValues()));
        PydioApi.getClient().request(parameters, callback);
    },

    getValuesForPOST:function(values, prefix='DRIVER_OPTION_'){
        return FormManager.getValuesForPOST(this.props.parameters, values, prefix, this._parametersMetadata);
    },

    checkValidStatus:function(values){
        var failedMandatories = [];
        this.props.parameters.map(function(p){
            if (['string', 'textarea', 'password', 'integer'].indexOf(p.type) > -1 && (p.mandatory == "true" || p.mandatory === true)) {
                if(!values || !values.hasOwnProperty(p.name) || values[p.name] === undefined || values[p.name] === ""){
                    failedMandatories.push(p);
                }
            }
            if( ( p.type === 'valid-password' ) && this.refs['form-element-' + p.name]){
                if(!this.refs['form-element-' + p.name].isValid()){
                    failedMandatories.push(p);
                }
            }
        }.bind(this));
        var previousValue, newValue;
        previousValue = this._internalValid;//(this._internalValid !== undefined ? this._internalValid : true);
        newValue = failedMandatories.length ? false : true;
        if((newValue !== this._internalValid || this.props.forceValidStatusCheck) && this.props.onValidStatusChange) {
            this.props.onValidStatusChange(newValue, failedMandatories);
        }
        this._internalValid = newValue;
    },

    componentDidMount:function(){
        this.checkValidStatus(this.props.values);
    },

    componentWillReceiveProps: function(nextProps){
        if(nextProps.values && nextProps.values !== this.props.values){
            this.checkValidStatus(nextProps.values);
        }
    },

    renderGroupHeader:function(groupLabel, accordionize, index, active){

        var properties = { key: 'group-' + groupLabel };
        if(accordionize){
            var current = (this.state && this.state.currentActiveGroup) ? this.state.currentActiveGroup : null;
            properties['className'] = 'group-label-' + (active ? 'active' : 'inactive');
            properties['onClick'] = function(){
                this.setState({currentActiveGroup:(current != index ? index : null)});
            }.bind(this);
            groupLabel = [<span key="toggler" className={"group-active-toggler icon-angle-" + (current == index ? 'down' : 'right') }></span>, groupLabel];
        }

        return React.createElement(
            'h' + (3 + this.props.depth),
            properties,
            groupLabel
        );

    },

    render:function(){
        var allGroups = [];
        var values = this.getValues();
        var groupsOrdered = ['__DEFAULT__'];
        allGroups['__DEFAULT__'] = {FIELDS:[]};
        var replicationGroups = {};

        this.props.parameters.map(function(attributes){

            var type = attributes['type'];
            if(this.props.skipFieldsTypes && this.props.skipFieldsTypes.indexOf(type) > -1){
                return;
            }
            var paramName = attributes['name'];
            var field;
            if(attributes['group_switch_name']) return;

            var group = attributes['group'] || '__DEFAULT__';
            if(!allGroups[group]){
                groupsOrdered.push(group);
                allGroups[group] = {FIELDS:[], LABEL:group};
            }

            var repGroup = attributes['replicationGroup'];
            if(repGroup) {

                if (!replicationGroups[repGroup]) {
                    replicationGroups[repGroup] = {
                        PARAMS: [],
                        GROUP: group,
                        POSITION: allGroups[group].FIELDS.length
                    };
                    allGroups[group].FIELDS.push('REPLICATION:' + repGroup);
                }
                // Copy
                var repAttr = LangUtils.deepCopy(attributes);
                delete repAttr['replicationGroup'];
                delete repAttr['group'];
                replicationGroups[repGroup].PARAMS.push(repAttr);

            }else{

                if(type.indexOf("group_switch:") === 0){

                    field = (
                        <GroupSwitchPanel
                            {...this.props}
                            onChange={this.onSubformChange}
                            paramAttributes={attributes}
                            parameters={this.props.parameters}
                            values={this.props.values}
                            key={paramName}
                            onScrollCallback={null}
                            limitToGroups={null}
                            onValidStatusChange={this.onSubformValidStatusChange}
                        />
                    );

                }else if(attributes['type'] !== 'hidden'){

                    var helperMark;
                    if(this.props.setHelperData && this.props.checkHasHelper && this.props.checkHasHelper(attributes['name'], this.props.helperTestFor)){
                        var showHelper = function(){
                            this.props.setHelperData({
                                paramAttributes:attributes,
                                values:values,
                                postValues:this.getValuesForPOST(values),
                                applyButtonAction:this.applyButtonAction
                            }, this.props.helperTestFor);
                        }.bind(this);
                        helperMark = <span className="icon-question-sign" onClick={showHelper}></span>;
                    }
                    var mandatoryMissing = false;
                    var classLegend = "form-legend";
                    if(attributes['errorText']) {
                        classLegend = "form-legend mandatory-missing";
                    }else if(attributes['warningText']){
                        classLegend = "form-legend warning-message";
                    }else if( attributes['mandatory'] && (attributes['mandatory'] === "true" || attributes['mandatory'] === true) ){
                        if(['string', 'textarea', 'image', 'integer'].indexOf(attributes['type']) !== -1 && !values[paramName]){
                            mandatoryMissing = true;
                            classLegend = "form-legend mandatory-missing";
                        }
                    }

                    var props = {
                        ref:"form-element-" + paramName,
                        attributes:attributes,
                        name:paramName,
                        value:values[paramName],
                        onChange:function(newValue, oldValue, additionalFormData){
                            this.onParameterChange(paramName, newValue, oldValue, additionalFormData);
                        }.bind(this),
                        disabled:this.props.disabled || attributes['readonly'],
                        multiple:attributes['multiple'],
                        binary_context:this.props.binary_context,
                        displayContext:'form',
                        applyButtonAction:this.applyButtonAction,
                        errorText:mandatoryMissing?'Field cannot be empty':(attributes.errorText?attributes.errorText:null)
                    };

                    field = (
                        <div key={paramName} className={'form-entry-' + attributes['type']}>
                            {FormManager.createFormElement(props)}
                            <div className={classLegend}>{attributes['warningText'] ? attributes['warningText'] : attributes['description']} {helperMark}</div>
                        </div>
                    );
                }else{

                    this._hiddenValues[paramName] = (values[paramName] !== undefined ? values[paramName] : attributes['default']);

                }

                if(field) {
                    allGroups[group].FIELDS.push(field);
                }

            }


        }.bind(this));

        for(var rGroup in replicationGroups){
            if (!replicationGroups.hasOwnProperty(rGroup)) continue;
            var rGroupData = replicationGroups[rGroup];
            allGroups[rGroupData.GROUP].FIELDS[rGroupData.POSITION] = (
                <ReplicationPanel
                    {...this.props}
                    key={"replication-group-" + rGroupData.PARAMS[0].name}
                    onChange={this.onSubformChange}
                    onParameterChange={null}
                    values={this.getValues()}
                    depth={this.props.depth+1}
                    parameters={rGroupData.PARAMS}
                    applyButtonAction={this.applyButtonAction}
                    onScrollCallback={null}
                />
            );
        }

        var groupPanes = [];
        var accordionize = (this.props.accordionizeIfGroupsMoreThan && groupsOrdered.length > this.props.accordionizeIfGroupsMoreThan);
        var currentActiveGroup = (this.state && this.state.currentActiveGroup) ? this.state.currentActiveGroup : 0;
        groupsOrdered.map(function(g, gIndex) {
            if(this.props.limitToGroups && this.props.limitToGroups.indexOf(g) === -1){
                return;
            }
            var header, gData = allGroups[g];
            var className = 'pydio-form-group', active = false;
            if(accordionize){
                active = (currentActiveGroup == gIndex);
                if(gIndex == currentActiveGroup) className += ' form-group-active';
                else className += ' form-group-inactive';
            }
            if (!gData.FIELDS.length) return;
            if (gData.LABEL && !(this.props.skipFieldsTypes && this.props.skipFieldsTypes.indexOf('GroupHeader') > -1)) {
                header = this.renderGroupHeader(gData.LABEL, accordionize, gIndex, active);
            }
            if(this.props.depth == 0){
                className += ' z-depth-1';
                groupPanes.push(
                    <Paper className={className} key={'pane-'+g}>
                        {gIndex==0 && this.props.header? this.props.header: null}
                        {header}
                        <div>
                            {gData.FIELDS}
                        </div>
                        {gIndex==groupsOrdered.length-1 && this.props.footer? this.props.footer: null}
                    </Paper>
                );
            }else{
                groupPanes.push(
                    <div className={className} key={'pane-'+g}>
                        {gIndex==0 && this.props.header? this.props.header: null}
                        {header}
                        <div>
                            {gData.FIELDS}
                        </div>
                        {gIndex==groupsOrdered.length-1 && this.props.footer? this.props.footer: null}
                    </div>
                );
            }
        }.bind(this));
        if(this.props.additionalPanes){
            let otherPanes = {top:[], bottom:[]};
            const depth = this.props.depth;
            let index = 0;
            for(let k in otherPanes){
                if(!otherPanes.hasOwnProperty(k)) continue;
                if(this.props.additionalPanes[k]){
                    this.props.additionalPanes[k].map(function(p){
                        if(depth == 0){
                            otherPanes[k].push(
                                <Paper className="pydio-form-group additional" key={'other-pane-'+index}>{p}</Paper>
                            );
                        }else{
                            otherPanes[k].push(
                                <div className="pydio-form-group additional" key={'other-pane-'+index}>{p}</div>
                            );
                        }
                        index++;
                    });
                }
            }
            groupPanes = otherPanes['top'].concat(groupPanes).concat(otherPanes['bottom']);
        }

        if(this.props.tabs){
            const className = this.props.className;
            let initialSelectedIndex = 0;
            let i = 0;
            const tabs = this.props.tabs.map(function(tDef){
                const label = tDef['label'];
                const groups = tDef['groups'];
                if(tDef['selected']){
                    initialSelectedIndex = i;
                }
                const panes = groups.map(function(gId){
                    if(groupPanes[gId]){
                        return groupPanes[gId];
                    }else{
                        return null;
                    }
                });
                i++;
                return(
                    <Tab label={label}
                         key={label}
                         value={this.props.onTabChange ? i - 1  : undefined}>
                        <div className={(className?className+' ':' ') + 'pydio-form-panel' + (panes.length % 2 ? ' form-panel-odd':'')}>
                            {panes}
                        </div>
                    </Tab>
                );
            }.bind(this));
            if(this.state.tabSelectedIndex !== undefined){
                initialSelectedIndex = this.state.tabSelectedIndex;
            }
            return (
                <div className="layout-fill vertical-layout tab-vertical-layout">
                    <Tabs ref="tabs"
                          initialSelectedIndex={initialSelectedIndex}
                          value={this.props.onTabChange ? initialSelectedIndex : undefined}
                          onChange={this.props.onTabChange ? (i) => {this.setState({tabSelectedIndex:i});this.props.onTabChange(i)} : undefined}
                    >
                        {tabs}
                    </Tabs>
                </div>
            );

        }else{
            return (
                <div className={(this.props.className?this.props.className+' ':' ') + 'pydio-form-panel' + (groupPanes.length % 2 ? ' form-panel-odd':'')} onScroll={this.props.onScrollCallback}>
                    {groupPanes}
                </div>
            );
        }


    }

});