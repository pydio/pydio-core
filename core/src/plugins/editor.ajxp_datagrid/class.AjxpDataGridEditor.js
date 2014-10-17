Class.create("AjxpDataGridEditor", AbstractEditor, {

    _dataSource:null,
    _lists: null,

    initialize: function($super, oFormObject, editorOptions)
    {
        editorOptions = Object.extend({
            fullscreen:true
        }, editorOptions);
        $super(oFormObject, editorOptions);
        this._lists = $A();

    },

    destroy: function(){
        // TODO: Shall we destroy the SVG objects?
        this._lists.each(function(resultPane){
            resultPane.destroy();
        });
        this._lists = $A();
    },

    open : function($super, node){
        $super(node);
        this.node = node;
        if(this.node.getMetadata().get("grid_header_title")){
            this.element.down("#grid_container").insert({before:new Element('div', {className:'tabrow'}).update(this.node.getMetadata().get("grid_header_title"))});
        }
        if(this.node.getMetadata().get("grid_element_class")){
            this.element.addClassName(this.node.getMetadata().get("grid_element_class"));
        }

        this._uniqueSource = this.node.getMetadata().get("grid_datasource");
        if(this._uniqueSource){
            // Add a File List  with correct parameters
            this.fRP = new FetchedResultPane(this.element.down("#grid_container"), {
                displayMode:'list',
                fixedDisplayMode:'list',
                rootNodeLabel:this.node.getLabel(),
                selectionChangeCallback:function(){},
                nodeProviderProperties: this._uniqueSource.toQueryParams()
            });
            this.fRP._dataLoaded = false;
            this.fRP.showElement(true);
            this._lists.push(this.fRP);
        }else{
            var i = 1;
            while(this.node.getMetadata().get("grid_datasource_" + i)){
                var dS = this.node.getMetadata().get("grid_datasource_" + i);
                var title = this.node.getMetadata().get("grid_datatitle_" + i);
                if(title){
                    this.element.down("#grid_container").insert(new Element('div',{className:'multiple_grid_title'}).update(title));
                }
                var newContainer = new Element("div", {id:'grid_container_'+i,className:'multiple_grid_container'});
                this.element.down("#grid_container").insert(newContainer);
                // Add a File List  with correct parameters
                var params = {
                    fit:'content',
                    displayMode:'list',
                    fixedDisplayMode:'list',
                    rootNodeLabel:this.node.getLabel(),
                    selectionChangeCallback:function(){},
                    nodeProviderProperties: dS.toQueryParams()
                };
                if(this.node.getMetadata().get("filesList.sortColumn") != undefined){
                    params['defaultSortColumn'] = this.node.getMetadata().get("filesList.sortColumn");
                    params['defaultSortDescending'] = this.node.getMetadata().get("filesList.sortDescending");
                }
                var frp = new FetchedResultPane(newContainer, params);
                frp._dataLoaded = false;
                frp.showElement(true);
                this._lists.push(frp);
                i++;
            }
        }
        this.element.fire("editor:updateTitle", this.node.getLabel());

    },

    resize: function(size){
        fitHeightToBottom(this.element);
        fitHeightToBottom(this.element.down("#grid_container"));
        this._lists.each(function(pane){
            pane.resize(size);
        });
    }

});