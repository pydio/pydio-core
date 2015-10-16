//Copyright 2012 Cameron Wengert

//Licensed under the Apache License, Version 2.0 (the "License");
//you may not use this file except in compliance with the License.
//You may obtain a copy of the License at
//
//http://www.apache.org/licenses/LICENSE-2.0
//
//Unless required by applicable law or agreed to in writing, software
//distributed under the License is distributed on an "AS IS" BASIS,
//WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//See the License for the specific language governing permissions and
//limitations under the License.

Class.create("Resizable", {
    
        initialize: function(elm) {
            /*
                Method:initialize

                Create a new resizable object

                Parameters:
                    elm - The Element to make resizable
                    options - Additional optional arguments available to be passed in as an object
            */
            
            var defaults = {
                onStart: null,
                onEnd: null,
                'default_style':true,
                'class_name':'resizableHandle'
            };

            this.options = Object.extend(defaults, arguments[1] || {});

            if (this.options.onStart) {
                this.onStart = this.options.onStart;
            }

            if (this.options.onEnd) {
                this.onEnd = this.options.onEnd;
            }

            //Container
            this.c = elm;
            if(!this.c.getStyle('position')) {
                this.c.setStyle({'position':'relative'});
            }
            
            //Resizer Element
            this.resizer = new Element('div', {'class':this.options['class_name']});
            if (this.options['default_style']) {
                this.resizer.writeAttribute({
                    'style':'width:10px; height:10px; position:absolute; background-color:grey;'
                });
            }

            // Hide and place the resizer to get its dimensions
            this.resizer.toggle();
            this.c.insert(this.resizer);
            
            this.setResizer();

            this.start_count = 0;
           
            // Setup Events
            this.resizer.observe('mousedown', function(ev) {
                    if (this.onStart && this.start_count < 1) {
                        this.onStart();
                    }
                this.c.observe('resizer:mousemove', function(ev) {
                    this.resizing(ev.memo);
                }.bind(this));

                this.c.observe('resizer:mouseup', function(ev) {
                    this.start_count = 0;
                    if (this.onEnd) {
                        this.onEnd();
                    }
                    this.c.stopObserving('resizer:mousemove');
                    this.c.stopObserving('resizer:mouseup');
                }.bind(this));
                
                var p = Event.pointer(ev);
                this.start_x = p.x;
                this.start_y = p.y;

                document.observe('mousemove', function(ev) {
                    this.c.fire('resizer:mousemove', ev);
                }.bind(this));
                
                document.observe('mouseup', function(ev) {
                    this.c.fire('resizer:mouseup', ev);
                }.bind(this));
            }.bind(this));

            // Disable ondragstart
            this.resizer.ondragstart = function() { return false; };
            // Show resizer now that it's ready
            this.resizer.toggle();
        },

        setResizer: function() {
            /*
                Method:setResizer

                Position the resizer element in the bottom right hand corner
                of the container element. 
            */

           
            var r_layout = this.resizer.getLayout();
            var c_layout = this.c.getLayout();

            Element.setStyle(this.resizer,{
                'position':'absolute',
                'left':c_layout.get('padding-box-width') - r_layout.get('padding-box-width') + 'px',
                'top':c_layout.get('padding-box-height') - r_layout.get('padding-box-width') + 'px'
            });
        },

        resizing: function(ev) {
            /*
                Method:resizing

                Change the dimension of the the container based on the ev argument. 

                Parameters:
                    ev - The event memo data (mouse coords) that triggered the resizing

            */
            var p = Event.pointer(ev);
            var xdiff = p.x - this.start_x;
            var ydiff = p.y - this.start_y;
            
            var c_layout = this.c.getLayout();
            
            this.c.setStyle({'width': c_layout.get('width') + xdiff + 'px'});
            this.c.setStyle({'height': c_layout.get('height') + ydiff + 'px' }); 

            this.start_x = p.x;
            this.start_y = p.y;
            
            // We moved the container so lets set the resizer to match
            this.setResizer();
        }

    });
