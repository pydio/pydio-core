/**
 * Fx.ProgressBar
 *
 * @version		1.0
 *
 * @license		MIT License
 *
 * @author		Harald Kirschner <mail [at] digitarald [dot] de>
 * @copyright	Authors
 */

Fx.ProgressBar = new Class({

	Extends: Fx,

	options: {
		text: null,
		transition: Fx.Transitions.Circ.easeOut,
		link: 'cancel'
	},

	initialize: function(element, options) {
		this.element = $(element);
		this.parent(options);
		this.text = $(this.options.text);
		this.set(0);
	},

	start: function(to, total) {
		return this.parent(this.now, (arguments.length == 1) ? to.limit(0, 100) : to / total * 100);
	},

	set: function(to) {
		this.now = to;
		this.element.setStyle('backgroundPosition', (100 - to) + '% 0px');
		if (this.text) this.text.set('text', Math.round(to) + '%');
		return this;
	}

});