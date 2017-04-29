import React, {Component} from 'react'

import FilePreview from '../../FilePreview'
import AdvancedSearch from './AdvancedSearch'

import {Subheader, DropDownMenu, DatePicker, TextField, Toggle, FlatButton, CircularProgress} from 'material-ui';

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
            dataModel: basicDataModel,
            loading: false
        }

        this.setMode = _.debounce(this.setMode, 250);
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

        // Refresh data model
        const newDM = PydioDataModel.RemoteDataModelFactory({
            get_action : crossWorkspace ? 'multisearch' : 'search',
            query: queryString,
            limit: crossWorkspace ? 5 : (display !== 'small' ? 9 : 100),
            connexion_discrete: true
        });
        newDM.getRootNode().observeOnce("loaded", () => {
            this.setState({loading: false});
        });
        this.setState({
            loading     : true,
            dataModel   : newDM
        }, () => {
            this.refs.results.reload();
        });
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

        const nodeClicked = (node)=>{
            console.log(node);
            this.props.pydio.goTo(node);
            this.setMode('closed');
        };

        return (
            <div ref="root" className={"top_search_form " + this.state.display} style={this.props.style}>
                <MainSearch
                    mode={this.state.display}
                    title={this.state.display === 'advanced' ? 'Advanced' : 'Search...'}
                    onOpen={() => this.setMode("small")}
                    onAdvanced={() => this.setMode("advanced")}
                    onClose={() => this.setMode("closed")}
                    onChange={(values) => this.update(values)}
                    onSubmit={() => this.submit()}
                    hintText={this.props.crossWorkspace ? "Search inside all workspaces..." : "Search inside this workspace"}
                    loading={this.state.loading}
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
                        openCollection={nodeClicked}
                        nodeClicked={nodeClicked}
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
                border: "none",
                transition:'all .25s'
            },
            input: {
                padding: "0 10px",
                border: 0
            },
            hint: {
                transition:'all .25s',
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
            },
            closedMode: {
                main: {
                    background: 'rgba(255,255,255,.1)',
                    boxShadow: 'rgba(0, 0, 0, 0.117647) 0px 1px 6px, rgba(0, 0, 0, 0.117647) 0px 1px 4px',
                    borderRadius: 2
                },
                input: {
                    color: 'rgba(255, 255, 255, 0.64)',
                },
                hint: {
                    color: 'rgba(255, 255, 255, 0.64)',
                }
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
        const {mode, onOpen, onClose, hintText, loading} = this.props;
        let {main, input, hint, underline, closedMode} = MainSearch.styles
        if(mode === 'closed'){
            main = {...main, ...closedMode.main};
            hint = {...hint, ...closedMode.hint};
            input = {...input, ...closedMode.input};
        }

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
                        onFocus={onOpen}
                        onBlur={mode === 'small' ? onClose : null}
                        hintText={<span><span className="mdi mdi-magnify"/> {hintText}</span>}
                        onChange={(e,v) => this.onChange(v)}
                        onKeyPress={(e) => (e.key === 'Enter' ? this.onChange(e.target.value) : null)}
                    />
                }

                {loading &&
                    <div style={{position:'absolute', right: 13, top: 13}} ><CircularProgress size={20} thickness={3}/></div>
                }

                <span className="search-advanced-button" onClick={this.props.onAdvanced}>Advanced search</span>
            </div>
        )
    }
}

export default SearchForm
