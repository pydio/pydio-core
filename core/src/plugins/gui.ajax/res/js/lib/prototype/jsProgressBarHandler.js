/*****************************************************************
 *
 * jsProgressBarHandler 0.3.3 - by Bramus! - http://www.bram.us/
 *
 * v 0.3.3 - 2008.11.10 - UPD: fixed IE compatibility issue (thanks Kevin - Sep 19 2008 / 6pm)
 *                      - UPD: setPercentage now parses the targetPercentage to an Integer to avoid infinite loop (thanks Jack - Sep 07 2008 / 9pm)
 *                      - UPD: Moved from Event.Observe(window, 'load', fn) to document.observe('dom:loaded', fn) in order to force people to use an up to date Prototype release.
 *                      - UPD: setPercentage now takes an overrideQueue param. If set the current queue is cleared.
 *                      - ADD: Added onTick callback event which gets called when the percentage is updated.
 *                      - ADD: Added stable (as in "non-crashing") versions of the additions which first surfaced in the (unreleased) 0.3.2 release
 *                             Preloading support partially implemented in IE as all versions (IE6,7&8) are quite hard to tame (one time they work, the next reload they don't anymore)
 * v 0.3.2 - 2008.04.09 (*UNRELEASED*)
 *                      - ADD: implemented preloading of images to avoid slight flicker when switching images (BUGGY!)
 *                      - ADD: percentage image now has class percentImage and percentage Text now has class percentText; This allows you to style the output easily.
 * v 0.3.1 - 2008.02.20 - UPD: fixed queue bug when animate was set to false (thanks Jamie Chong)
 *                      - UPD: update Prototype to version 1.6.0.2
 * v 0.3.0 - 2008.02.01 - ADD: animation queue, prevents from the progressbar getting stuck when multiple calls are made during an animation
 *                      - UPD: multiple barImages now work properly in Safari
 * v 0.2.1 - 2007.12.20 - ADD: option : set boxImage
 *                        ADD: option : set barImage (one or more)
 *                        ADD: option : showText
 * v 0.2   - 2007.12.13 - SYS: rewrite in 2 classs including optimisations
 *                        ADD: Config options
 * v 0.1   - 2007.08.02 - initial release
 *
 * @see http://www.barenakedapp.com/the-design/displaying-percentages on how to create a progressBar Background Image!
 *
 * Licensed under the Creative Commons Attribution 2.5 License - http://creativecommons.org/licenses/by/2.5/
 *
 *****************************************************************/
/**
 * JS_BRAMUS Object
 * -------------------------------------------------------------
 */

if (!JS_BRAMUS) { var JS_BRAMUS = {}; }


JS_BRAMUS.jsProgressBar = Class.create({
	 
		el				: null,
		id				: null,
		percentage		: null,
		options			: null,
		initialPos		: null,
		initialPerc		: null,
		pxPerPercent	: null,
		backIndex		: null,
		numPreloaded	: null,
		running			: null,
		queue			: false,


    /**
     * Constructor
     *
     * @return void
     * -------------------------------------------------------------
     * @param el
     * @param percentage
     * @param options
     */
	 
		initialize : function(el, percentage, options) {

			this.options = Object.extend({
				animate		: true,										
				showText	: true,										
				width		: 120,
				boxImage	: 'images/progress_box.gif',	
				barImage	: 'images/progress_bar.gif',
				height		: 12,
                visualStyle : '',
				onTick		: function(pbObj) { return true; }
			}, options || {});

			// datamembers from arguments
			this.el				= $(el);
			this.id				= $(el).id;
			this.percentage		= 0;							// Set to 0 intially, we'll change this later.
			this.backIndex		= 0;							// Set to 0 initially
			this.numPreloaded	= 0;							// Set to 0 initially
			this.running		= false;						// Set to false initially
			this.queue			= [];						// Set to empty Array initially
			this.visualsInitialized = false;

			// datamembers which are calculatef
			this.imgWidth		= this.options.width * 2;		// define the width of the image (twice the width of the progressbar)
			this.initialPos		= this.options.width * (-1);	// Initial postion of the background in the progressbar (0% is the middle of our image!)
			this.pxPerPercent	= this.options.width / 100;		// Define how much pixels go into 1%
			this.initialPerc	= percentage;					// Store this, we'll need it later.

			// enfore backimage array
			if (this.options.barImage.constructor != Array) { 	// used to be (but doesn't work in Safari): if (this.options.barImage.constructor.toString().indexOf("Array") == -1) {
				this.options.barImage = [this.options.barImage];
			}

			// preload Images
			this.preloadImages();

		},


	/**
	 * Preloads the images needed for the progressbar
	 *
	 * @return void
	 * -------------------------------------------------------------
	 */

		preloadImages	: function() {

			// loop all barimages
			for (var i = 0; i < this.options.barImage.length; i++) {

				// create new image ref
				var newImage = null;
				newImage = new Image();

				// set onload, onerror and onabort functions
				newImage.onload		= function() { this.numPreloaded++; }.bind(this);
				newImage.onerror	= function() { this.numPreloaded++; }.bind(this);
				newImage.onabort	= function() { this.numPreloaded++; }.bind(this);

				// set image source (preload it!)
				newImage.src = this.options.barImage[i];

				// image is in cache
				if (newImage.complete) {
					this.numPreloaded++;
				}
				
			}

			// if not IE, check if they're loaded
			if (!Prototype.Browser.IE) {
				this.checkPreloadedImages();

			// if IE, just init the visuals as it's quite hard to tame all IE's
			} else {
				this.initVisuals();
			}

		},


	/**
	 * Check whether all images are preloaded and loads the percentage if so
	 *
	 * @return void
	 * -------------------------------------------------------------
	 */

	 	checkPreloadedImages	: function() {

			// all images are loaded, go init the visuals
			if (parseInt(this.numPreloaded,10) >= parseInt(this.options.barImage.length,10) ) {

				// initVisuals
				this.initVisuals();

			// not all images are loaded ... wait a little and then retry
			} else {

				if ( parseInt(this.numPreloaded,10) <= parseInt(this.options.barImage.length,10) ) {
					// $(this.el).update(this.id + ' : ' + this.numPreloaded + '/' + this.options.barImage.length);
					setTimeout(function() { this.checkPreloadedImages(); }.bind(this), 100);
				}

			}

		},


	/**
	 * Intializes the visual output and sets the percentage
	 *
	 * @return void
	 * -------------------------------------------------------------
	 */				
		
		initVisuals		: function () {

			// create the visual aspect of the progressBar
			$(this.el).update(
				'<img id="' + this.id + '_percentImage" src="' + this.options.boxImage + '" alt="0%" style="position:absolute;width: ' + this.options.width + 'px; height: ' + this.options.height + 'px; background-position: ' + this.initialPos + 'px 50%; background-image: url(' + this.options.barImage[this.backIndex] + '); padding: 0; margin: 0; '+this.options.visualStyle+'" class="percentImage" />' +
				((this.options.showText == true)?'<span id="' + this.id + '_percentText" class="percentText">0%</span>':''));
		
			this.visualsInitialized = true;
			// set the percentage
			this.setPercentage(this.initialPerc);
		},


    /**
     * Sets the percentage of the progressbar
     *
     * @return void
     * -------------------------------------------------------------
     * @param targetPercentage
     * @param clearQueue
     */
		setPercentage	: function(targetPercentage, clearQueue) {

        if(!this.visualsInitialized){
				this.initialPerc = targetPercentage;
				this.initVisuals();
				return;
			}
			// if clearQueue is set, empty the queue and then set the percentage
			if (clearQueue) {
				
				this.percentage = (this.queue.length != 0) ? this.queue[0] : targetPercentage;
				this.timer		= null;
				this.queue 		= [];
				
				setTimeout(function() { this.setPercentage(targetPercentage); }.bind(this), 10);
				
			// no clearQueue defined, set the percentage
			} else {
			
				// add the percentage on the queue
				this.queue.push(targetPercentage);
				
				// process the queue (if not running already)
				if (this.running == false) {
					this.processQueue();
				}
			}
			
		},
	
	
	/**
	 * Processes the queue
	 *
	 * @return void
	 * -------------------------------------------------------------
	 */
		
		processQueue	: function() {
			
			// stuff on queue?
			if (this.queue.length > 0) {
				
				// tell the world that we're busy
				this.running = true;
				// process the entry
				this.processQueueEntry(this.queue[0]);
				
			// no stuff on queue
			} else {
					
				// return;

			}
			
		},


    /**
     * Processes an entry from the queue (viz. animates it)
     *
     * @return void
     * -------------------------------------------------------------
     * @param targetPercentage
     */
		
		processQueueEntry	: function(targetPercentage) {
								
			// get the current percentage
			var curPercentage	= parseInt(this.percentage,10);
			
			// define the new percentage
			if ((targetPercentage.toString().substring(0,1) == "+") || (targetPercentage.toString().substring(0,1) == "-")) {
				targetPercentage	= curPercentage + parseInt(targetPercentage);
			}
		
			// min and max percentages
			if (targetPercentage < 0)		targetPercentage = 0;
			if (targetPercentage > 100)		targetPercentage = 100;
			
			// if we don't need to animate, just change the background position right now and return
			if (this.options.animate == false) {
				
				// remove the entry from the queue 
				this.queue.splice(0,1);	// @see: http://www.bram.us/projects/js_bramus/jsprogressbarhandler/#comment-174878
				
				// Change the background position (and update this.percentage)
				this._setBgPosition(targetPercentage);
			
				// call onTick
				if (!this.options.onTick(this)) {
					return;	
				}
				
				// we're not running anymore
				this.running = false;
				
				// continue processing the queue
				this.processQueue();
				
				// we're done!
				return;
			}

            var newPercentage, callTick;
			// define if we need to add/subtract something to the current percentage in order to reach the target percentage
			if (targetPercentage != curPercentage) {
				if (curPercentage < targetPercentage) {
					newPercentage = curPercentage + 1;
				} else {
					newPercentage = curPercentage - 1;	
				}						
				callTick = true;						
			} else {
				newPercentage = curPercentage;
				callTick = false;
			}											
									
			// Change the background position (and update this.percentage)
			this._setBgPosition(newPercentage);
			
			// call onTick
			if (callTick && !this.options.onTick(this)) {
				return;	
			}
			
			// Percentage not reached yet : continue processing entry
			if (curPercentage != newPercentage) {
				
				this.timer = setTimeout(function() { this.processQueueEntry(targetPercentage); }.bind(this), 3);
				
			// Percentage reached!
			} else {
												  
				// remove the entry from the queue
				this.queue.splice(0,1);
				
				// we're not running anymore
				this.running = false;	
				
				// unset timer
				this.timer = null;
				
				// process the rest of the queue
				this.processQueue();
				
			}
			
		},
	
	
	/**
	 * Gets the percentage of the progressbar
	 *
	 * @return int
	 */
		getPercentage : function(id) {
			return this.percentage;
		},
	
	
	/**
	 * Set the background position
	 *
	 * @param percentage int
	 */
		_setBgPosition		: function(percentage) {
			// adjust the background position
				$(this.id + "_percentImage").style.backgroundPosition 	= (this.initialPos + (percentage * this.pxPerPercent)) + "px 50%";
										
			// adjust the background image and backIndex
				var newBackIndex										= Math.floor((percentage-1) / (100/this.options.barImage.length));
				
				if ((newBackIndex != this.backIndex) && (this.options.barImage[newBackIndex] != undefined)) {
					$(this.id + "_percentImage").style.backgroundImage 	= "url(" + this.options.barImage[newBackIndex] + ")";
				}
				
				this.backIndex											= newBackIndex;
			
			// Adjust the alt & title of the image
				$(this.id + "_percentImage").alt 						= percentage + "%";
				$(this.id + "_percentImage").title 						= percentage + "%";
				
			// Update the text
				if (this.options.showText == true) {
					$(this.id + "_percentText").update("" + percentage + "%");
				}
			// adjust datamember to stock the percentage
				this.percentage	= percentage;
		}
});