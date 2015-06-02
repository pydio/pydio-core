"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) subClass.__proto__ = superClass; }

var EmptyNodeProvider = (function (_Observable) {
    function EmptyNodeProvider() {
        _classCallCheck(this, EmptyNodeProvider);

        _Observable.call(this);
    }

    _inherits(EmptyNodeProvider, _Observable);

    EmptyNodeProvider.prototype.initProvider = function initProvider(properties) {
        this.properties = properties;
    };

    /**
     *
     * @param node AjxpNode
     * @param nodeCallback Function
     * @param childCallback Function
     */

    EmptyNodeProvider.prototype.loadNode = function loadNode(node, nodeCallback, childCallback) {};

    EmptyNodeProvider.prototype.loadLeafNodeSyncfunction = function loadLeafNodeSyncfunction(node, callback) {};

    return EmptyNodeProvider;
})(Observable);