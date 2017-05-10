import MetaList from '../meta/MetaList'
import Workspace from '../model/Workspace'

export default React.createClass({

    mixins:[AdminComponents.MessagesConsumerMixin],

    propTypes:{
        onSelectionChange:React.PropTypes.func.isRequired,
        metaSourceProvider:React.PropTypes.object.isRequired,
        driverLabel:React.PropTypes.string,
        driverDescription:React.PropTypes.string,
        currentSelection:React.PropTypes.string,
        model:React.PropTypes.instanceOf(Workspace),
        tplFieldsComponent:React.PropTypes.object
    },

    setEditState:function(key){
        this.props.onSelectionChange(key);
    },

    closeCurrent:function(event){
        event.stopPropagation();
        this.setEditState('activity');
    },

    render: function(){

        var firstSections = [];
        if(!this.props.model.isTemplate()){
            firstSections.push(<PydioComponents.PaperEditorNavEntry keyName='shares' key='shares' selectedKey={this.props.currentSelection} label={this.context.getMessage('ws.25')} onClick={this.setEditState}/>);
        }
        var driverTabLabel = this.context.getMessage('ws.9') + ": " + this.props.driverLabel;
        var additionalFeatures;
        if(this.props.model.isTemplateChild()){
            driverTabLabel = this.context.getMessage('ws.13');
        }else{
            var plusButton;
            if(this.props.model.isEditable()){
                plusButton = <span className="metasource-add" onClick={this.props.metaSourceProvider.showMetaSourceForm.bind(this.props.metaSourceProvider)} >+</span>;
            }
            additionalFeatures = (
                <div key="additional-k">
                    <PydioComponents.PaperEditorNavHeader label={this.context.getMessage('ws.27')}>
                        {plusButton}
                    </PydioComponents.PaperEditorNavHeader>
                    <MetaList
                        metaSourceProvider={this.props.metaSourceProvider}
                        currentMetas={this.props.model.getOption('META_SOURCES')}
                        edit={this.props.currentSelection}
                        closeCurrent={this.closeCurrent}
                        setEditState={this.setEditState}
                        featuresEditable={this.props.model.isEditable()}
                    />
                </div>
            );
        }

        return (
            <div>
                <PydioComponents.PaperEditorNavHeader key="parameters-k" label={this.context.getMessage('ws.29')}/>
                <PydioComponents.PaperEditorNavEntry keyName='general' key='general' selectedKey={this.props.currentSelection} label={this.context.getMessage('ws.30')} onClick={this.setEditState}/>
                <PydioComponents.PaperEditorNavEntry keyName='driver' key='driver' selectedKey={this.props.currentSelection} onClick={this.setEditState}>{driverTabLabel}</PydioComponents.PaperEditorNavEntry>
                {firstSections}
                {this.props.tplFieldsComponent}
                {additionalFeatures}
            </div>
        );
    }

});
