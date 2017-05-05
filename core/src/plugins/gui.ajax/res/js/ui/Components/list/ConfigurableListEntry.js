import React from 'react'
import ListEntryNodeListenerMixin from './ListEntryNodeListenerMixin'
import InlineEditor from './InlineEditor'
import {DragDropListEntry} from './ListEntry'
import {FontIcon} from 'material-ui'

/**
 * Callback based material list entry with custom icon render, firstLine, secondLine, etc.
 */
export default React.createClass({

    mixins:[ListEntryNodeListenerMixin],

    propTypes: {
        node:React.PropTypes.instanceOf(AjxpNode),
        // SEE ALSO ListEntry PROPS
        renderIcon: React.PropTypes.func,
        renderFirstLine:React.PropTypes.func,
        renderSecondLine:React.PropTypes.func,
        renderThirdLine:React.PropTypes.func,
        renderActions:React.PropTypes.func,
        style: React.PropTypes.object
    },

    render: function(){
        let icon, firstLine, secondLine, thirdLine, style = this.props.style || {};
        if(this.props.renderIcon) {
            icon = this.props.renderIcon(this.props.node, this.props);
        } else {
            var node = this.props.node;
            var iconClass = node.getMetadata().get("icon_class")? node.getMetadata().get("icon_class") : (node.isLeaf()?"icon-file-alt":"icon-folder-close");
            icon = <FontIcon className={iconClass}/>;
        }

        if(this.props.renderFirstLine) {
            firstLine = this.props.renderFirstLine(this.props.node);
        } else {
            firstLine = this.props.node.getLabel();
        }
        if(this.state && this.state.inlineEdition){
            firstLine = (
                <span>
                        <InlineEditor
                            node={this.props.node}
                            onClose={()=>{this.setState({inlineEdition:false})}}
                            callback={this.state.inlineEditionCallback}
                        />
                    {firstLine}
                    </span>
            );
            style.position = 'relative';
        }
        if(this.props.renderSecondLine) {
            secondLine = this.props.renderSecondLine(this.props.node);
        }
        if(this.props.renderThirdLine) {
            thirdLine = this.props.renderThirdLine(this.props.node);
        }
        var actions = this.props.actions;
        if(this.props.renderActions) {
            actions = this.props.renderActions(this.props.node);
        }

        return (
            <DragDropListEntry
                {...this.props}
                iconCell={icon}
                firstLine={firstLine}
                secondLine={secondLine}
                thirdLine={thirdLine}
                actions={actions}
                style={style}
            />
        );

    }

});

