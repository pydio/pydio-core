const {Checkbox} = require('material-ui')

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
                <div key={this.props.driverName + '-' + f.name} style={{paddingTop:6,paddingBottom:6}}>
                    <Checkbox
                        label={f.label}
                        checked={this.props.selectedFields.indexOf(f.name) !== -1}
                        onCheck={this.toggleField.bind(this, f.name)}
                    />
                </div>
            );
        }.bind(this));
        return (
            <div style={this.props.style}>
                {fields}
            </div>
        );
    }

});
