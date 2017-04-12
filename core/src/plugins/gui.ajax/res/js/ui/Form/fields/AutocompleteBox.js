import FormMixin from '../mixins/FormMixin'
const React = require('react')
const {AutoComplete, MenuItem, RefreshIndicator} = require('material-ui')

export default React.createClass({

    mixins:[FormMixin],

    handleUpdateInput: function(searchText) {
        //this.setState({searchText: searchText});
    },

    handleNewRequest: function(chosenValue) {
        this.onChange(null, chosenValue.key);
    },

    onChoicesLoaded: function(choices){
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
        this.setState({dataSource: dataSource, labels: labels});
    },

    render: function(){

        let displayText = this.state.value;
        if(this.state.labels && this.state.labels[displayText]){
            displayText = this.state.labels[displayText];
        }

        return (
            <div className="pydioform_autocomplete" style={{position:'relative'}}>
                {!this.state.dataSource &&
                    <RefreshIndicator
                        size={30}
                        right={10}
                        top={0}
                        status="loading"
                    />
                }
                {this.state.dataSource &&
                    <AutoComplete
                        fullWidth={true}
                        searchText={displayText}
                        onUpdateInput={this.handleUpdateInput}
                        onNewRequest={this.handleNewRequest}
                        dataSource={this.state.dataSource}
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