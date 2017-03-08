export default React.createClass({

    propTypes:{
        dataModel:React.PropTypes.instanceOf(PydioDataModel),
        options:React.PropTypes.object,
        onBadgeIncrease: React.PropTypes.func,
        onBadgeChange: React.PropTypes.func
    },

    getInitialState:function(){
        return {value:''};
    },

    componentDidMount:function(){
        let options = this.props.options;
        let dm = this.props.dataModel;
        let newValue = '';
        this._observer = function(){
            switch (options.property){
                case "root_children":
                    var l = dm.getRootNode().getChildren().size;
                    newValue = l ? l : 0;
                    break;
                case "root_label":
                    newValue = dm.getRootNode().getLabel();
                    break;
                case "root_children_empty":
                    var cLength = dm.getRootNode().getChildren().size;
                    newValue = !cLength?options['emptyMessage']:'';
                    break;
                case "metadata":
                    if(options['metadata_sum']){
                        newValue = 0;
                        dm.getRootNode().getChildren().forEach(function(c){
                            if(c.getMetadata().get(options['metadata_sum'])) newValue += parseInt(c.getMetadata().get(options['metadata_sum']));
                        });
                    }
                    break;
                default:
                    break;
            }
            let prevValue = this.state.value;
            if(newValue && newValue !== prevValue){
                if(Object.isNumber(newValue) && this.props.onBadgeIncrease){
                    if(prevValue !== '' && newValue > prevValue) this.props.onBadgeIncrease(newValue, prevValue ? prevValue : 0, this.props.dataModel);
                }
            }
            if(this.props.onBadgeChange){
                this.props.onBadgeChange(newValue, prevValue, this.props.dataModel);
            }
            this.setState({value: newValue});
        }.bind(this);
        dm.getRootNode().observe("loaded", this._observer);
    },

    componentWillUnmount:function(){
        this.props.dataModel.stopObserving("loaded", this._observer);
    },

    render:function(){
        if(!this.state.value) {
            return null;
        } else {
            return (<span className={this.props.options['className']}>{this.state.value}</span>);
        }
    }

});

