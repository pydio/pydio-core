const {Component, PropTypes} = require('react')

/**
 * Ready to use Form + Result List for search users
 */
class SearchForm extends Component{

    constructor(props, context){
        super(props.context);
        this.state = {value: ''};
    }

    search(){
        this.props.onSearch(this.state.value);
    }

    onChange(event, value){
        this.setState({value: value});
        FuncUtils.bufferCallback('search_users_list', 300, this.search.bind(this) );
    }

    render(){

        return (
            <div style={{minWidth:320, ...this.props.style}}>
                <MaterialUI.TextField
                    fullWidth={true}
                    value={this.state.value}
                    onChange={this.onChange.bind(this)}
                    hintText={this.props.searchLabel}
                />
            </div>
        );

    }

}

SearchForm.propTypes = {
    /**
     * Label displayed in the search field
     */
    searchLabel     : PropTypes.string.isRequired,
    /**
     * Callback triggered to search
     */
    onSearch        : PropTypes.func.isRequired,
    /**
     * Will be appended to the root element
     */
    style           : PropTypes.object
};

export {SearchForm as default}