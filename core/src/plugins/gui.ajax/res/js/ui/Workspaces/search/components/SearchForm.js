import React, {Component} from 'react'

import FilePreview from '../../FilePreview'
import AdvancedSearch from './AdvancedSearch'

import {Subheader, DropDownMenu, DatePicker, TextField, Toggle, FlatButton} from 'material-ui';

import _ from 'lodash';

class SearchForm extends Component {

    constructor(props) {
        super(props)

        // Create Fake DM
        let basicDataModel = new PydioDataModel(true);
        let rNodeProvider = new EmptyNodeProvider();
        basicDataModel.setAjxpNodeProvider(rNodeProvider);
        const rootNode = new AjxpNode("/", false, '', '', rNodeProvider);
        basicDataModel.setRootNode(rootNode);

        this.state = {
            values: {},
            display: 'closed',
            dataModel: basicDataModel
        }

        this.setMode = _.debounce(this.setMode, 500)
        this.update = _.debounce(this.update, 500)
        this.submit = _.debounce(this.submit, 500)
    }

    componentDidUpdate(prevProps, prevState) {
        if(this.refs.results && this.refs.results.refs.list){
            this.refs.results.refs.list.updateInfiniteContainerHeight();
            FuncUtils.bufferCallback('search_results_resize_list', 550, ()=>{
                try{
                    this.refs.results.refs.list.updateInfiniteContainerHeight();
                }catch(e){}
            });
        }
    }

    setMode(mode) {
        if (mode === 'small' && this.state.display !== 'closed') return // we can only set to small when the previous state was closed

        this.setState({
            display: mode
        })
    }

    update(newValues) {
        let values = {
            ...this.state.values,
            ...newValues
        }

        // Removing null values
        Object.keys(values).forEach((key) => (values[key] == null) && delete values[key]);

        this.setState({
            values
        });
    }

    submit() {
        const {display, values} = this.state
        const {crossWorkspace} = this.props

        let queryString = ''
        const keys = Object.keys(values);
        if (keys.length === 1 && keys[0] === 'basename') {
            queryString = values['basename'];
        } else {
            queryString = keys.map((k) => k + ':' + values[k]).join(' AND ')
        }

        if (queryString === '') {
            return
        }

        const query = {
            get_action : crossWorkspace ? 'multisearch' : 'search',
            query: queryString,
            limit: crossWorkspace ? 5 : (display !== 'small' ? 9 : 100)
        }

        // Refresh data model
        this.setState({
            dataModel: PydioDataModel.RemoteDataModelFactory(query)
        }, () => this.refs.results.reload()) // TODO - we shoudln't need to reload here - SimpleList should handle cases such as those
    }


    render() {

        let renderSecondLine = null, renderIcon = null, elementHeight = 49;
        if (this.state.display !== 'small') {
            elementHeight = PydioComponents.SimpleList.HEIGHT_TWO_LINES + 10;
            renderSecondLine = function(node){
                return <div>{node.getPath()}</div>
            };
            renderIcon = function(node, entryProps = {}){
                return <FilePreview loadThumbnail={!entryProps['parentIsScrolling']} node={node}/>;
            };
        }

        return (
            <div ref="root" className={"top_search_form " + this.state.display}>
                <MainSearch
                    mode={this.state.display}
                    title={this.state.display === 'advanced' ? 'Advanced' : 'Search...'}
                    onOpen={() => this.setMode("small")}
                    onAdvanced={() => this.setMode("advanced")}
                    onClose={() => this.setMode("closed")}
                    onChange={(values) => this.update(values)}
                    onSubmit={() => this.submit()}
                    hintText={this.props.crossWorkspace ? "Search inside all workspaces..." : "Search inside this workspace"}
                />

                {this.state.display === 'advanced' &&
                    <AdvancedSearch
                        {...this.props}
                        value={this.state.values.basename}
                        onChange={(values) => this.update(values)}
                        onSubmit={() => this.submit()}
                    />
                }

                <div className="search-results">
                    <PydioComponents.NodeListCustomProvider
                        ref="results"
                        className={this.state.display !== 'small' ? 'files-list' : null}
                        elementHeight={elementHeight}
                        entryRenderIcon={renderIcon}
                        entryRenderActions={function() {return null}}
                        entryRenderSecondLine={renderSecondLine}
                        presetDataModel={this.state.dataModel}
                        heightAutoWithMax={this.state.display === 'small' ? 500  : 412}
                        nodeClicked={(node)=>{this.props.pydio.goTo(node);this.setState({display:'closed'})}}
                        defaultGroupBy={this.props.crossWorkspace?'repository_id':null}
                        groupByLabel={this.props.crossWorkspace?'repository_display':null}
                    />

                    {this.state.display === 'small' &&
                        <div className="search-more-container">
                            <FlatButton secondary={true} label={"Show more..."} onFocus={() => this.setMode("small")} onTouchTap={() => this.setMode("more")} onClick={() => this.setMode("more")} />
                        </div>
                    }
                </div>

            </div>
        );
    }
}

class MainSearch extends Component {

    static get styles() {
        return {
            main: {
                background: "#ffffff",
                width: "100%",
                height: "36px",
                border: "none"
            },
            input: {
                padding: "0 10px",
                border: 0
            },
            hint: {
                width: "100%",
                padding: "0 10px",
                bottom: 0,
                lineHeight: "36px",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis"
            },
            underline: {
                display: "none"
            }
        }
    }

    constructor(props) {
        super(props)

        this.state = {
            mode: props.mode
        }

        // Making sure we don't send too many requests
        this.onChange = _.debounce(this.onChange, 500)
    }

    componentWillReceiveProps(nextProps) {
        this.setState({
            mode: nextProps.mode
        })
    }

    componentDidUpdate() {
        if (this.state.mode !== 'closed') {
            this.input && this.input.focus()
        }
    }

    onChange(value) {
        this.props.onChange({'basename': value})
        this.props.onSubmit()
    }

    render() {
        const {main, input, hint, underline} = MainSearch.styles

        return (
            <div className="search-input">
                <div className="panel-header">
                    <span className="panel-header-label">{this.props.title}</span>
                    <span className="panel-header-close mdi mdi-close" onClick={this.props.onClose}></span>
                </div>

                {this.props.mode !== 'advanced' &&
                    <TextField
                        ref={(input) => this.input = input}
                        style={main}
                        inputStyle={input}
                        hintStyle={hint}
                        underlineStyle={underline}
                        onFocus={this.props.onOpen}
                        onBlur={this.props.mode === 'small' ? this.props.onClose : null}
                        hintText={this.props.hintText}
                        onChange={(e) => this.onChange(e.target.value)}
                        onKeyPress={(e) => (e.key === 'Enter' ? this.onChange(e.target.value) : null)}
                    />
                }

                <span className="search-advanced-button" onClick={this.props.onAdvanced}>Advanced search</span>
            </div>
        )
    }
}

export default SearchForm
