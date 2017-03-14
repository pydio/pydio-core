import Store from './Store'
import GridBuilder from './GridBuilder'

export default React.createClass({

    propTypes: {
        storeNamespace   : React.PropTypes.string.isRequired,
        builderNamespaces: React.PropTypes.array,
        defaultCards     : React.PropTypes.array,
        pydio            : React.PropTypes.instanceOf(Pydio),
        disableDrag      : React.PropTypes.bool
    },

    mixins:[PydioReactUI.PydioContextConsumerMixin],

    removeCard:function(cardId){

        this._store.removeCard(cardId);
    },

    addCard:function(cardDefinition){

        this._store.addCard(cardDefinition);
    },

    resetCardsAndLayout:function(){
        this._store.saveUserPreference('Layout', null);
        this._store.setCards(null);
        this.setState(this.getInitialState());
    },

    saveFullLayouts:function(allLayouts){
        var savedPref = this._store.getUserPreference('Layout');
        // Compare JSON versions to avoid saving unnecessary changes
        if(savedPref && this.previousLayout  && this.previousLayout == JSON.stringify(allLayouts)){
            return;
        }
        this.previousLayout = JSON.stringify(allLayouts);
        this._store.saveUserPreference('Layout', allLayouts);
    },

    onLayoutChange: function(currentLayout, allLayouts){
        if(this._blockLayoutSave) return;
        this.saveFullLayouts(allLayouts);
    },

    componentDidMount: function(){
    },

    componentWillUnmount: function(){
        this._store.stopObserving("cards", this._storeObserver);
    },

    getInitialState:function(){

        this._store = new Store(this.props.storeNamespace, this.props.defaultCards, this.props.pydio);
        this._storeObserver = function(e){
            this.setState({
                cards: this._store.getCards(),
            });
        }.bind(this);
        this._store.observe("cards", this._storeObserver);

        return {
            cards: this._store.getCards(),
            editMode:false
        }
    },

    toggleEditMode:function(){
        this.setState({editMode:!this.state.editMode});
    },

    render: function(){

        var index = 0;
        var lgLayout = [];
        var savedLayouts = this._store.getUserPreference('Layout');

        var layouts = {lg:[], md:[], sm:[], xs:[], xxs:[]};

        let items = [];
        let additionalNamespaces = [];
        this.state.cards.map(function(item){

            var parts = item.componentClass.split(".");
            var classNS = parts[0];
            var className = parts[1];
            var classObject;
            if(global[classNS] && global[classNS][className]){
                classObject = global[classNS][className];
            }else{
                if(!global[classNS]) {
                    additionalNamespaces.push(classNS);
                }
                return;
            }
            var props = LangUtils.deepCopy(item.props);
            var itemKey = props['key'] = item['id'] || 'item_' + index;
            props.showCloseAction = this.state.editMode;
            props.pydio=this.props.pydio;
            props.onCloseAction = function(){
                this.removeCard(itemKey);
            }.bind(this);
            props.preferencesProvider = this._store;
            var defaultX = 0, defaultY = 0;
            if(item.defaultPosition){
                defaultX = item.defaultPosition.x;
                defaultY = item.defaultPosition.y;
            }
            var defaultLayout = classObject.getGridLayout(defaultX, defaultY);
            defaultLayout['handle'] = 'h4';
            if(item['gridHandle']){
                defaultLayout['handle'] = item['gridHandle'];
            }
            defaultLayout['i'] = itemKey;

            for(var breakpoint in layouts){
                if(!layouts.hasOwnProperty(breakpoint))continue;
                var breakLayout = layouts[breakpoint];
                // Find corresponding element in preference
                var existing;
                if(savedLayouts && savedLayouts[breakpoint]){
                    savedLayouts[breakpoint].map(function(gridData){
                        if(gridData['i'] == itemKey && gridData['h'] == defaultLayout['h']){
                            existing = gridData;
                        }
                    });
                }
                if(existing){
                    breakLayout.push(existing);
                }else{
                    breakLayout.push(defaultLayout);
                }
            }
            index++;
            items.push(React.createElement(classObject, props));

        }.bind(this));

        if(additionalNamespaces.length){
            this._blockLayoutSave = true;
            ResourcesManager.loadClassesAndApply(additionalNamespaces, function(){
                this.setState({additionalNamespacesLoaded:additionalNamespaces}, function(){
                    this._blockLayoutSave = false;
                }.bind(this));
            }.bind(this));
        }

        var monitorWidgetEditing = function(status){
            this.setState({widgetEditing:status});
        }.bind(this);

        var builder;
        if(this.props.builderNamespaces && this.state.editMode){
            builder = (
                <GridBuilder
                    className="admin-helper-panel"
                    namespaces={this.props.builderNamespaces}
                    onCreateCard={this.addCard}
                    onResetLayout={this.resetCardsAndLayout}
                    onEditStatusChange={monitorWidgetEditing}
                />);
        }
        const {Responsive, WidthProvider} = ReactGridLayout;
        const ResponsiveGridLayout = WidthProvider(Responsive);
        const propStyle = this.props.style || {};
        return (
            <div style={{...this.props.style, width:'100%', flex:'1'}} className={this.state.editMode?"builder-open":""}>
                <div style={{position:'absolute',bottom:30,right:18, zIndex:11}}>
                    <MaterialUI.FloatingActionButton
                        tooltip={this.context.getMessage('home.49')}
                        onClick={this.toggleEditMode}
                        iconClassName={this.state.editMode?"icon-ok":"mdi mdi-pencil"}
                        mini={this.state.editMode}
                        disabled={this.state.editMode && this.state.widgetEditing}
                    />
                </div>
                {builder}
                <div className="home-dashboard" style={{height:'100%'}}>
                    <ResponsiveGridLayout
                        className="dashboard-layout"
                        cols={this.props.cols || {lg: 10, md: 8, sm: 8, xs: 4, xxs: 2}}
                        layouts={layouts}
                        rowHeight={5}
                        onLayoutChange={this.onLayoutChange}
                        isDraggable={!this.props.disableDrag}
                        style={{height: '100%'}}
                        autoSize={false}
                    >
                        {items}
                    </ResponsiveGridLayout>
                </div>
            </div>
        );
    }

});
