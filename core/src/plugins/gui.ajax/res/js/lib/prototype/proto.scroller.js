/**
 * @author Ryan Johnson <http://syntacticx.com/>
 * @copyright 2008 PersonalGrid Corporation <http://personalgrid.com/>
 * @package LivePipe UI
 * @license MIT
 * @url http://livepipe.net/control/scrollbar
 * @require prototype.js, slider.js, livepipe.js
 */

if(typeof(Prototype) == "undefined")
    throw "Control.ScrollBar requires Prototype to be loaded.";
if(typeof(Control.Slider) == "undefined")
    throw "Control.ScrollBar requires Control.Slider to be loaded.";
if(typeof(Object.Event) == "undefined")
    throw "Control.ScrollBar requires Object.Event to be loaded.";

Control.ScrollBar = Class.create({
    initialize: function(container,track,options){
        Control.ScrollBar.instances.push(this);
        this.enabled = false;
        this.notificationTimeout = false;
        this.container = $(container);
        this.container.scrollerInstance = this;
        this.boundMouseWheelEvent = this.onMouseWheel.bindAsEventListener(this);
        this.boundResizeObserver = this.onWindowResize.bind(this);
        this.track = $(track);
        this.handle = this.track.firstDescendant();
        this.options = Object.extend({
            active_class_name: 'scrolling',
            apply_active_class_name_to: this.container,
            notification_timeout_length: 125,
            handle_minimum_length: 25,
            scroll_to_smoothing: 0.01,
            scroll_to_steps: 15,
            scroll_to_precision: 100,
            proportional: true,
            custom_event: null,
            custom_event_handler: null,
            scroll_axis: 'vertical',
            /**
             * If this value is larger than 0 (e.g. 50), one mouse wheel event will scroll the page
             * by the given number of pixels (default behaviour in any program). If it is lower than 0,
             * the scrollbar's default behaviour will be used (scrolls down 1/20 of the page, no matter
             * how long it is). Initialization with e.g.:
             * new Control.ScrollBar(
                    document.getElementById('scrollbar_content'),
                    document.getElementById('scrollbar_track'),
                    { fixed_scroll_distance: 50 });
             */
            fixed_scroll_distance: 100,
            slider_options: {}
        },options || {});
        this.slider = new Control.Slider(this.handle,this.track,Object.extend({
            axis: this.options.scroll_axis,
            onSlide: this.onChange.bind(this),
            onChange: this.onChange.bind(this)
        },this.options.slider_options));
        this.recalculateLayout();
        Event.observe(window,'resize',this.boundResizeObserver);
        if (this.options.custom_event) {
            if (Object.isFunction(this.options.custom_event_handler)) {
                this.container.observe(this.options.custom_event, this.options.custom_event_handler);
            } else {
                this.container.observe(this.options.custom_event, this.boundResizeObserver);
            }
        }
        this.handle.observe('mousedown',function(){
            if(this.auto_sliding_executer)
                this.auto_sliding_executer.stop();
        }.bind(this));
        attachMobileScroll(container, 'vertical');
    },
    destroy: function(){
        Event.stopObserving(window,'resize',this.boundResizeObserver);
        if(this.options.active_class_name)
            $(this.options.apply_active_class_name_to).removeClassName(this.options.active_class_name);
        if (this.options.custom_event) {
            this.container.stopObserving(this.options.custom_event);
        }
        this.container.scrollerInstance = null;
    },
    scrollLength: function(){
        return (this.options.scroll_axis == 'vertical') ? this.container.scrollHeight : this.container.scrollWidth;
    },
    offsetLength: function(){
        return (this.options.scroll_axis == 'vertical') ? this.container.offsetHeight : this.container.offsetWidth;
    },
    enable: function(){
        this.enabled = true;
        this.container.observe('mouse:wheel',this.boundMouseWheelEvent);
        this.slider.setEnabled();
        this.track.show();
        if(this.options.active_class_name)
            $(this.options.apply_active_class_name_to).addClassName(this.options.active_class_name);
        this.notify('enabled');
    },
    disable: function(){
        this.enabled = false;
        this.container.stopObserving('mouse:wheel',this.boundMouseWheelEvent);
        this.slider.setDisabled();
        this.track.hide();
        if(this.options.active_class_name)
            $(this.options.apply_active_class_name_to).removeClassName(this.options.active_class_name);
        this.notify('disabled');
        this.reset();
    },
    reset: function(){
        this.slider.setValue(0);
    },
    recalculateLayout: function(){
        if(this.scrollLength() <= this.offsetLength())
            this.disable();
        else{
            this.enable();
            this.slider.trackLength = this.slider.maximumOffset() - this.slider.minimumOffset();
            if(this.options.proportional){
                this.slider.handleLength = Math.max(this.offsetLength() * (this.offsetLength() / this.scrollLength()),this.options.handle_minimum_length);
                if (this.options.scroll_axis == 'vertical')
                    this.handle.style.height = this.slider.handleLength + 'px';
                else
                    this.handle.style.width = this.slider.handleLength + 'px';
            }
            if( (this.options.scroll_axis == 'vertical' && parseInt(this.handle.style.height) > this.offsetLength())
                || parseInt(this.handle.style.width) > this.offsetLength()){
                this.disable();
                return;
            }
            this.scrollBy(0);
        }
    },
    onWindowResize: function(){
        this.recalculateLayout();
        this.scrollBy(0);
    },
    onMouseWheel: function(event){
        if(this.auto_sliding_executer) {
            this.auto_sliding_executer.stop();
        }
        if (this.options.fixed_scroll_distance > 0) {
            // Move the content by the given number of pixels each wheel event
            this.slider.setValueBy(-(this.options.fixed_scroll_distance * event.memo.delta / (this.scrollLength()-this.slider.trackLength)));
        } else {
            // Move the content by 1/20 of the page
            this.slider.setValueBy(-(event.memo.delta / 8));
        }
        event.stop();
        return false;
    },
    onChange: function(value){
        var scroll_pos = Math.round(value / this.slider.maximum * (this.scrollLength() - this.offsetLength()));
        if (this.options.scroll_axis == 'vertical')
            try{this.container.scrollTop = scroll_pos;}catch(e){}
        else
            this.container.scrollLeft = scroll_pos;
        if(this.notificationTimeout)
            window.clearTimeout(this.notificationTimeout);
        this.notificationTimeout = window.setTimeout(function(){
            this.notify('change',value);
        }.bind(this),this.options.notification_timeout_length);
    },
    getCurrentMaximumDelta: function(){
        return this.slider.maximum * (this.scrollLength() - this.offsetLength());
    },
    getContainerOffset: function(element) {
        var offset = element.positionedOffset();
        while (element.getOffsetParent() != this.container)
        {
            element         = element.getOffsetParent();
            offset[0]      += element.positionedOffset()[0];
            offset[1]      += element.positionedOffset()[1];
            offset.top     += element.positionedOffset().top;
            offset.left    += element.positionedOffset().left;
        }
        return offset;
    },
    getDeltaToElement: function(element){

        if (this.options.scroll_axis == 'vertical')
            return this.slider.maximum * ((this.getContainerOffset(element).top + (element.getHeight() / 2)) - (this.container.getHeight() / 2));
        else
            return this.slider.maximum * ((this.getContainerOffset(element).left + (element.getWidth() / 2)) - (this.container.getWidth() / 2));
    },
    scrollTo: function(y,animate){
        var precision = this.options.scroll_to_precision,
            current_maximum_delta = this.getCurrentMaximumDelta();
        if (precision == 'auto')
            precision = Math.pow(10, Math.ceil(Math.log(current_maximum_delta)/Math.log(10)));
        if(y == 'top')
            y = 0;
        else if(y == 'bottom')
            y = current_maximum_delta;
        else if(typeof(y) != "number")
            y = this.getDeltaToElement($(y));
        if(this.enabled){
            y = Math.max(0,Math.min(y,current_maximum_delta));
            if(this.auto_sliding_executer)
                this.auto_sliding_executer.stop();
            var target_value = y / current_maximum_delta;
            var original_slider_value = this.slider.value;
            var delta = (target_value - original_slider_value) * current_maximum_delta;
            if(animate){
                this.auto_sliding_executer = new PeriodicalExecuter(function(){
                    if(Math.round(this.slider.value * precision) / precision < Math.round(target_value * precision) / precision || Math.round(this.slider.value * precision) / precision > Math.round(target_value * precision) / precision){
                        this.scrollBy(delta / this.options.scroll_to_steps);
                    }else{
                        this.auto_sliding_executer.stop();
                        this.auto_sliding_executer = null;
                        if(typeof(animate) == "function")
                            animate();
                    }
                }.bind(this),this.options.scroll_to_smoothing);
            }else
                this.scrollBy(delta);
        }else if(typeof(animate) == "function")
            animate();
    },
    scrollBy: function(y){
        if(!this.enabled)
            return;
        this.slider.setValueBy(y / (this.getCurrentMaximumDelta() == 0 ? 1 : this.getCurrentMaximumDelta()) );
    }
});
Object.extend(Control.ScrollBar,
{
    instances: [],

    findByElementId: function(id)
    {
        return Control.ScrollBar.instances.find(function(instance)
        {
            return (instance.container.id && instance.container.id == id);
        });
    }
});
Object.Event.extend(Control.ScrollBar);