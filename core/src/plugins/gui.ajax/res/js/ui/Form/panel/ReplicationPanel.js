const React = require('react')
const ReactMUI = require('material-ui-legacy')
import FormPanel from './FormPanel'

/**
 * Sub form replicating itself (+/-)
 */
export default React.createClass({

    propTypes:{
        parameters:React.PropTypes.array.isRequired,
        values:React.PropTypes.object,
        onChange:React.PropTypes.func,
        disabled:React.PropTypes.bool,
        binary_context:React.PropTypes.string,
        depth:React.PropTypes.number
    },

    buildSubValue:function(values, index=0){
        var subVal;
        var suffix = index==0?'':'_'+index;
        this.props.parameters.map(function(p){
            var pName = p['name'];
            if(values[pName+suffix] !== undefined){
                if(!subVal) subVal = {};
                subVal[pName] = values[pName+suffix];
            }
        });
        return subVal || false;
    },

    indexedValues:function(rowsArray){
        var index = 0;
        var values = {};
        rowsArray.map(function(row){
            var suffix = index==0?'':'_'+index;
            for(var p in row){
                if(!row.hasOwnProperty(p)) continue;
                values[p+suffix] = row[p];
            }
            index ++;
        });
        return values;
    },

    indexValues:function(rowsArray, removeLastRow){
        var indexed = this.indexedValues(rowsArray);
        if(this.props.onChange){
            if(removeLastRow){
                var lastRow = {}, nextIndex = rowsArray.length-1;
                this.props.parameters.map(function(p){
                    lastRow[p['name'] + '_' + nextIndex] = '';
                });
                this.props.onChange(indexed, true, lastRow);
            }else{
                this.props.onChange(indexed, true);
            }
        }
    },

    instances:function(){
        // Analyze current value to grab number of rows.
        var rows = [], subVal, index = 0;
        while(subVal = this.buildSubValue(this.props.values, index)){
            index ++;
            rows.push(subVal);
        }
        if(!rows.length){
            var emptyValue={};
            this.props.parameters.map(function(p) {
                emptyValue[p['name']] = p['default'] || '';
            });
            rows.push(emptyValue);
        }
        return rows;
    },

    addRow:function(){
        var newValue={}, currentValues = this.instances();
        this.props.parameters.map(function(p) {
            newValue[p['name']] = p['default'] || '';
        });
        currentValues.push(newValue);
        this.indexValues(currentValues);
    },

    removeRow:function(index){
        var instances = this.instances();
        var removeInst = instances[index];
        instances = LangUtils.arrayWithout(this.instances(), index);
        instances.push(removeInst);
        this.indexValues(instances, true);
    },

    swapRows:function(i,j){
        var instances = this.instances();
        var tmp = instances[j];
        instances[j] = instances[i];
        instances[i] = tmp;
        this.indexValues(instances);
    },

    onChange:function(index, newValues, dirty){
        var instances = this.instances();
        instances[index] = newValues;
        this.indexValues(instances);
    },

    onParameterChange:function(index, paramName, newValue, oldValue){
        var instances = this.instances();
        instances[index][paramName] = newValue;
        this.indexValues(instances);
    },

    render:function(){
        var replicationTitle, replicationDescription;
        var firstParam = this.props.parameters[0];
        replicationTitle = firstParam['replicationTitle'] || firstParam['label'];
        replicationDescription = firstParam['replicationDescription'] || firstParam['description'];

        var instances = this.instances();
        var rows = instances.map(function(subValues, index){
            var buttons = [];
            if(instances.length > 1){
                if(index > 0){
                    var upF = function(){ this.swapRows(index, index-1) }.bind(this);
                    buttons.push(<ReactMUI.IconButton key="up" iconClassName="icon-caret-up" onClick={upF}/>);
                }
                if(index < instances.length -1){
                    var downF = function(){ this.swapRows(index, index+1) }.bind(this);
                    buttons.push(<ReactMUI.IconButton key="down" iconClassName="icon-caret-down" onClick={downF}/>);
                }
            }
            if(index != 0 || instances.length > 1){
                var removeF = function(){ this.removeRow(index); }.bind(this);
                buttons.push(<ReactMUI.IconButton key="remove" iconClassName="icon-remove-sign" onClick={removeF}/>);
            }
            if(!buttons.length){
                buttons.push(<ReactMUI.IconButton key="remove" className="disabled" iconClassName="icon-remove-sign" disabled={true}/>);
            }
            var actionBar = (
                <div className="replicable-action-bar">{buttons}</div>
            );
            var onChange = function(values, dirty){ this.onChange(index, values, dirty); }.bind(this);
            var onParameterChange = function(paramName, newValue, oldValue){ this.onParameterChange(index, paramName, newValue, oldValue); }.bind(this);
            return (
                <FormPanel
                    {...this.props}
                    tabs={null}
                    key={index}
                    values={subValues}
                    onChange={null}
                    onParameterChange={onParameterChange}
                    header={actionBar}
                    className="replicable-group"
                    depth={this.props.depth}
                />
            );
        }.bind(this));
        return (
            <div className="replicable-field">
                <div className="title-bar">
                    <ReactMUI.IconButton key="add" style={{float:'right'}} iconClassName="icon-plus" tooltip="Add value" onClick={this.addRow}/>
                    <div className="title">{replicationTitle}</div>
                    <div className="legend">{replicationDescription}</div>
                </div>
                {rows}
            </div>

        );
    }

});