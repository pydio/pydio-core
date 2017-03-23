export default React.createClass({

    propTypes:{
        driverName:React.PropTypes.string,
        driverFields:React.PropTypes.array,
        selectedFields:React.PropTypes.array,
        onToggleField:React.PropTypes.func
    },

    toggleField: function(name, e){
        this.props.onToggleField(name, e.currentTarget.checked, this.props.selectedFields);
    },

    render: function(){
        var fields = this.props.driverFields.map(function(f){
            return (
                <div className="menu-entry-toggleable" key={this.props.driverName + '-' + f.name}>
                    <ReactMUI.Checkbox
                        label={f.label}
                        checked={this.props.selectedFields.indexOf(f.name) !== -1}
                        onCheck={this.toggleField.bind(this, f.name)}
                    />
                </div>
            );
        }.bind(this));
        return (
            <div>
                <PydioComponents.PaperEditorNavHeader key="save-k" label="3 - Add / Remove parameters"/>
                {fields}
            </div>
        );
    }

});
