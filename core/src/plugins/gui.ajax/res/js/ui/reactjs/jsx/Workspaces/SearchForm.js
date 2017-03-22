import FilePreview from './FilePreview'

let SearchDatePanel = React.createClass({

    recomputeStart: function(event, value){
        this.setState({startDate: value}, this.dateToChange);
    },
    recomputeEnd: function(event, value){
        this.setState({endDate: value}, this.dateToChange);
    },
    clearStart:function(){
        this.setState({startDate: null}, this.dateToChange);
        this.refs['startDate'].refs.input.setValue("");
    },
    clearEnd :function(){
        this.setState({endDate: null}, this.dateToChange);
        this.refs['endDate'].refs.input.setValue("");
    },

    getInitialState: function(){
        return {
            value:'custom',
            startDate: null,
            endDate: null,
        };
    },

    dateToChange: function(){
        if(!this.state.startDate && !this.state.endDate){
            this.props.onChange({ajxp_modiftime:null}, true);
        }else{
            let s = this.state.startDate || 'XXX';
            let e = this.state.endDate || 'XXX';
            this.props.onChange({ajxp_modiftime:'['+s+' TO '+e+']'}, true);
        }
    },

    changeSelector: function(e, selectedIndex, menuItem){
        this.setState({value: menuItem.payload});
        if(menuItem.payload === 'custom'){
            this.dateToChange();
        }else{
            this.props.onChange({ajxp_modiftime:menuItem.payload}, true);
        }
    },

    render: function () {
        const today = new Date();
        let getMessage = function(messageId){return this.props.pydio.MessageHash[messageId]}.bind(this);
        let values = [
            {payload: 'custom', text: 'Custom Dates'},
            {payload: 'AJXP_SEARCH_RANGE_TODAY', text: getMessage('493')},
            {payload: 'AJXP_SEARCH_RANGE_YESTERDAY', text : getMessage('494')},
            {payload: 'AJXP_SEARCH_RANGE_LAST_WEEK', text : getMessage('495')},
            {payload: 'AJXP_SEARCH_RANGE_LAST_MONTH', text : getMessage('496')},
            {payload: 'AJXP_SEARCH_RANGE_LAST_YEAR', text : getMessage('497')}
        ];
        let value = this.state.value;
        let index = 0;
        values.map(function(el, id){
            if(el.payload === value) {
                index = id;
            }
        });
        let selector = <ReactMUI.DropDownMenu menuItems={values} selectedIndex={index} onChange={this.changeSelector}/>;

        let customDate;
        if(value === 'custom'){
            customDate = (
                <div className="paginator-dates">
                    <ReactMUI.DatePicker
                        ref="startDate"
                        onChange={this.recomputeStart}
                        key="start"
                        hintText={"From..."}
                        autoOk={true}
                        maxDate={this.state.endDate || today}
                        defaultDate={this.state.startDate}
                        showYearSelector={true}
                        onShow={this.props.pickerOnShow}
                        onDismiss={this.props.pickerOnDismiss}
                    /> <span className="mdi mdi-close" onClick={this.clearStart}></span>
                    <ReactMUI.DatePicker
                        ref="endDate"
                        onChange={this.recomputeEnd}
                        key="end"
                        hintText={"To..."}
                        autoOk={true}
                        minDate={this.state.startDate}
                        maxDate={today}
                        defaultDate={this.state.endDate}
                        showYearSelector={true}
                        onShow={this.props.pickerOnShow}
                        onDismiss={this.props.pickerOnDismiss}
                    /> <span className="mdi mdi-close" onClick={this.clearEnd}></span>
                </div>
            );
        }
        return (
            <div>
                {selector}
                {customDate}
            </div>
        );
    }

});

let SearchFileFormatPanel = React.createClass({

    getInitialState: function(){
        return {folder:false, ext: null};
    },

    changeExt: function(){
        this.setState({ext: this.refs.ext.getValue()}, this.stateChanged);
    },

    toggleFolder: function(e, toggled){
        this.setState({folder: toggled}, () => {this.stateChanged(true);});
    },

    stateChanged: function(submit = false){
        let value = null;
        if(this.state.folder){
            value = 'ajxp_folder';
        }else if(this.state.ext){
            value = this.state.ext;
        }
        this.props.onChange({ajxp_mime:value}, submit);
    },

    render: function(){
        let textField;
        if(!this.state.folder){
            textField = (
                <ReactMUI.TextField
                    ref="ext"
                    hintText="Extension"
                    floatingLabelText="File extension"
                    onChange={this.changeExt}
                    onFocus={this.props.fieldsFocused}
                    onBlur={this.props.fieldsBlurred}
                    onKeyPress={this.props.fieldsKeyPressed}
                />
            );
        }
        return (
            <div>
                <ReactMUI.Toggle
                    ref="folder"
                    name="toggleFolder"
                    value="ajxp_folder"
                    label="Folders Only"
                    onToggle={this.toggleFolder}
                />
                {textField}
            </div>
        );
    }

});

let SearchFileSizePanel = React.createClass({

    getInitialState: function(){
        return {from:false, to: null};
    },

    changeFrom: function(){
        this.setState({from: this.refs.from.getValue()}, this.stateChanged);
    },

    changeTo: function(){
        this.setState({to: this.refs.to.getValue()}, this.stateChanged);
    },

    stateChanged: function(){
        if(!this.state.to && !this.state.from){
            this.props.onChange({ajxp_bytesize:null});
        }else{
            let from = this.state.from || 0;
            let to   = this.state.to   || 1099511627776;
            this.props.onChange({ajxp_bytesize:'['+from+' TO '+to+']'});
        }
    },

    render: function(){
        return (
            <div>
                <ReactMUI.TextField
                    ref="from"
                    hintText="1Mo,1Go,etc"
                    floatingLabelText="Size greater than..."
                    onChange={this.changeFrom}
                    onFocus={this.props.fieldsFocused}
                    onBlur={this.props.fieldsBlurred}
                    onKeyPress={this.props.fieldsKeyPressed}
                />
                <ReactMUI.TextField
                    ref="to"
                    hintText="1Mo,1Go,etc"
                    floatingLabelText="Size bigger than..."
                    onChange={this.changeTo}
                    onFocus={this.props.fieldsFocused}
                    onBlur={this.props.fieldsBlurred}
                    onKeyPress={this.props.fieldsKeyPressed}
                />
            </div>
        );
    }

});

let SearchForm = React.createClass({

    fieldsFocused: function(){
    },

    fieldsBlurred: function(){
    },

    fieldsKeyPressed: function(e){
        if(e.key === 'Enter'){
            this.submitSearch();
        }
    },

    focused: function(){
        if(this.state.display === 'closed'){
            this.setState({display: 'small'});
        }
        this.fieldsFocused();
    },

    blurred: function(){
        if(this.state.display === 'small'){
            //this.setState({display: 'closed'});
        }
        this.fieldsBlurred();
    },

    getInitialState: function(){

        let dataModel = new PydioDataModel(true);
        let rNodeProvider = new RemoteNodeProvider();
        dataModel.setAjxpNodeProvider(rNodeProvider);
        rNodeProvider.initProvider({});
        let rootNode = new AjxpNode("/", false, "loading", "folder.png", rNodeProvider);
        rootNode.setLoaded(true);
        dataModel.setRootNode(rootNode);

        return {
            quickSearch:true,
            display: 'closed',
            metaFields: {basename:{label:'Filename'}},
            searchMode:'remote',
            dataModel: dataModel,
            nodeProvider: rNodeProvider
        };
    },

    mainFieldQuickSearch: function(event){
        let current = {
            basename:this.refs.main_search_input.getValue()
        };
        this.setState({
            quickSearch: true,
            formValues: current,
            query:this.buildQuery(current)
        });
        FuncUtils.bufferCallback('search-form-submit-search', 350, this.submitSearch);

    },

    mainFieldEnter: function(event){
        if(event.key !== 'Enter') return;
        let current = {
            basename:this.refs.main_search_input.getValue()
        };
        this.setState({
            quickSearch: false,
            formValues: current,
            query:this.buildQuery(current)
        }, this.submitSearch);

    },

    updateFormValues: function(object, submit = false){
        let current = this.state.formValues || {};
        for(var k in object){
            if(!object.hasOwnProperty(k)) continue;
            if(object[k] === null && current[k]) delete current[k];
            else current[k] = object[k];
        }
        let afterFunction = submit ? this.submitSearch.bind(this) :  () => {};
        this.setState({
            quickSearch: false,
            formValues: current,
            query:this.buildQuery(current)
        }, afterFunction);
    },

    submitSearch: function(){
        if(this.state.display !== 'advanced' && !(this.state.formValues && this.state.formValues.basename)){
            return;
        }
        this.refs.results.reload();
    },

    buildQuery: function(formValues){
        let keys = Object.keys(formValues);
        if(keys.length === 1 && keys[0] === 'basename'){
            return formValues['basename'];
        }
        let parts = [];
        keys.map(function(k){
            parts.push(k + ':' + formValues[k]);
        });
        return parts.join(' AND ');
    },

    parseMetaColumns(force = false){
        if(!force && this._currentWorkspaceParsed && pydio.repositoryId === this._currentWorkspaceParsed){
            return;
        }
        let metaFields = {basename:'Filename'}, searchMode = 'remote', registry = this.props.pydio.getXmlRegistry();
        this._currentWorkspaceParsed = pydio.repositoryId;
        // Parse client configs
        let options = JSON.parse(XMLUtils.XPathGetSingleNodeText(registry, 'client_configs/template_part[@ajxpClass="SearchEngine" and @theme="material"]/@ajxpOptions'));
        if(options && options.metaColumns){
            metaFields = Object.assign(metaFields, options.metaColumns);
            Object.keys(metaFields).map(function(key){
                let cData = {
                    key: key,
                    label: metaFields[key]
                };
                if(options.reactColumnsRenderers && options.reactColumnsRenderers[key]) {
                    let renderer = cData['renderer'] = options.reactColumnsRenderers[key];
                    let namespace = renderer.split('.',1).shift();
                    if(window[namespace]){
                        cData.renderComponent = FuncUtils.getFunctionByName(renderer, window);
                    }else{
                        return ResourcesManager.detectModuleToLoadAndApply(renderer, function(){
                            this.parseMetaColumns(true);
                        }.bind(this), true);
                    }
                }
                metaFields[key] = cData;
            }.bind(this));
        }
        // Parse Indexer data (e.g. Lucene)
        let indexerNode = XMLUtils.XPathSelectSingleNode(registry, 'plugins/indexer');
        if(indexerNode){
            let indexerOptions = JSON.parse(XMLUtils.XPathGetSingleNodeText(registry, 'plugins/indexer/@indexed_meta_fields'));
            if(indexerOptions && indexerOptions.additional_meta_columns){
                Object.keys(indexerOptions.additional_meta_columns).map(function(aKey){
                    if(!metaFields[aKey]) {
                        metaFields[aKey] = {label:indexerOptions.additional_meta_columns[aKey]};
                    }
                });
            }
        }else{
            searchMode = 'local';
        }
        this.setState({
            metaFields:metaFields,
            searchMode: searchMode
        });
    },

    hideOnExternalClick(event){
        if(this.state.display !== 'small') return;
        const root = this.refs.root;
        if(root !== event.target && !root.contains(event.target)){
            this.setState({display:'closed'});
        }
    },

    componentWillReceiveProps: function(nextProps){
        this.parseMetaColumns();
    },

    componentDidMount: function(){
        this.parseMetaColumns();
        this._clickObserver = this.hideOnExternalClick.bind(this);
        document.addEventListener('click', this._clickObserver);
    },

    componentWillUnmount: function(){
        document.removeEventListener('click', this._clickObserver);
    },

    componentDidUpdate: function(){
        if(this.refs.results && this.refs.results.refs.list){
            this.refs.results.refs.list.updateInfiniteContainerHeight();
            FuncUtils.bufferCallback('search_results_resize_list', 550, ()=>{
                try{
                    this.refs.results.refs.list.updateInfiniteContainerHeight();
                }catch(e){}
            });
        }
    },

    render: function(){
        let columnsDesc;
        let props = {
            fieldsFocused       : this.fieldsFocused,
            fieldsBlurred       : this.fieldsBlurred,
            fieldsKeyPressed    : this.fieldsKeyPressed,
            onChange            : this.updateFormValues
        };
        let cols = Object.keys(this.state.metaFields).map(function(k){
            let label = this.state.metaFields[k].label;
            if(this.state.metaFields[k].renderComponent){
                let fullProps = Object.assign(props, this.props, {label:label, fieldname: k});
                return this.state.metaFields[k].renderComponent(fullProps);
            }else{
                let onChange = function(event){
                    let object = {};
                    let objectKey = (k === 'basename') ? k : 'ajxp_meta_' + k;
                    object[objectKey] = event.target.getValue() || null;
                    this.updateFormValues(object);
                }.bind(this);
                return (
                    <ReactMUI.TextField
                        key={k}
                        onFocus={this.fieldsFocused}
                        onBlur={this.fieldsBlurred}
                        onKeyPress={this.fieldsKeyPressed}
                        floatingLabelText={label}
                        onChange={onChange}
                    />);
            }
        }.bind(this));
        columnsDesc = <div>{cols}</div>;
        if(this.state.query){
            let limit = 100;
            if(this.state.quickSearch) limit = 9;
            if(this.props.crossWorkspace) limit = 5;
            // Refresh nodeProvider query
            this.state.nodeProvider.initProvider({
                get_action  : this.props.crossWorkspace ? 'multisearch' : 'search',
                query       : this.state.query,
                limit       : limit
            });
        }
        let moreButton, renderSecondLine = null, renderIcon = null, elementHeight = 49;
        if(this.state.display === 'small'){
            let openMore = function(){
                this.setState({display:'more', quickSearch:false}, this.submitSearch);
            }.bind(this);
            moreButton = (
                <div className="search-more-container">
                    <ReactMUI.FlatButton secondary={true} label={"Show more..."} onClick={openMore}/>
                </div>
            );
        }else{
            elementHeight = PydioComponents.SimpleList.HEIGHT_TWO_LINES + 10;
            renderSecondLine = function(node){
                return <div>{node.getPath()}</div>
            };
            renderIcon = function(node, entryProps = {}){
                return <FilePreview loadThumbnail={!entryProps['parentIsScrolling']} node={node}/>;
            };

        }
        let advancedButton = (
            <span className="search-advanced-button" onClick={()=>{this.setState({display:'advanced'})}}>Advanced search</span>
        );
        return (
            <div ref="root" className={"top_search_form " + this.state.display}>
                <div className="search-input">
                    <div className="panel-header">
                        <span className="panel-header-label">{this.state.display === 'advanced'?'Advanced Search' : 'Search ...'}</span>
                        <span className="panel-header-close mdi mdi-close" onClick={()=>{this.setState({display:'closed'});}}></span>
                    </div>
                    <ReactMUI.TextField
                        ref="main_search_input"
                        onFocus={this.focused}
                        onBlur={this.blurred}
                        hintText={this.props.crossWorkspace?"Search inside all workspaces...":"Search inside this workspace"}
                        onChange={this.mainFieldQuickSearch}
                        onKeyPress={this.mainFieldEnter}
                    />
                    {advancedButton}
                </div>
                <div className="search-advanced">
                    <div className="advanced-section">User Metadata</div>
                    {columnsDesc}
                    <div className="advanced-section">Modification Date</div>
                    <SearchDatePanel {...this.props} {...props}/>
                    <div className="advanced-section">File format</div>
                    <SearchFileFormatPanel {...this.props} {...props}/>
                    <div className="advanced-section">Bytesize ranges</div>
                    <SearchFileSizePanel {...this.props} {...props}/>
                </div>
                <div className="search-results">
                    <PydioComponents.NodeListCustomProvider
                        ref="results"
                        className={this.state.display !== 'small' ? 'files-list' : null}
                        elementHeight={elementHeight}
                        entryRenderIcon={renderIcon}
                        entryRenderActions={function(){return null}}
                        entryRenderSecondLine={renderSecondLine}
                        presetDataModel={this.state.dataModel}
                        heightAutoWithMax={this.state.display === 'small' ? 500  : 412}
                        nodeClicked={(node)=>{this.props.pydio.goTo(node);this.setState({display:'closed'})}}
                        defaultGroupBy={this.props.crossWorkspace?'repository_id':null}
                        groupByLabel={this.props.crossWorkspace?'repository_display':null}
                    />
                    {moreButton}
                </div>
            </div>
        );
    }

});

export {SearchForm as default}