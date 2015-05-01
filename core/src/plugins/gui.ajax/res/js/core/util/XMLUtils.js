"use strict";

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } };

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var XMLUtils = (function () {
    function XMLUtils() {
        _classCallCheck(this, XMLUtils);
    }

    _createClass(XMLUtils, null, [{
        key: "loadXPathReplacer",
        value: function loadXPathReplacer() {
            if (document.createExpression) {
                return;
            }PydioApi.loadLibrary("plugins/gui.ajax/res/js/vendor/xpath-polyfill/javascript-xpath-cmp.js", null, false);
        }
    }, {
        key: "XPathSelectSingleNode",

        /**
         * Selects the first XmlNode that matches the XPath expression.
         *
         * @param element {Element | Document} root element for the search
         * @param query {String} XPath query
         * @return {Element} first matching element
         * @signature function(element, query)
         */
        value: function XPathSelectSingleNode(element, query) {
            if (element.selectSingleNode) {
                return element.selectSingleNode(query);
            }

            if (!XMLUtils.__xpe) {
                try {
                    XMLUtils.__xpe = new XPathEvaluator();
                } catch (e) {}
            }

            if (!XMLUtils.__xpe) {
                if (!document.createExpression) XMLUtils.loadXPathReplacer();
                query = document.createExpression(query, null);
                var result = query.evaluate(element, 7, null);
                return result.snapshotLength ? result.snapshotItem(0) : null;
            }

            var xpe = XMLUtils.__xpe;

            try {
                return xpe.evaluate(query, element, xpe.createNSResolver(element), XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
            } catch (err) {
                throw new Error("selectSingleNode: query: " + query + ", element: " + element + ", error: " + err);
            }
        }
    }, {
        key: "XPathSelectNodes",

        /**
         * Selects a list of nodes matching the XPath expression.
         *
         * @param element {Element | Document} root element for the search
         * @param query {String} XPath query
         * @return {Element[]} List of matching elements
         * @signature function(element, query)
         */
        value: function XPathSelectNodes(element, query) {
            if (element.selectNodes) {
                try {
                    if (element.ownerDocument) {
                        element.ownerDocument.setProperty("SelectionLanguage", "XPath");
                    } else {
                        element.setProperty("SelectionLanguage", "XPath");
                    }
                } catch (e) {
                    if (console) console.log(e);
                }
                return element.selectNodes(query);
            }

            var xpe = XMLUtils.__xpe;

            if (!xpe) {
                try {
                    XMLUtils.__xpe = xpe = new XPathEvaluator();
                } catch (e) {}
            }
            var result,
                nodes = [],
                i;
            if (!XMLUtils.__xpe) {
                if (!document.createExpression) XMLUtils.loadXPathReplacer();
                query = document.createExpression(query, null);
                result = query.evaluate(element, 7, null);
                nodes = [];
                for (i = 0; i < result.snapshotLength; i++) {
                    if (Element.extend) {
                        nodes[i] = Element.extend(result.snapshotItem(i));
                    } else {
                        nodes[i] = result.snapshotItem(i);
                    }
                }
                return nodes;
            }

            try {
                result = xpe.evaluate(query, element, xpe.createNSResolver(element), XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
            } catch (err) {
                throw new Error("selectNodes: query: " + query + ", element: " + element + ", error: " + err);
            }

            for (i = 0; i < result.snapshotLength; i++) {
                nodes[i] = result.snapshotItem(i);
            }

            return nodes;
        }
    }, {
        key: "XPathGetSingleNodeText",

        /**
         * Selects the first XmlNode that matches the XPath expression and returns the text content of the element
         *
         * @param element {Element|Document} root element for the search
         * @param query {String}  XPath query
         * @return {String} the joined text content of the found element or null if not appropriate.
         * @signature function(element, query)
         */
        value: function XPathGetSingleNodeText(element, query) {
            var node = XPathSelectSingleNode(element, query);
            return XMLUtils.getDomNodeText(node);
        }
    }, {
        key: "getDomNodeText",
        value: function getDomNodeText(node) {
            var includeCData = arguments[1] === undefined ? false : arguments[1];

            if (!node || !node.nodeType) {
                return null;
            }

            switch (node.nodeType) {
                case 1:
                    // NODE_ELEMENT
                    var i,
                        a = [],
                        nodes = node.childNodes,
                        length = nodes.length;
                    for (i = 0; i < length; i++) {
                        a[i] = XMLUtils.getDomNodeText(nodes[i], includeCData);
                    }

                    return a.join("");

                case 2:
                    // NODE_ATTRIBUTE
                    return node.value;
                    break;

                case 3:
                    // NODE_TEXT
                    return node.nodeValue;
                    break;

                case 4:
                    // CDATA
                    if (includeCData) {
                        return node.nodeValue;
                    }}

            return null;
        }
    }, {
        key: "parseXml",

        /**
         * @param xmlStr
         * @returns {*}
         */
        value: function parseXml(xmlStr) {

            if (typeof window.ActiveXObject != "undefined" && new window.ActiveXObject("MSXML2.DOMDocument.6.0")) {
                var xmlDoc = new window.ActiveXObject("MSXML2.DOMDocument.6.0");
                xmlDoc.validateOnParse = false;
                xmlDoc.async = false;
                xmlDoc.loadXML(xmlStr);
                xmlDoc.setProperty("SelectionLanguage", "XPath");
                return xmlDoc;
            } else if (typeof window.DOMParser != "undefined") {
                return new window.DOMParser().parseFromString(xmlStr, "text/xml");
            }
        }
    }]);

    return XMLUtils;
})();