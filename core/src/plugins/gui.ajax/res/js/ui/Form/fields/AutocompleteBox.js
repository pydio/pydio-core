import FormMixin from '../mixins/FormMixin'
const React = require('react')
const {AutoComplete, MenuItem, RefreshIndicator} = require('material-ui')
import FieldWithChoices from '../mixins/FieldWithChoices'

let AutocompleteBox = React.createClass({

    mixins:[FormMixin],

    handleUpdateInput: function(searchText) {
        //this.setState({searchText: searchText});
    },

    handleNewRequest: function(chosenValue) {
        this.onChange(null, chosenValue.key);
    },

    render: function(){

        const {choices} = this.props;
        let dataSource = [];
        let labels = {};
        choices.forEach((choice, key) => {
            dataSource.push({
                key         : key,
                text        : choice,
                value       : <MenuItem>{choice}</MenuItem>
            });
            labels[key] = choice;
        });

        let displayText = this.state.value;
        if(labels && labels[displayText]){
            displayText = labels[displayText];
        }

        return (
            <div className="pydioform_autocomplete" style={{position:'relative'}}>
                {!dataSource.length &&
                    <RefreshIndicator
                        size={30}
                        right={10}
                        top={0}
                        status="loading"
                    />
                }
                {dataSource.length &&
                    <AutoComplete
                        fullWidth={true}
                        searchText={displayText}
                        onUpdateInput={this.handleUpdateInput}
                        onNewRequest={this.handleNewRequest}
                        dataSource={dataSource}
                        floatingLabelText={this.props.attributes['label']}
                        filter={(searchText, key) => (key.toLowerCase().indexOf(searchText.toLowerCase()) === 0)}
                        openOnFocus={true}
                        menuProps={{maxHeight: 200}}
                    />
                }
            </div>

        );
    }

});

AutocompleteBox = FieldWithChoices(AutocompleteBox);
export {AutocompleteBox as default}