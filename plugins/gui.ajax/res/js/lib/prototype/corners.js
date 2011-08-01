/*
 * Prototype/Scriptaculous corner plugin version 0.1 (6/29/2007)
 *
 * Based on the jQuery corner plugin version 1.7 (1/26/2007)
 *
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *   http://www.gnu.org/licenses/gpl.html
 */
/**
 * The Effect.Corner class provides a simple way of styling DOM elements.  
 *
 * Effect.Corner constructor takes two arguments:  new Effect.Corner(element, "effect corners width")
 *
 *   effect:  The name of the effect to apply, such as round or bevel. 
 *            If you don't specify an effect, rounding is used.
 *
 *   corners: The corners can be one or more of top, bottom, tr, tl, br, or bl. 
 *            By default, all four corners are adorned. 
 *
 *   width:   The width specifies the width of the effect; in the case of rounded corners this 
 *            will be the radius of the width. 
 *            Specify this value using the px suffix such as 10px, and yes it must be pixels.
 *
 * For more details see: http://methvin.com/jquery/jq-corner.html
 * For a full demo see:  http://malsup.com/jquery/corner/
 *
 *
 * @example new Effect.Corner(element);
 * @desc Create round, 10px corners 
 *
 * @example new Effect.Corner(element, "25px");
 * @desc Create round, 25px corners 
 *
 * @name corner
 * @type scriptaculous
 * @author Ilia Lobsanov (first name @ last name dot com)
 */
Effect.Corner = Class.create();
Object.extend(Object.extend(Effect.Corner.prototype, Effect.Base.prototype), {
    hex2: function (s) {
        var s = parseInt(s).toString(16);
        return ( s.length < 2 ) ? '0'+s : s;
    },
    gpc: function (node) {
        for ( ; node && node.nodeName.toLowerCase() != 'html'; node = node.parentNode  ) {
            var v = Element.getStyle(node, 'backgroundColor');
            if ( v.indexOf('rgb') >= 0 ) { 
                rgb = v.match(/\d+/g); 
                return '#'+ this.hex2(rgb[0]) + this.hex2(rgb[1]) + this.hex2(rgb[2]);
            }
            if ( v && v != 'transparent' )
                return v;
        }
        return '#ffffff';
    },
    getW: function (i) {
        switch(this.fx) {
        case 'round':  return Math.round(this.width*(1-Math.cos(Math.asin(i/this.width))));
        case 'cool':   return Math.round(this.width*(1+Math.cos(Math.asin(i/this.width))));
        case 'sharp':  return Math.round(this.width*(1-Math.cos(Math.acos(i/this.width))));
        case 'bite':   return Math.round(this.width*(Math.cos(Math.asin((this.width-i-1)/this.width))));
        case 'slide':  return Math.round(this.width*(Math.atan2(i,this.width/i)));
        case 'jut':    return Math.round(this.width*(Math.atan2(this.width,(this.width-i-1))));
        case 'curl':   return Math.round(this.width*(Math.atan(i)));
        case 'tear':   return Math.round(this.width*(Math.cos(i)));
        case 'wicked': return Math.round(this.width*(Math.tan(i)));
        case 'long':   return Math.round(this.width*(Math.sqrt(i)));
        case 'sculpt': return Math.round(this.width*(Math.log((this.width-i-1),this.width)));
        case 'dog':    return (i&1) ? (i+1) : this.width;
        case 'dog2':   return (i&2) ? (i+1) : this.width;
        case 'dog3':   return (i&3) ? (i+1) : this.width;
        case 'fray':   return (i%2)*this.width;
        case 'notch':  return this.width; 
        case 'bevel':  return i+1;
        }
    },
    initialize: function(element, o) {
        element = $(element);
        o = (o||"").toLowerCase();
        var keep = /keep/.test(o);                       // keep borders?
        var cc = ((o.match(/cc:(#[0-9a-f]+)/)||[])[1]);  // corner color
        var sc = ((o.match(/sc:(#[0-9a-f]+)/)||[])[1]);  // strip color
        this.width = parseInt((o.match(/(\d+)px/)||[])[1]) || 10; // corner width
        var re = /round|bevel|notch|bite|cool|sharp|slide|jut|curl|tear|fray|wicked|sculpt|long|dog3|dog2|dog/;
        this.fx = ((o.match(re)||['round'])[0]);
        var edges = { T:0, B:1 };
        var opts = {
            TL:  /top|tl/.test(o),       TR:  /top|tr/.test(o),
            BL:  /bottom|bl/.test(o),    BR:  /bottom|br/.test(o)
        };
        if ( !opts.TL && !opts.TR && !opts.BL && !opts.BR )
            opts = { TL:1, TR:1, BL:1, BR:1 };
        var strip = document.createElement('div');
        strip.style.overflow = 'hidden';
        strip.style.height = '1px';
        strip.style.backgroundColor = sc || 'transparent';
        strip.style.borderStyle = 'solid';
        var pad = {
            T: parseInt(Element.getStyle(element,'paddingTop'))||0,     R: parseInt(Element.getStyle(element,'paddingRight'))||0,
            B: parseInt(Element.getStyle(element,'paddingBottom'))||0,  L: parseInt(Element.getStyle(element,'paddingLeft'))||0
        };

        if ( /MSIE/.test(navigator.userAgent) ) element.style.zoom = 1; // force 'hasLayout' in IE
        if (!keep) element.style.border = 'none';
        strip.style.borderColor = cc || this.gpc(element.parentNode);
        var cssHeight = Element.getHeight(element);

        for (var j in edges) {
            var bot = edges[j];
            strip.style.borderStyle = 'none '+(opts[j+'R']?'solid':'none')+' none '+(opts[j+'L']?'solid':'none');
            var d = document.createElement('div');
            var ds = d.style;

            bot ? element.appendChild(d) : element.insertBefore(d, element.firstChild);

            if (bot && cssHeight != 'auto') {
                if (Element.getStyle(element,'position') == 'static')
                    element.style.position = 'relative';
                ds.position = 'absolute';
                ds.bottom = ds.left = ds.padding = ds.margin = '0';
                if (/MSIE/.test(navigator.userAgent))
                    ds.setExpression('width', 'this.parentNode.offsetWidth');
                else
                    ds.width = '100%';
            }
            else {
                ds.margin = !bot ? '-'+pad.T+'px -'+pad.R+'px '+(pad.T-this.width)+'px -'+pad.L+'px' : 
                                    (pad.B-this.width)+'px -'+pad.R+'px -'+pad.B+'px -'+pad.L+'px';                
            }

            for (var i=0; i < this.width; i++) {
                var w = Math.max(0,this.getW(i));
                var e = strip.cloneNode(false);
                e.style.borderWidth = '0 '+(opts[j+'R']?w:0)+'px 0 '+(opts[j+'L']?w:0)+'px';
                bot ? d.appendChild(e) : d.insertBefore(e, d.firstChild);
            }
        }
    }
});