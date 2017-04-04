import FormMixin from '../mixins/FormMixin'
const React = require('react')
const ReactMUI = require('material-ui-legacy')
// @TODO USE MaterialUI SelectBox or AutoCompleter
/**
 * Select box input conforming to Pydio standard form parameter.
 */
export default React.createClass({
    mixins:[FormMixin],

    getDefaultProps:function(){
        return {
            skipBufferChanges:true
        };
    },

    onDropDownChange:function(event, index, item){
        this.onChange(event, item.payload);
        this.toggleEditMode();
    },

    onMultipleSelectChange:function(joinedValue, arrayValue){
        this.onChange(null, joinedValue);
        this.toggleEditMode();
    },

    render:function(){
        let currentValue = this.state.value;
        let currentValueIndex = 0, index=0;
        let menuItems = [], multipleOptions = [], mandatory = true;
        if(!this.props.attributes['mandatory'] || this.props.attributes['mandatory'] != "true"){
            mandatory = false;
            menuItems.unshift({payload:-1, text: this.props.attributes['label'] +  '...'});
            index ++;
        }
        let itemsMap = this.state.choices;
        itemsMap.forEach(function(value, key){
            if(currentValue == key) currentValueIndex = index;
            menuItems.push({payload:key, text:value});
            multipleOptions.push({value:key, label:value});
            index ++;
        });
        if((this.isDisplayGrid() && !this.state.editMode) || this.props.disabled){
            let value = this.state.value;
            if(itemsMap.get(value)) value = itemsMap.get(value);
            return (
                <div
                    onClick={this.props.disabled?function(){}:this.toggleEditMode}
                    className={value?'':'paramValue-empty'}>
                    {!value?'Empty':value} &nbsp;&nbsp;<span className="icon-caret-down"></span>
                </div>
            );
        } else {
            let hasValue = false;
            if(this.props.multiple && this.props.multiple == true){
                if(typeof currentValue == "string"){
                    currentValue = currentValue.split(",");
                }
                hasValue = currentValue.length ? true: false;
                return (
                    <span className={"drop-down-with-floating-label multiple has-value"}>
                        <label className="drop-down-floating-label">{this.props.attributes.label}</label>
                        <ReactSelect
                            options={multipleOptions}
                            value={currentValue}
                            name="test"
                            delimiter=","
                            multi={true}
                            onChange={this.onMultipleSelectChange}
                        />
                    </span>
                );
            }else{
                hasValue = currentValueIndex > 0 || mandatory;
                return(
                    <span className={"drop-down-with-floating-label" + (hasValue?" has-value":"")}>
                        <label className="drop-down-floating-label">{this.props.attributes.label}</label>
                        <ReactMUI.DropDownMenu
                            menuItems={menuItems}
                            onChange={this.onDropDownChange}
                            selectedIndex={currentValueIndex}
                            autoWidth={false}
                            className={this.props.className}
                        />
                    </span>
                );
            }
        }
    }
});