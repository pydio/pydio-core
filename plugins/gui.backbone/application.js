
jQuery(function($) {

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
            todos.url = 'http://debian.local/ajaxplorer/index.php?get_action=ls&options=al&dir=' + encodeURIComponent(path);
            todos.fetch();
        }
    });

    var myRouter = new AppRouter();

    var Todo = Backbone.Model.extend({

        defaults:{
            title: 'Check attributes property of the both model instances in the console.',
            completed: true
        },
        initialize: function(){
            console.log('This model has been initialized.');
            this.on('change:title', function(){
                console.log('- Values for this model have changed.');
            });
            this.on("error", function(model, error){
                console.log(error);
            });
        }

    });
// We can then create our own concrete instance of a (Todo) model // with no values at all:
// or with some arbitrary data:
    var todo2 = new Todo({id:2});
    console.log(todo2);

    todo2.set({title:'A new title'});


    var TodoView = Backbone.View.extend({
        tagName: 'li',
        // Cache the template function for a single item.
        todoTpl: _.template( '<div class="edit" ><img src="/ajaxplorer/plugins/gui.ajax/res/themes/umbra/images/mimes/16/<%= icon %>"><%= title %></div>' ),
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
            // executed when todo label is double clicked
            //todos.url = 'http://debian.local/ajaxplorer/index.php?get_action=ls&options=al&dir=' + encodeURIComponent(this.model.id);
            //todos.fetch();
            myRouter.navigate(this.model.id, true);
        },
        hout: function() {
            // executed when todo loses focus
            this.$el.css({fontSize:'1em'});
        },
        hover: function( e ) {
            // executed on each keypress when in todo edit mode, // but we'll wait for enter to get in action
            this.$el.css({fontSize:'1.1em'});
        } });

    var TodosCollection = Backbone.Collection.extend({
        model: Todo,
        url:'http://debian.local/ajaxplorer/index.php?get_action=ls&options=al&dir=%2F',
        parse: function( response ) {
            var parsed = Jath.parse(
                [ '//tree', {
                    id: '@filename',
                    title: '@text',
                    isLeaf:'@is_file',
                    icon:'@icon'
                } ], response );
            var first = parsed[0];
            parsed[0]
            return parsed;
        }
        //localStorage: new Store('todos-backbone')
    }, ['rootNode']);

    var todos = new TodosCollection();
    todos.fetch();
    
    var TodosView = Backbone.View.extend({
        tagName: 'ul', // required, but defaults to 'div' if not set
        className: 'container', // optional, you can assign multiple classes to this property like id: 'todos', // optional
        initialize: function(){
            todos.on('all', this.render, this );

        },
        render: function(){
            this.$el.html('');
            todos.each(function(todo){
                var view = new TodoView({model:todo});
                this.$el.append(view.render().el);
            }, this);
            return this;
        }
    });

    var todosView = new TodosView({collection:todos});
    $('body').html(todosView.render().el);

    Backbone.history.start({silent:true, pushState: false, root: "/ajaxplorer/plugins/gui.backbone/"});


});

