(function(global){

    class Renderer{

        static getMetadataConfigs(){

            if(pydio && pydio.user && pydio.user.activeRepository && Renderer.__CACHE
                && Renderer.__CACHE.has(pydio.user.activeRepository)){
                return Renderer.__CACHE.get(pydio.user.activeRepository);
            }
            var configMap = new Map();
            try{
                let configs = JSON.parse(pydio.getPluginConfigs("meta.user").get("meta_definitions"));
                Object.keys(configs).map(function(key){
                    let value = configs[key];
                    var type = value.type;
                    if(type == 'choice' && value.data){
                        var values = new Map();
                        value.data.split(",").map(function(keyLabel){
                            var parts = keyLabel.split("|");
                            values.set(parts[0], parts[1]);
                        });
                        value.data = values;
                    }
                    configMap.set(key, value);
                });
            }catch(e){
                console.debug(e);
            }
            if(pydio && pydio.user && pydio.user.activeRepository){
                if(!Renderer.__CACHE) Renderer.__CACHE = new Map();
                Renderer.__CACHE.set(pydio.user.activeRepository, configMap);
            }
            return configMap;
        }

        static renderStars(node, column){
            return <MetaStarsRenderer node={node} column={column}/>;
        }

        static renderSelector(node, column){
            return <SelectorFilter node={node} column={column}/>;
        }

        static renderCSSLabel(node, column){
            return <CSSLabelsFilter node={node} column={column}/>;
        }

        static renderTagsCloud(node, column){
            return <TagsCloud node={node} column={column}/>;
        }

        static formPanelStars(props){
            return <StarsFormPanel {...props}/>;
        }

        static formPanelCssLabels(props){

            const menuItems = Object.keys(CSSLabelsFilter.CSS_LABELS).map(function(id){
                let label = CSSLabelsFilter.CSS_LABELS[id];
                //return {payload:id, text:label.label};
                return <MaterialUI.MenuItem value={id} primaryText={label.label}/>
            }.bind(this));

            return <MetaSelectorFormPanel {...props} menuItems={menuItems}/>;
        }

        static formPanelSelectorFilter(props){

            let configs = Renderer.getMetadataConfigs().get(props.fieldname);
            let menuItems = [];
            if(configs && configs.data){
                configs.data.forEach(function(value, key){
                    //menuItems.push({payload:key, text:value});
                    menuItems.push(<MaterialUI.MenuItem value={key} primaryText={value}/>);
                });
            }

            return <MetaSelectorFormPanel {...props} menuItems={menuItems}/>;
        }

        static formPanelTags(props){
            let configs = Renderer.getMetadataConfigs().get(props.fieldname);
            console.log(configs);
            // let menuItems = [];
            // if(configs && configs.data){
            //     configs.data.forEach(function(value, key){
            //         //menuItems.push({payload:key, text:value});
            //         menuItems.push(<MaterialUI.MenuItem value={key} primaryText={value}/>);
            //     });
            // }

            return <TagsCloud {...props} editMode={true}/>;
            // return <MetaSelectorFormPanel {...props} menuItems={menuItems} />//<div>Tags</div>
        }

    }

    class Callbacks{

        static editMeta(){

            ResourcesManager.detectModuleToLoadAndApply('MetaCellRenderer', function(){
                var userSelection = global.pydio.getUserSelection();
                var loadFunc = function(oForm){
                    var form = $(oForm).select('div[id="user_meta_form"]')[0];
                    var nodeMeta = $H();
                    var firstNodeMeta = userSelection.getUniqueNode().getMetadata();
                    var metaConfigs = MetaCellRenderer.prototype.staticGetMetaConfigs();
                    if(userSelection.isUnique()){
                        nodeMeta = firstNodeMeta;
                    }
                    metaConfigs.each(function(pair){
                        var value = nodeMeta.get(pair.key) || '';
                        form.insert('<div class="SF_element"><div class="SF_label">'+pair.value.label+' : </div><input class="SF_input" name="'+pair.key+'" value="'+value.replace(/"/g, '&quot;')+'"/></div>');
                        var element = form.down('input[name="'+pair.key+'"]');
                        var fieldType = pair.value.type;
                        if(fieldType == 'stars_rate'){
                            MetaCellRenderer.prototype.formPanelStars(element, form);
                        }else if(fieldType == 'css_label'){
                            MetaCellRenderer.prototype.formPanelCssLabels(element, form);
                        }else if(fieldType == 'textarea'){
                            MetaCellRenderer.prototype.formTextarea(element, form);
                        }else if(fieldType == 'choice'){
                            MetaCellRenderer.prototype.formPanelSelectorFilter(element, form);
                        }else if(fieldType == 'tags'){
                            MetaCellRenderer.prototype.formPanelTags(element, form);
                        }else if(fieldType == 'updater' || fieldType == 'creator'){
                            element.disabled = true;
                        }
                    });
                }
                var closeFunc = function(){
                    var oForm = $(modal.getForm());
                    userSelection.updateFormOrUrl(modal.getForm());
                    PydioApi.getClient().submitForm(oForm, true);
                    hideLightBox(true);
                    return false;
                };
                modal.showDialogForm('Meta Edit', 'user_meta_form', loadFunc, closeFunc);
            });

        }

    }

    let MetaFieldFormPanelMixin = {

        propTypes:{
            label:React.PropTypes.string,
            fieldname:React.PropTypes.string,
            onChange:React.PropTypes.func,
            onValueChange:React.PropTypes.func
        },

        updateValue:function(value, submit = true){
            this.setState({value:value});
            if(this.props.onChange){
                let object = {};
                object['ajxp_meta_' + this.props.fieldname] = value;
                this.props.onChange(object, submit);
            }else if(this.props.onValueChange){
                this.props.onValueChange(this.props.fieldname, value);
            }
        }

    };

    let MetaFieldRendererMixin = {

        propTypes:{
            node:React.PropTypes.instanceOf(AjxpNode),
            column:React.PropTypes.object
        },

        getRealValue: function(){
            return this.props.node.getMetadata().get(this.props.column.name);
        }

    };

    let StarsFormPanel = React.createClass({

        mixins:[MetaFieldFormPanelMixin],

        getInitialState: function(){
            return {value: 0};
        },

        render: function(){
            let value = this.state.value;
            let stars = [0,1,2,3,4].map(function(v){
                return <span key={"star-" + v} onClick={this.updateValue.bind(this, v+1)} className={"mdi mdi-star" + (value > v ? '' : '-outline')}></span>;
            }.bind(this));
            return (
                <div className="advanced-search-stars">
                    <div className="stars-label">{this.props.label}</div>
                    <div className="stars-icons">{stars}</div>
                </div>
            );
        }

    });

    let MetaStarsRenderer = React.createClass({

        mixins:[MetaFieldRendererMixin],

        render: function(){
            let value = this.getRealValue() || 0;
            let stars = [0,1,2,3,4].map(function(v){
                return <span key={"star-" + v} className={"mdi mdi-star" + (value > v ? '' : '-outline')}></span>;
            });
            return <span>{stars}</span>;
        }

    });

    let SelectorFilter = React.createClass({

        mixins:[MetaFieldRendererMixin],

        render: function(){
            let value;
            let displayValue = value = this.getRealValue();
            let configs = Renderer.getMetadataConfigs().get(this.props.column.name);
            if(configs && configs.data){
                displayValue = configs.data.get(value);
            }
            return <span>{displayValue}</span>;
        }

    });

    let CSSLabelsFilter = React.createClass({

        mixins:[MetaFieldRendererMixin],

        statics:{
            CSS_LABELS : {
                'low'       : {cssClass:'meta_low',         label:MessageHash['meta.user.4'], sortValue:'5'},
                'todo'      : {cssClass:'meta_todo',        label:MessageHash['meta.user.5'], sortValue:'4'},
                'personal'  : {cssClass:'meta_personal',    label:MessageHash['meta.user.6'], sortValue:'3'},
                'work'      : {cssClass:'meta_work',        label:MessageHash['meta.user.7'], sortValue:'2'},
                'important' : {cssClass:'meta_important',   label:MessageHash['meta.user.8'], sortValue:'1'}
            }
        },

        render: function(){
            let MessageHash = global.pydio.MessageHash;
            let value = this.getRealValue();
            const data = CSSLabelsFilter.CSS_LABELS;
            if(value && data[value]){
                let dV = data[value];
                return <span className={dV.cssClass}>{dV.label}</span>
            }else{
                return <span>{value}</span>;
            }
        }

    });

    let MetaSelectorFormPanel = React.createClass({

        mixins:[MetaFieldFormPanelMixin],

        changeSelector: function(e, selectedIndex, payload){
            this.updateValue(payload);
        },

        getInitialState: function(){
            return {value: this.props.value};
        },

        render: function(){
            let index = 0, i = 1;
            this.props.menuItems.unshift(<MaterialUI.MenuItem value={null} primaryText={this.props.label}/>);
            return (
                <div>
                    <MaterialUI.SelectField
                        style={{width:'100%'}}
                        value={this.state.value}
                        onChange={this.changeSelector}>{this.props.menuItems}</MaterialUI.SelectField>
                </div>
            );
        }

    });

    let TagsCloud = React.createClass({

        mixins: [MetaFieldFormPanelMixin],

        propTypes:{
            node:React.PropTypes.instanceOf(AjxpNode),
            column:React.PropTypes.object
        },
        componentDidMount: function() {
            this.getRealValue();
        },

        getRealValue: function(){
            let {node, value, column} = this.props
            if (node != null) {
                this.setState({tags: node.getMetadata().get(column.name)});
            } else {
                this.setState({tags: value});
            }
        },

        getInitialState: function(){
            let {node, value} = this.props
            if (node != null) {
                return {
                    loading     : false,
                    dataSource  : [],
                    tags        : node.getMetadata().get(this.props.column.name),
                    searchText  : ''
                };
            } else {
                return {
                    loading     : false,
                    dataSource  : [],
                    tags        : value,
                    searchText  : ''
                };
            }
        },

        suggestionLoader: function(input, callback) {
            const excludes = this.props.excludes;
            const disallowTemporary = this.props.existingOnly && !this.props.freeValueAllowed;
            this.setState({loading:this.state.loading + 1});
            PydioApi.getClient().request({get_action: 'meta_user_list_tags', meta_field_name: this.props.fieldname}, (transport) => {
                this.setState({loading:this.state.loading - 1});
                let suggestedTags = transport.responseJSON;
                callback(suggestedTags);
            }.bind(this));
        },

        loadBuffered: function(value, timeout) {
            if(!value && this._emptyValueList){
                this.setState({dataSource: this._emptyValueList});
                return;
            }
            FuncUtils.bufferCallback('meta_user_list_tags', timeout, function(){
                this.setState({loading: true});
                this.suggestionLoader(value, function(tags){
                    let crtValueFound = false;
                    const values = tags.map(function(tag){
                        let component = (<MaterialUI.MenuItem>{tag}</MaterialUI.MenuItem>);
                        return {
                            userObject  : tag,
                            text        : tag,
                            value       : component
                        };
                    }.bind(this));
                    if(!value){
                        this._emptyValueList = values;
                    }
                    this.setState({dataSource: values, loading: false});
                }.bind(this));
            }.bind(this));
        },

        handleRequestDelete: function(tag) {
            let tags = this.state.tags.split(',');
            let index = tags.indexOf(tag);
            tags.splice(index, 1);
            this.setState({
                tags: tags.toString()},
            () => {
                this.updateValue(this.state.tags);
                console.log(`Tags after deletion = ${this.state.value}`);
            });
        },

        handleUpdateInput: function(searchText) {
            this.setState({searchText: searchText});
            this.loadBuffered(searchText, 350);
        },

        handleNewRequest: function() {
            let tags = this.state.tags.split(',');
            tags.push(this.state.searchText);
            this.setState({
                tags: tags.toString()},
            () => {
                this.updateValue(this.state.tags);
            });
            this.setState({
                searchText: '',
            });
        },

        renderChip: function(tag) {
            if (this.props.editMode) {
                return (
                    <MaterialUI.Chip
                        style={{margin: 2}}
                        onRequestDelete={this.handleRequestDelete.bind(this, tag)}
                    >{tag}</MaterialUI.Chip>
                );
            } else {
                return (
                    <MaterialUI.Chip
                        style={{margin: 2}}
                        onTouchTap={this.handleTouchTap}
                    >{tag}</MaterialUI.Chip>
                );
            }
        },
        
        render: function(){
            let tags;
            if (this.state.tags) {
                tags = this.state.tags.split(",").map(function(tag){
                    tag = LangUtils.trim(tag);
                    if(!tag) return null;
                    return (this.renderChip(tag));
                }.bind(this));
            } else {
                tags = <div></div>
            }
            let autoCompleter;
            let textField;
            if (this.props.editMode) {
                autoCompleter = <MaterialUI.AutoComplete
                                    fullWidth={true}
                                    hintText="Type 'r', case insensitive"
                                    searchText={this.state.searchText}
                                    onUpdateInput={this.handleUpdateInput}
                                    onNewRequest={this.handleNewRequest}
                                    dataSource={this.state.dataSource}
                                    // filter={(searchText, key) => (key.indexOf(searchText) !== -1)}
                                    openOnFocus={true}
                                />
            } else {
                autoCompleter = <div></div>

            }

            return (
                <div>
                    <div style={{display: 'flex', flexWrap: 'wrap'}}>
                        {tags}
                    </div>
                    {autoCompleter}
                </div>
            );
        }

    });

    let UserMetaPanel = React.createClass({

        propTypes:{
            editMode: React.PropTypes.bool
        },

        getDefaultProps: function(){
            return {editMode: false};
        },

        getInitialState: function(){
            return { updateMeta: new Map() };
        },

        updateValue: function(name, value){
            this.state.updateMeta.set(name, value);
            this.setState({
                updateMeta: this.state.updateMeta
            });
        },

        getUpdateData: function(){
            return this.state.updateMeta;
        },

        resetUpdateData: function(){
            this.setState({
                updateMeta: new Map()
            });
        },

        render: function(){

            let configs = Renderer.getMetadataConfigs();
            let data = [];
            let node = this.props.node;
            let metadata = this.props.node.getMetadata();
            let updateMeta = this.state.updateMeta;
            let nonEmptyDataCount = 0;

            configs.forEach(function(meta, key){
                let label = meta.label;
                let type = meta.type;
                let value = metadata.get(key);
                if(updateMeta.has(key)){
                    value = updateMeta.get(key);
                }
                let realValue = value;

                if(this.props.editMode){
                    let field;
                    let baseProps = {
                        fieldname: key,
                        label: label,
                        value: value,
                        onValueChange: this.updateValue
                    };
                    if(type === 'stars_rate'){
                        field = <StarsFormPanel {...baseProps}/>;
                    }else if(type === 'choice') {
                        field = Renderer.formPanelSelectorFilter(baseProps);
                    }else if(type === 'css_label'){
                        field = Renderer.formPanelCssLabels(baseProps);
                    }else if(type === 'tags'){
                        field = Renderer.formPanelTags(baseProps);
                    }else{
                        field = (
                            <MaterialUI.TextField
                                floatingLabelText={label}
                                value={value}
                                style={{width:'100%'}}
                                onChange={(event, value)=>{this.updateValue(key, value);}}
                            />
                        );
                    }
                    data.push(field);
                }else{
                    let column = {name:key};
                    if(type === 'stars_rate'){
                        value = <MetaStarsRenderer node={node} column={column}/>
                    }else if(type === 'css_label'){
                        value = <CSSLabelsFilter node={node} column={column}/>
                    }else if(type === 'choice'){
                        value = <SelectorFilter node={node} column={column}/>
                    }else if(type === 'tags'){
                        value = <TagsCloud node={node} column={column}/>
                    }
                    if(realValue) nonEmptyDataCount ++;
                    data.push(
                        <div className={"infoPanelRow" + (!realValue?' no-value':'')} key={key}>
                            <div className="infoPanelLabel">{label}</div>
                            <div className="infoPanelValue">{value}</div>
                        </div>
                    );
                }
            }.bind(this));

            if(!this.props.editMode && !nonEmptyDataCount){
                return <div><div style={{color: 'rgba(0,0,0,0.23)', paddingBottom:10}}>No metadata set. Click edit to add some.</div>{data}</div>
            }else{
                return (<div>{data}</div>);
            }
        }

    });

    let InfoPanel = React.createClass({

        propTypes: {
            node: React.PropTypes.instanceOf(AjxpNode)
        },

        getInitialState: function(){
            return {editMode: false};
        },

        openEditMode: function(){
            this.setState({editMode:true });
        },

        reset: function(){
            this.refs.panel.resetUpdateData();
            this.setState({editMode: false});
        },

        componentWillReceiveProps: function(newProps){
            if(newProps.node !== this.props.node && this.refs.panel){
                this.reset();
            }
        },

        saveChanges: function(){
            let values = this.refs.panel.getUpdateData();
            let params = {};
            values.forEach(function(v, k){
                params[k] = v;
            });
            PydioApi.getClient().postSelectionWithAction("edit_user_meta", function(t){
                PydioApi.getClient().parseXmlMessage(t.responseXML);
                this.reset();
            }.bind(this), null, params);
        },

        render: function(){
            let actions = [];

            if(this.state.editMode){
                actions.push(
                    <MaterialUI.FlatButton
                        key="cancel"
                        label={"Cancel"}
                        onClick={()=>{this.reset()}}
                    />
                );
            }
            actions.push(
                <MaterialUI.FlatButton
                    key="edit"
                    label={this.state.editMode?"Save Meta":"Edit Meta"}
                    onClick={()=>{!this.state.editMode?this.openEditMode():this.saveChanges()}}
                />
            );

            return (
                <PydioDetailPanes.InfoPanelCard title={"Metadata"} actions={actions} icon="tag-multiple" iconColor="#00ACC1">
                    <UserMetaPanel
                        ref="panel"
                        node={this.props.node}
                        editMode={this.state.editMode}
                    />
                </PydioDetailPanes.InfoPanelCard>
            );
        }

    });

    let ns = global.ReactMeta || {};
    ns.Renderer = Renderer;
    ns.InfoPanel = InfoPanel;
    ns.Callbacks = Callbacks;
    global.ReactMeta = ns;

})(window);
