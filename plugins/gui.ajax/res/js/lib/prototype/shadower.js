/*******************************************************************************************************
 * shadower.js
 * Author: Erik Rasmussen
 * Email: erikwordpressplugins -at- gmail
 * Info: http://www.erik-rasmussen.com/blog/2006/12/04/the-shadower-realistic-drop-shadows-in-javascript/
 *******************************************************************************************************/
var Shadower =
{
	shadow: function(element)
	{
		if(Modernizr.boxshadow){
			element.addClassName("css_boxshadow");
			return;
		}
		
		element = $(element);
		var options = Object.extend(
		{
			distance: 8,
			angle: 130,
			opacity: 0.7,
			nestedShadows: 4,
			color: '#000000'
		}, arguments[1] || {});
		this.options = options;
		var positionStyle = Element.getStyle(element, 'position');
		var parent = element.parentNode;
		if (!element.shadowZIndex)
		{
			if (positionStyle != 'absolute' && positionStyle != 'fixed')
			{
				var placeHolder = this.idSafeClone(element);
				placeHolder.id = null;
				parent.insertBefore(placeHolder, element);
				Position.absolutize(element);
				Position.clone(placeHolder, element);
				element.style.margin = '0';
				placeHolder.style.visibility = 'hidden';
				positionStyle = 'absolute';
			}
			element.shadowZIndex = new Number(Element.getStyle(element, 'zIndex') ? Element.getStyle(element, 'zIndex') : 1);
			element.style.zIndex = element.shadowZIndex + options.nestedShadows;
		}
		if (arguments[2]){ 
			this.deshadow(element);
		}		
		if (!element.shadows)
		{
			element.shadows = new Array(options.nestedShadows);
			for (var i = 0; i < options.nestedShadows; i++)
			{
				var shadow = new Element('div', {className:'shadow_class'});
				Element.hide(shadow);
				shadow.appendChild(document.createTextNode(' '));
				if (parent)	parent.appendChild(shadow);
				shadow.style.position = positionStyle;
				shadow.style.backgroundColor = options.color;
				Element.setOpacity(shadow, options.opacity / options.nestedShadows);
				shadow.style.zIndex = element.shadowZIndex + i;				
				element.shadows[i] = shadow;
			}
		}
		var legendHeight = this.getLegendHeight(element);
		/* position shadows*/
		Position.prepare();
		var offsets = Position.positionedOffset(element);
		var topOffset = -Math.cos(-options.angle * Math.PI / 180) * options.distance;
		var leftOffset = -Math.sin(-options.angle * Math.PI / 180) * options.distance;
		element.shadows.each(function(shadow, i){
			shadow.style.top = Math.ceil(offsets[1] + topOffset + i + (legendHeight / 2)) + 'px';
			shadow.style.left = (offsets[0] + leftOffset + i) + 'px';
			shadow.style.width = (element.offsetWidth - (2 * i)) + 'px';
			shadow.style.height = (element.offsetHeight - (2 * i) - (legendHeight / 2)) + 'px';
			Element.show(shadow);
		});
	},

	showShadows : function(element)
	{
		if(!element.shadows) return;
		var options = this.options;
		var legendHeight = this.getLegendHeight(element);
		
		Position.prepare();
		var offsets = Position.positionedOffset(element);
		var topOffset = -Math.cos(-options.angle * Math.PI / 180) * options.distance;
		var leftOffset = -Math.sin(-options.angle * Math.PI / 180) * options.distance;
		if(element.shadowZIndex && element.getStyle('zIndex') && element.getStyle('zIndex') <= element.shadowZIndex){
			element.setStyle({zIndex:element.shadowZIndex+20});
		}
		element.shadows.each(function(shadow, i)
		{
			shadow.style.top = Math.ceil(offsets[1] + topOffset + i + (legendHeight / 2)) + 'px';
			shadow.style.left = (offsets[0] + leftOffset + i) + 'px';
			shadow.style.width = (element.offsetWidth - (2 * i)) + 'px';
			shadow.style.height = (element.offsetHeight - (2 * i) - (legendHeight / 2)) + 'px';
			Element.show(shadow);
		});		
	},
	
	idSafeClone: function(node)
	{
		var clone = node.cloneNode(false);
		if (clone.hasAttribute && clone.hasAttribute('id')){
			clone.removeAttribute('id');
		}
		var clonedChildren = $A(node.childNodes).collect(this.idSafeClone.bind(this));
		clonedChildren.each(function(child)
		{
			clone.appendChild(child);
		});
		return clone;
	},

	getLegendHeight: function(element)
	{
		if (element.nodeName.toLowerCase() == 'fieldset')
		{
			var legend;
			$A(element.childNodes).each(function(child)
			{
				if (child.nodeName.toLowerCase() == 'legend')
				{
					legend = child;
					throw $break;
				}
			});
			if (legend){
				return Element.getDimensions(legend).height;
			}
		}
		return 0;
	},

	deshadow: function(element)
	{
		if(Modernizr.boxshadow && $(element)){
			$(element).removeClassName("css_boxshadow");
			return;
		}
		
		element = $(element);
		if (element.shadows)
		{
			element.shadows.each(Element.remove);
			element.shadows = null;
		}
	},

	shadowWithClass: function(cssClass, options)
	{
		$$('.' + cssClass).each(function(element)
		{
			this.shadow(element, options);
		}.bind(this));
	}
};

if ((typeof Prototype == 'undefined') || (typeof Element == 'undefined') || (typeof Element.Methods == 'undefined') || parseFloat(Prototype.Version.split(".")[0] + "." + Prototype.Version.split(".")[1]) < 1.5){
	throw("Shadower requires the Prototype JavaScript framework >= 1.5.0");
}