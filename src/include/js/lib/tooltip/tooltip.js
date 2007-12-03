/*
 +-------------------------------------------------------------------+
 |                   J S - T O O L T I P   (v2.1)                    |
 |                                                                   |
 | Copyright Gerd Tentler                www.gerd-tentler.de/tools   |
 | Created: Feb. 15, 2005                Last modified: Apr. 9, 2007 |
 +-------------------------------------------------------------------+
 | This program may be used and hosted free of charge by anyone for  |
 | personal purpose as long as this copyright notice remains intact. |
 |                                                                   |
 | Obtain permission before selling the code for this program or     |
 | hosting this software on a commercial website or redistributing   |
 | this software over the Internet or in any other medium. In all    |
 | cases copyright must remain intact.                               |
 +-------------------------------------------------------------------+

======================================================================================================

 This script was tested with the following systems and browsers:

 - Windows XP: IE 6, NN 7, Opera 7 + 9, Firefox 2
 - Mac OS X:   IE 5, Safari 1

 If you use another browser or system, this script may not work for you - sorry.

------------------------------------------------------------------------------------------------------

 USAGE:

 Use the toolTip-function with mouse-over and mouse-out events (see example below).

 - To show a tooltip, use this syntax: toolTip(text, width in pixels, opacity in percent)
   Note: width and opacity are optional. Opacity is not supported by all browsers.

 - To hide a tooltip, use this syntax: toolTip()

------------------------------------------------------------------------------------------------------

 EXAMPLE:

 <a href="#" onMouseOver="toolTip('Just a test', 150)" onMouseOut="toolTip()">some text here</a>

======================================================================================================
*/

var OP = (navigator.userAgent.indexOf('Opera') != -1);
var IE = (navigator.userAgent.indexOf('MSIE') != -1 && !OP);
var GK = (navigator.userAgent.indexOf('Gecko') != -1);
var SA = (navigator.userAgent.indexOf('Safari') != -1);
var DOM = document.getElementById;

var tooltip = null;

function TOOLTIP() {
//----------------------------------------------------------------------------------------------------
// Configuration
//----------------------------------------------------------------------------------------------------
  this.width = 200;                     // width (pixels)
  this.bgColor = "#FFFFC0";             // background color
  this.textFont = "Comic Sans MS";      // text font family
  this.textSize = 13;                   // text font size (pixels)
  this.textColor = "#A00000";           // text color
  this.border = "1px dashed #DDDDDD";   // border (CSS spec: size style color, e.g. "1px solid #D00000")
  this.opacity = 80;                    // opacity (0 - 100); not supported by all browsers
  this.cursorDistance = 5;              // distance from mouse cursor (pixels)

  // don't change
  this.text = '';
  this.height = 0;
  this.obj = null;
  this.active = false;

//----------------------------------------------------------------------------------------------------
// Methods
//----------------------------------------------------------------------------------------------------
  this.create = function() {
    if(!this.obj) this.init();

    var s = (this.textFont ? 'font-family:' + this.textFont + '; ' : '') +
            (this.textSize ? 'font-size:' + this.textSize + 'px; ' : '') +
            (this.border ? 'border:' + this.border + '; ' : '') +
            (this.textColor ? 'color:' + this.textColor + '; ' : '');

    var t = '<table border=0 cellspacing=0 cellpadding=4 width=' + this.width + '><tr>' +
            '<td align=center' + (s ? ' style="' + s + '"' : '') + '>' + this.text +
            '</td></tr></table>';

    if(DOM || IE) this.obj.innerHTML = t;
    if(DOM) this.height = this.obj.offsetHeight;
    else if(IE) this.height = this.obj.style.pixelHeight;
    if(this.bgColor) this.obj.style.backgroundColor = this.bgColor;

    this.setOpacity();
    this.move();
    this.show();
  }

  this.init = function() {
    if(DOM) this.obj = document.getElementById('ToolTip');
    else if(IE) this.obj = document.all.ToolTip;
  }

  this.move = function() {
    var winX = getWinX() - (((GK && !SA) || OP) ? 17 : 0);
    var winY = getWinY() - (((GK && !SA) || OP) ? 17 : 0);
    var x = mouseX;
    var y = mouseY;

    if(x + this.width + this.cursorDistance > winX + getScrX())
      x -= this.width + this.cursorDistance;
    else x += this.cursorDistance;

    if(y + this.height + this.cursorDistance > winY + getScrY())
      y -= this.height;
    else y += this.cursorDistance;

    this.obj.style.left = x + 'px';
    this.obj.style.top = y + 'px';
  }

  this.show = function() {
    this.obj.style.zIndex = 69;
    this.active = true;
    this.obj.style.visibility = 'visible';
  }

  this.hide = function() {
    this.obj.style.zIndex = -1;
    this.active = false;
    this.obj.style.visibility = 'hidden';
  }

  this.setOpacity = function() {
    this.obj.style.opacity = this.opacity / 100;
    this.obj.style.MozOpacity = this.opacity / 100;
    this.obj.style.KhtmlOpacity = this.opacity / 100;
    this.obj.style.filter = 'alpha(opacity=' + this.opacity + ')';
  }
}

//----------------------------------------------------------------------------------------------------
// Global functions
//----------------------------------------------------------------------------------------------------
function getScrX() {
  var offset = 0;
  if(window.pageXOffset)
    offset = window.pageXOffset;
  else if(document.documentElement && document.documentElement.scrollLeft)
    offset = document.documentElement.scrollLeft;
  else if(document.body && document.body.scrollLeft)
    offset = document.body.scrollLeft;
  return offset;
}

function getScrY() {
  var offset = 0;
  if(window.pageYOffset)
    offset = window.pageYOffset;
  else if(document.documentElement && document.documentElement.scrollTop)
    offset = document.documentElement.scrollTop;
  else if(document.body && document.body.scrollTop)
    offset = document.body.scrollTop;
  return offset;
}

function getWinX() {
  var size = 0;
  if(window.innerWidth)
    size = window.innerWidth;
  else if(document.documentElement && document.documentElement.clientWidth)
    size = document.documentElement.clientWidth;
  else if(document.body && document.body.clientWidth)
    size = document.body.clientWidth;
  else size = screen.width;
  return size;
}

function getWinY() {
  var size = 0;
  if(window.innerHeight)
    size = window.innerHeight;
  else if(document.documentElement && document.documentElement.clientHeight)
    size = document.documentElement.clientHeight;
  else if(document.body && document.body.clientHeight)
    size = document.body.clientHeight;
  else size = screen.height;
  return size;
}

function getMouseXY(e) {
  if(e && e.pageX != null) {
    mouseX = e.pageX;
    mouseY = e.pageY;
  }
  else if(event && event.clientX != null) {
    mouseX = event.clientX + getScrX();
    mouseY = event.clientY + getScrY();
  }
  if(mouseX < 0) mouseX = 0;
  if(mouseY < 0) mouseY = 0;
  if(tooltip && tooltip.active) tooltip.move();
}

function toolTip(text, width, opacity) {
  if(text) {
    tooltip = new TOOLTIP();
    tooltip.text = text;
    if(width) tooltip.width = width;
    if(opacity) tooltip.opacity = opacity;
    tooltip.create();
  }
  else if(tooltip) tooltip.hide();
}

//----------------------------------------------------------------------------------------------------
// Build tooltip box
//----------------------------------------------------------------------------------------------------
document.write('<div id="ToolTip" style="position:absolute; visibility:hidden"></div>');

//----------------------------------------------------------------------------------------------------
// Event handlers
//----------------------------------------------------------------------------------------------------
var mouseX = mouseY = 0;
document.onmousemove = getMouseXY;
//----------------------------------------------------------------------------------------------------

function toolTipImage(imageName, object)
{
	toolTip('<img src="'+imageName+'" width="150">', 150);
}
