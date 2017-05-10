const React = require('react');
/**
 * React Mixin for the form helper : default properties that
 * helpers can receive
 */
export default {
    propTypes:{
        paramName:React.PropTypes.string,
        paramAttributes:React.PropTypes.object,
        values:React.PropTypes.object,
        updateCallback:React.PropTypes.func
    }
}