import FormMixin from '../mixins/FormMixin'
const React = require('react')
// @TODO ; REPLACE AUTOSUGGEST BY MATERIAL UI COMPLETER

export default React.createClass({

    mixins:[FormMixin],

    onSuggestionSelected: function(value, event){
        this.onChange(event, value);
    },

    getInitialState:function(){
        return {loading : 0};
    },

    suggestionLoader:function(input, callback) {

        this.setState({loading:true});
        let values = {};
        if(this.state.choices){
            this.state.choices.forEach(function(v){
                if(v.indexOf(input) === 0){
                    values[v] = v;
                }
            });
        }
        callback(null, LangUtils.objectValues(values));
        this.setState({loading:false});

    },

    getSuggestions(input, callback){
        FuncUtils.bufferCallback('suggestion-loader-search', 350, function(){
            this.suggestionLoader(input, callback);
        }.bind(this));
    },

    suggestionValue: function(suggestion){
        return '';
    },

    renderSuggestion(value){
        return <span>{value}</span>;
    },

    render: function(){

        const inputAttributes = {
            id: 'pydioform-autosuggest',
            name: 'pydioform-autosuggest',
            className: 'react-autosuggest__input',
            placeholder: this.props.attributes['label'],
            value: this.state.value   // Initial value
        };
        return (
            <div className="pydioform_autocomplete">
                <span className={"suggest-search icon-" + (this.state.loading ? 'refresh rotating' : 'search')}/>
                <ReactAutoSuggest
                    ref="autosuggest"
                    cache={true}
                    showWhen = {input => true }
                    inputAttributes={inputAttributes}
                    suggestions={this.getSuggestions}
                    suggestionRenderer={this.renderSuggestion}
                    suggestionValue={this.suggestionValue}
                    onSuggestionSelected={this.onSuggestionSelected}
                />
            </div>

        );
    }

});