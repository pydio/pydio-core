import React, {Component} from 'react'

import FilePreview from '../../FilePreview'
import AdvancedSearch from './AdvancedSearch'
import Textfit from 'react-textfit'
import Pydio from 'pydio'
import LangUtils from 'pydio/util/lang'
const {EmptyStateView} = Pydio.requireLib('components')

import {Paper, Subheader, FontIcon, TextField, FlatButton, CircularProgress, IconMenu, MenuItem, IconButton, DropDownMenu} from 'material-ui';

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
            empty: true,
            loading: false,
            searchScope: 'folder'
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
        const {display, values, searchScope} = this.state
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
        let dmParams = {
            get_action : crossWorkspace || searchScope === 'all' ? 'multisearch' : 'search',
            query: queryString,
            limit: (crossWorkspace  || searchScope === 'all') ? 5 : (display === 'small' ? 9 : 100),
            connexion_discrete: true,
        };
        if(searchScope === 'folder'){
            dmParams.current_dir = this.props.pydio.getContextHolder().getContextNode().getPath();
        }
        const newDM = PydioDataModel.RemoteDataModelFactory(dmParams);
        newDM.getRootNode().observeOnce("loaded", () => {
            this.setState({loading: false});
        });
        this.setState({
            loading     : true,
            dataModel   : newDM,
            empty       : false
        }, () => {
            this.refs.results.reload();
        });
    }


    render() {

        const {crossWorkspace, pydio} = this.props;
        const {searchScope, display, loading, dataModel, empty, values} = this.state;

        let renderSecondLine = null, renderIcon = null, elementHeight = 49;
        if (display !== 'small') {
            elementHeight = PydioComponents.SimpleList.HEIGHT_TWO_LINES + 10;
            renderSecondLine = (node) => {
                let path = node.getPath();
                if(searchScope === 'folder'){
                    const crtFolder = pydio.getContextHolder().getContextNode().getPath();
                    if(path.indexOf(crtFolder) === 0){
                        path = './' + LangUtils.trimLeft(path.substr(crtFolder.length), '/');
                    }
                }
                return <div>{path}</div>
            };
            renderIcon = (node, entryProps = {}) => {
                return <FilePreview loadThumbnail={!entryProps['parentIsScrolling']} node={node}/>;
            };
        }

        const nodeClicked = (node)=>{
            pydio.goTo(node);
            this.setMode('closed');
        };

        const searchScopeChanged = (value) =>{
            if(display === 'small') {
                setTimeout(()=>this.setMode('small'), 250);
            }
            this.setState({searchScope:value});
            this.submit();
        };

        let style = this.props.style;
        let zDepth = 2;
        if(display === 'closed'){
            zDepth = 0;
            style = {...style, backgroundColor: 'transparent'};
        }

        return (
            <Paper ref="root" zDepth={zDepth} className={"top_search_form " + display} style={style}>
                <MainSearch
                    mode={display}
                    title={display === 'advanced' ? 'Advanced Search' : null}
                    onOpen={() => this.setMode("small")}
                    showAdvanced={!this.props.crossWorkspace}
                    onAdvanced={() => this.setMode("advanced")}
                    onClose={() => this.setMode("closed")}
                    onMore={() => this.setMode("more")}
                    onChange={(values) => this.update(values)}
                    onSubmit={() => this.submit()}
                    hintText={this.props.crossWorkspace || searchScope === 'all' ? "Search inside all workspaces..." : "Search ..."}
                    loading={loading}
                    scopeSelectorProps={this.props.crossWorkspace ? null : {
                        value:searchScope,
                        onChange:searchScopeChanged
                    }}
                />
                {display === 'advanced' &&
                    <AdvancedSearch
                        {...this.props}
                        value={values.basename}
                        onChange={(values) => this.update(values)}
                        onSubmit={() => this.submit()}
                    />
                }

                <div className="search-results">
                    {empty &&
                        <EmptyStateView
                            iconClassName=""
                            primaryTextId="Start typing to search"
                            style={{minHeight: 180, backgroundColor: 'transparent'}}
                        />
                    }
                    <PydioComponents.NodeListCustomProvider
                        ref="results"
                        className={display !== 'small' ? 'files-list' : null}
                        elementHeight={elementHeight}
                        entryRenderIcon={renderIcon}
                        entryRenderActions={function() {return null}}
                        entryRenderSecondLine={renderSecondLine}
                        presetDataModel={dataModel}
                        heightAutoWithMax={display === 'small' ? 500  : 412}
                        openCollection={nodeClicked}
                        nodeClicked={nodeClicked}
                        defaultGroupBy={(crossWorkspace || searchScope ==='all') ? 'repository_id' : null }
                        groupByLabel={(crossWorkspace || searchScope ==='all') ? 'repository_display' : null }
                        emptyStateProps={{
                            iconClassName:"",
                            primaryTextId:"No results",
                            style:{minHeight: (display === 'small' ? 180  : 412), backgroundColor: 'transparent'}
                        }}
                    />

                    {display === 'small' &&
                        <div style={{display:'flex', alignItems:'center', padding:5, paddingLeft: 0, backgroundColor:'#f5f5f5'}}>
                            {!this.props.crossWorkspace &&  <MenuScopeSelector style={{flex: 1, maxWidth:170}} labelStyle={{paddingLeft: 8}} value={searchScope} onChange={searchScopeChanged} onTouchTap={() => this.setMode('small')}/>}
                            <FlatButton style={{marginTop:4}} primary={true} label={"More..."} onFocus={() => this.setMode("small")} onTouchTap={() => this.setMode("more")} onClick={() => this.setMode("more")} />
                        </div>
                    }
                </div>

            </Paper>
        );
    }
}

class MenuScopeSelector extends Component {

    render(){
        return (

            <DropDownMenu
                value={this.props.value}
                onChange={(e,i,v) => {this.props.onChange(v)}}
                onTouchTap={this.props.onTouchTap}
                autoWidth={true}
                style={this.props.style}
                underlineStyle={{display:'none'}}
                labelStyle={this.props.labelStyle}
            >
                <MenuItem value={'folder'} primaryText="Current folder"/>
                <MenuItem value={'ws'} primaryText="Current workspace"/>
                <MenuItem value={'all'} primaryText="All workspaces"/>
            </DropDownMenu>

        );
    }


}

class MainSearch extends Component {

    static get styles() {
        return {
            main: {
                background: "#ffffff",
                width: "100%",
                height: 36,
                border: "none",
                transition:'all .25s',
                display:'flex'
            },
            input: {
                padding: "0 4px",
                border: 0
            },
            hint: {
                transition:'all .25s',
                width: "100%",
                padding: "0 4px",
                bottom: 0,
                lineHeight: "36px",
                whiteSpace: "nowrap",
                overflow: "hidden",
                textOverflow: "ellipsis"
            },
            magnifier: {
                padding: '7px 0 0 8px',
                fontSize: 23,
                color: 'rgba(0, 0, 0, 0.33)'
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
                magnifier: {
                    fontSize: 18,
                    color: 'rgba(255, 255, 255, 0.64)'
                },
                input: {
                    color: 'rgba(255, 255, 255, 0.64)'
                },
                hint: {
                    color: 'rgba(255, 255, 255, 0.64)'
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
        const {title, mode, onOpen, onAdvanced, onMore, onClose, hintText, loading, scopeSelectorProps, showAdvanced} = this.props;
        let {main, input, hint, underline, magnifier, closedMode} = MainSearch.styles
        if(mode === 'closed'){
            main = {...main, ...closedMode.main};
            hint = {...hint, ...closedMode.hint};
            input = {...input, ...closedMode.input};
            magnifier = {...magnifier, ...closedMode.magnifier};
        }

        return (
            <div className="search-input">
                <div className="panel-header" style={{display:'flex'}}>
                    {scopeSelectorProps &&
                        <span>
                            <MenuScopeSelector style={{marginTop:-16, marginLeft:-26}} labelStyle={{color: 'white'}} {...scopeSelectorProps}/>
                        </span>
                    }
                    <span style={{flex:1}}></span>
                    {showAdvanced &&
                        <FlatButton style={{textTransform:'none', color:'white', fontSize:15, marginTop:-5, padding:'0 16px'}} onTouchTap={mode === 'advanced' ? onMore : onAdvanced}>{mode === 'advanced' ? '- Less search options' : '+ More search options'}</FlatButton>
                    }
                    {mode === 'advanced' && loading &&
                        <div style={{marginRight: 10}} ><CircularProgress size={20} thickness={3}/></div>
                    }
                    <span className="panel-header-close mdi mdi-close" onClick={this.props.onClose}></span>
                </div>

                {mode !== 'advanced' &&
                    <div style={main}>

                        <FontIcon className="mdi mdi-magnify" style={magnifier}/>

                        <TextField
                            ref={(input) => this.input = input}
                            style={{flex: 1, height:main.height}}
                            inputStyle={input}
                            hintStyle={hint}
                            fullWidth={true}
                            underlineShow={false}
                            onFocus={onOpen}
                            onBlur={mode === 'small' ? onClose : null}
                            hintText={hintText}
                            onChange={(e,v) => this.onChange(v)}
                            onKeyPress={(e) => (e.key === 'Enter' ? this.onChange(e.target.value) : null)}
                        />

                        {loading &&
                            <div style={{marginTop:9, marginRight: 9}} ><CircularProgress size={20} thickness={3}/></div>
                        }
                    </div>
                }

            </div>
        )
    }
}

export default SearchForm
