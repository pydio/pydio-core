import React from 'react'
import {FlatButton} from 'material-ui'

export default React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes: {
        currentMetas: React.PropTypes.object,
        edit:React.PropTypes.string,
        metaSourceProvider:React.PropTypes.object,
        closeCurrent: React.PropTypes.func,
        setEditState: React.PropTypes.func,
        featuresEditable:React.PropTypes.bool
    },

    render: function(){
        var features = [];
        var metas = Object.keys(this.props.currentMetas);
        metas.sort(function(k1, k2){
            var type1 = k1.split('.').shift();
            var type2 = k2.split('.').shift();
            if(type1 == 'metastore' || type2 == 'index') return -1;
            if(type1 == 'index' || type2 == 'metastore') return 1;
            return (k1 > k2 ? 1:-1);
        });
        if(metas){
            features = metas.map(function(k){
                var removeButton, description;
                if(this.props.edit == k && this.props.featuresEditable){
                    var remove = function(event){
                        event.stopPropagation();
                        this.props.metaSourceProvider.removeMetaSource(k);
                    }.bind(this);
                    removeButton = (
                        <div style={{textAlign:'right'}}>
                            <FlatButton label={this.context.getMessage('ws.31')} primary={true} onTouchTap={remove}/>
                        </div>
                    );
                }
                description = <div className="legend">{this.props.metaSourceProvider.getMetaSourceDescription(k)}</div>;
                return (
                    <PydioComponents.PaperEditorNavEntry key={k} keyName={k} selectedKey={this.props.edit} onClick={this.props.setEditState}>
                        {this.props.metaSourceProvider.getMetaSourceLabel(k)}
                        {description}
                        {removeButton}
                    </PydioComponents.PaperEditorNavEntry>
                );
            }.bind(this));
        }
        if(this.props.featuresEditable){
            features.push(
                <div className="menu-entry" key="add-feature" onClick={this.props.metaSourceProvider.showMetaSourceForm.bind(this.props.metaSourceProvider)}>+ {this.context.getMessage('ws.32')}</div>
            );
        }

        return (
            <div>{features}</div>
        );
    }

});