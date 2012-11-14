/**
* Jath is free software provided under the MIT license.
*	See LICENSE file for full text of the license.
*	Copyright 2010 Dan Newcome.
*/
(function() {

Jath = {};
Jath.parse = parse;
Jath.resolver = null;
// values prefixed with literal charactar marker will not be
// treated as xpath expressions and will be output directly
Jath.literalChar = ":";

/**
* Rudimentary check for IE
* Also added support for WSH, uses the same API as IE
*/
var m_browser;
if( typeof WScript != "undefined" ) {
	m_browser = 'msie';
}
// TODO: is there a better way to detect node.js?
else if( typeof process != "undefined" ) {
	// running under node.js
	m_browser = 'node';
	var xmljs = require( 'libxmljs' );
	exports.parse = parse;
}
else if( navigator.userAgent.toLowerCase().indexOf( 'msie' ) > -1 ) {
	m_browser = 'msie';
}
else {
	m_browser = 'standards';
}

/**
* parse: 
*	process xml doc according to the given json template
*	@template - output spec as a json template
*	@xmldoc - input xml document
*	@node - the starting node to use in the document. xpath
*		expressions will be evaluated relative to this node.
*		If not given, root will be used.
*/
function parse( template, xmldoc, node ) {
	if( node === undefined ) {
		node = xmldoc;
	}
	if( typeOf( template ) === 'array' ) {
		return parseArray( template, xmldoc, node );
	}
	else if( typeOf( template ) === 'object' ) {
		return parseObject( template, xmldoc, node );
	}
	else {
		return parseItem( template, xmldoc, node );
	}
}

function parseArray( template, xmldoc, node ) {
	var retVal = [];
	
	if( template[0] != null ) {
		if( m_browser == 'msie' ) {
			xmldoc.setProperty("SelectionLanguage", "XPath");
			var nodeList = node.selectNodes( template[0] );
			var thisNode;
			while( thisNode = nodeList.nextNode() ) {
				retVal.push( parse( template[1], xmldoc, thisNode ) );
			}
		}
		else if( m_browser == 'node' ) {
			var nodeList = node.find( template[0] );
			for( var i=0; i < nodeList.length; i++ ) {
				retVal.push( parse( template[1], xmldoc, nodeList[i] ) );
			}
		}
		else {
			var xpathResult = xmldoc.evaluate( template[0], node, Jath.resolver, XPathResult.ANY_TYPE, null );
			var thisNode;
			while( thisNode = xpathResult.iterateNext() ) {
				retVal.push( parse( template[1], xmldoc, thisNode ) );
			}
		}
	}
	// we can have an array output without iterating over the source
	// data - in this case, current node is static 
	else {
		for( var i=1; i < template.length; i++ ) {
			retVal.push( parse( template[i], xmldoc, node ) );
		}
	}
	
	return retVal;
}

function parseObject( template, xmldoc, node ) {
	var item;
	var newitem = {};
	for( item in template ) {
		newitem[item] = parse( template[item], xmldoc, node );
	}
	return newitem;
}

function parseItem( template, xmldoc, node ) {
	if( m_browser == 'msie' ) {
		xmldoc.setProperty("SelectionLanguage", "XPath");
		if( typeOf( template ) == 'string' && template.substring( 0, 1 ) != Jath.literalChar ) {
			return node.selectSingleNode( template ).text;
		}
		else {
			return template.substring( 1 );
		}
	}
	else if( m_browser == 'node' ) {
		require('util').puts( template );	
			// node can be null if query fails
			var itemNode = node.get( template );
			if( itemNode ) {
				return itemNode.text();
			}
			else {
				return null;
			}
	}
	else {
		if( typeOf( template ) == 'string' && template[0] != Jath.literalChar ) {
			return xmldoc.evaluate( template, node, Jath.resolver, XPathResult.STRING_TYPE, null ).stringValue;
		}
		else {
			return template.substring( 1 );
		}
	}
}

/**
* typeOf function published by Douglas Crockford in ECMAScript recommendations
* http://www.crockford.com/javascript/recommend.html
*/
function typeOf(value) {
	var s = typeof value;
	if (s === 'object') {
		if (value) {
			if (typeof value.length === 'number' &&
					!(value.propertyIsEnumerable('length')) &&
					typeof value.splice === 'function') {
				s = 'array';
			}
		} else {
			s = 'null';
		}
	}
	return s;
}

})();
