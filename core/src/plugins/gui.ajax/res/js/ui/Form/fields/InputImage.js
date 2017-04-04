import FormMixin from '../mixins/FormMixin'
import FileDropzone from './FileDropzone'
const React = require('react')
const PydioApi = require('pydio/http/api')

/**
 * UI for displaying and uploading an image,
 * using the binaryContext string.
 */
export default React.createClass({

    mixins:[FormMixin],

    propTypes: {
        attributes: React.PropTypes.object,
        binary_context: React.PropTypes.string
    },

    componentWillReceiveProps(newProps){
        let imgSrc;
        if(newProps.value && !this.state.reset){
            if((!this.state.value || this.state.value != newProps.value)){
                imgSrc = this.getBinaryUrl(newProps.value, (this.state.temporaryBinary && this.state.temporaryBinary==newProps.value));
            }
        }else if(newProps.attributes['defaultImage']){
            if(this.state.value){
                //this.setState({ value:'ajxp-remove-original' });
            }
            imgSrc = newProps.attributes['defaultImage'];
        }
        if(imgSrc){
            this.setState({imageSrc:imgSrc, reset:false});
        }
    },

    getInitialState(){
        let imgSrc, originalBinary;
        if(this.props.value){
            imgSrc = this.getBinaryUrl(this.props.value);
            originalBinary = this.props.value;
        }else if(this.props.attributes['defaultImage']){
            imgSrc = this.props.attributes['defaultImage'];
        }
        return {imageSrc:imgSrc, originalBinary:originalBinary};
    },

    getBinaryUrl: function(binaryId, isTemporary=false){
        let url = global.pydio.Parameters.get('ajxpServerAccess') + "&get_action=" +this.props.attributes['loadAction'];
        if(!isTemporary) {
            url += "&binary_id=" + binaryId;
        } else {
            url += "&tmp_file=" + binaryId;
        }
        if(this.props.binary_context){
            url += "&" + this.props.binary_context;
        }
        return url;
    },

    getUploadUrl: function(paramsOnly){
        let params = "get_action=" +this.props.attributes['uploadAction'];
        if(this.props.binary_context){
            params += "&" + this.props.binary_context;
        }
        if(paramsOnly){
            return params;
        }else{
            return global.pydio.Parameters.get('ajxpServerAccess') + "&" + params;
        }
    },

    uploadComplete:function(newBinaryName){
        const prevValue = this.state.value;
        this.setState({
            temporaryBinary:newBinaryName,
            value:null
        });
        if(this.props.onChange){
            let additionalFormData = {type:'binary'};
            if(this.state.originalBinary){
                additionalFormData['original_binary'] = this.state.originalBinary;
            }
            this.props.onChange(newBinaryName, prevValue, additionalFormData);
        }
    },

    htmlUpload: function(){
        global.formManagerHiddenIFrameSubmission = function(result){
            result = result.trim();
            this.uploadComplete(result);
            global.formManagerHiddenIFrameSubmission = null;
        }.bind(this);
        this.refs.uploadForm.submit();
    },

    onDrop: function(files, event, dropzone){
        if(PydioApi.supportsUpload()){
            this.setState({loading:true});
            PydioApi.getClient().uploadFile(files[0], "userfile", this.getUploadUrl(true),
                function(transport){
                    // complete
                    let result = transport.responseText.trim().replace(/<\w+(\s+("[^"]*"|'[^']*'|[^>])+)?>|<\/\w+>/gi, '');
                    result = result.replace('parent.formManagerHiddenIFrameSubmission("', '').replace('");', '');
                    this.uploadComplete(result);
                    this.setState({loading:false});
                }.bind(this), function(transport){
                    // error
                    this.setState({loading:false});
                }.bind(this), function(computableEvent){
                    // progress
                    // console.log(computableEvent);
                })
        }else{
            this.htmlUpload();
        }
    },

    clearImage:function(){
        if(global.confirm('Do you want to remove the current image?')){
            const prevValue = this.state.value;
            this.setState({
                value:'ajxp-remove-original',
                reset:true
            }, function(){
                this.props.onChange('ajxp-remove-original', prevValue, {type:'binary'});
            }.bind(this));

        }
    },

    render: function(){
        const coverImageStyle = {
            backgroundImage:"url("+this.state.imageSrc+")",
            backgroundPosition:"50% 50%",
            backgroundSize:"cover"
        };
        let icons = [];
        if(this.state && this.state.loading){
            icons.push(<span key="spinner" className="icon-spinner rotating" style={{opacity:'0'}}></span>);
        }else{
            icons.push(<span key="camera" className="icon-camera" style={{opacity:'0'}}></span>);
        }
        return(
            <div>
                <div className="image-label">{this.props.attributes.label}</div>
                <form ref="uploadForm" encType="multipart/form-data" target="uploader_hidden_iframe" method="post" action={this.getUploadUrl()}>
                    <FileDropzone onDrop={this.onDrop} accept="image/*" style={coverImageStyle}>
                        {icons}
                    </FileDropzone>
                </form>
                <div className="binary-remove-button" onClick={this.clearImage}><span key="remove" className="mdi mdi-close"></span> RESET</div>
                <iframe style={{display:"none"}} id="uploader_hidden_iframe" name="uploader_hidden_iframe"></iframe>
            </div>
        );
    }

});