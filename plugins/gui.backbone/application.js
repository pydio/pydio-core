
jQuery(function($) {

	var BASE_URL = '/ajaxplorer/index.php?get_action=ls&options=al&dir=';

    // Keep track of the original sync method so we can
    // delegate to it at the end of our new sync.
    var originalSync = Backbone.sync ;
    Backbone.sync = function(method, model, options){

        options = _.extend(options,{
            dataType: 'xml',
            contentType: 'application/xml',
            processData: false
        });

        originalSync.apply(Backbone, [ method, model, options ]);
    };


    var AppRouter = Backbone.Router.extend({
        routes : {
            "*path"    : "lsNode"
        },
        lsNode: function(path){
            if(!path) path = "/";
            console.log("NAVIGATE TO " + path);
            todos.url = BASE_URL + encodeURIComponent(path);
            todos.fetch();
        }
    });

    var myRouter = new AppRouter();

    var Node = Backbone.Model.extend({

        defaults:{
            loaded: false
        },
        initialize: function(){
        	this.childNodes = new NodesCollection();
            console.log('This model has been initialized.');
            this.on('change:title', function(){
                console.log('- Values for this model have changed.');
            });
            this.on("error", function(model, error){
                console.log(error);
            });
            this.childNodes.on('all', function(){
	            this.set('loaded', true);
	            this.trigger('loaded', true);	            
            }, this);
        },
        fetchChildren : function(){
	        this.childNodes.url = BASE_URL + encodeURIComponent(this.id);
	        this.childNodes.fetch();
        },
        getChildren : function(){
	        return this.childNodes.models;
        }

    });

    var NodesCollection = Backbone.Collection.extend({
        model: Node,
        url:BASE_URL,
        parse: function( response ) {
            var parsed = Jath.parse(
                [ '//tree', {
                    id: '@filename',
                    title: '@text',
                    isLeaf:'@is_file',
                    icon:'@icon', 
                    mimestring:'@mimestring',
                    bytesize:'@bytesize',
                    filesize:'@filesize',
                    ajxp_modiftime:'@ajxp_modiftime'
                } ], response );
            parsed.shift();
            return parsed;
        }
    });

    var ListEntryView = Backbone.View.extend({
        tagName: 'tr',
        // Cache the template function for a single item.
        todoTpl: _.template( '<td><div class="edit" ><img src="/ajaxplorer/plugins/gui.ajax/res/themes/umbra/images/mimes/16/<%= icon %>"><%= title %></div></td><td><%= id %></td><td><%= mimestring %></td><td><%= filesize %></td>' ),
        events: {
            'click .edit': 'edit',
            'mouseover .edit': 'hover',
            'mouseout .edit':   'hout'
        },
        initialize: function() {
            this.model.on( 'change', this.render, this ); 
        },        
        // Re-render the titles of the todo item.
        render: function() {
            this.$el.html( this.todoTpl( this.model.toJSON() ) ); this.input = this.$('.edit');
            return this;
        },
        edit: function() {
            myRouter.navigate(this.model.id, true);
        },
        hout: function() {
            // executed when todo loses focus
            this.$el.css({textDecoration:'none'});
        },
        hover: function( e ) {
            // executed on each keypress when in todo edit mode, // but we'll wait for enter to get in action
            this.$el.css({textDecoration:'underline'});
        } });


    var todos = new NodesCollection();
    todos.fetch();
    
    var ListView = Backbone.View.extend({
        tagName: 'table', // required, but defaults to 'div' if not set
        className: 'ListView', // optional, you can assign multiple classes to this property like id: 'todos', // optional
        initialize: function(){
        },
        setCollection:function(collection){
        	if(this.collection){
	        	this.collection.off('all');
        	}
	      	this.collection = collection;  
            this.collection.on('all', this.render, this );
            this.render();
        },
        render: function(){
            this.$el.html('');
            if(!this.collection) return;
            this.collection.each(function(todo){
                var view = new ListEntryView({model:todo});
                this.$el.append(view.render().el);
            }, this);
            return this;
        }
    });

var COLLAPSE_SPEED = 200;
TreeView = Backbone.View.extend({
    tagName: 'li',
    template: '<a class="node-collapse" href="#"><span class="node-label"></span></a><ul class="nav nav-list node-tree"></ul>',

    initialize: function() {
 
        // Listen to model changes for updating view
        this.model.bind('loaded', function(value){
        	if(!this.collapsed) return;
        	this.collapsed = false;        	
        	this.render();
        }, this);

        this.model.bind('change', this.update, this);

        // Collapse state
        this.collapsed = true;
    },

    setupEvents: function() {
        // Hack to get around event delegation not supporting ">" selector
        var that = this;
        this.$('> .node-collapse').click(function() { that.toggleCollapse(); return false; });
    },

    toggleCollapse: function() {
        this.collapsed = !this.collapsed;
        todosView.setCollection(this.model.childNodes);        
        if(!this.model.get('loaded')){
	        this.model.fetchChildren();
	        this.collapsed = true;
	        return;
        }
        if (this.collapsed)
        {
            this.$('> .node-collapse i').attr('class', 'icon-plus');
            this.$('> .node-tree').slideUp(COLLAPSE_SPEED);
        }
        else
        {        	
            this.$('> .node-collapse i').attr('class', 'icon-minus');
            this.$('> .node-tree').slideDown(COLLAPSE_SPEED);	            
        }
    },

    update: function() {
        this.$('> a span.node-label').html(this.model.get('title'));
        this.collapsed ? this.$('> .node-tree').slideUp(COLLAPSE_SPEED) : this.$('> .node-tree').slideDown(COLLAPSE_SPEED);
    },

    render: function() {
        // Load HTML template and setup events
        this.$el.html(this.template);
        this.setupEvents();

        // Render this node
        this.update();

        // Build child views, insert and render each
        var tree = this.$('> .node-tree'), childView = null;
        _.each(this.model.getChildren(), function(model) {
            childView = new TreeView({
                model: model,
            });
            childView.$el.hide();
            tree.append(childView.$el);            
            childView.render();
            childView.$el.slideDown(COLLAPSE_SPEED);
        });

        /* Apply some extra styling to views with children */
        if (childView)
        {
            // Add bootstrap plus/minus icon
            this.$('> .node-collapse').prepend($('<i class="icon-plus"/>'));

            // Fixup css on last item to improve look of tree
            childView.$el.addClass('last-item').before($('<li/>').addClass('dummy-item'));   
        }

        return this;
    },
});

	var rootNode = new Node({id:"/", title:"Root"});
	var treeView = new TreeView({model:rootNode});
    $('body').html(treeView.render().el);
    treeView.render().$el.addClass('TreeView');
    
    var todosView = new ListView({collection:todos});
    $('body').append(todosView.render().el);

    //Backbone.history.start({silent:true, pushState: false, root: "/ajaxplorer/plugins/gui.backbone/"});


});

