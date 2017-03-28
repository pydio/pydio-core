(function(global){

    /**
     * React Mixin for Form Element
     */
    var FormMixin = {

        propTypes:{
            attributes:React.PropTypes.object.isRequired,
            name:React.PropTypes.string.isRequired,

            displayContext:React.PropTypes.oneOf(['form', 'grid']),
            disabled:React.PropTypes.bool,
            multiple:React.PropTypes.bool,
            value:React.PropTypes.any,
            onChange:React.PropTypes.func,
            onChangeEditMode:React.PropTypes.func,
            binary_context:React.PropTypes.string,
            errorText:React.PropTypes.string
        },

        getDefaultProps:function(){
            return {
                displayContext:'form',
                disabled:false
            };
        },

        isDisplayGrid:function(){
            return this.props.displayContext == 'grid';
        },

        isDisplayForm:function(){
            return this.props.displayContext == 'form';
        },

        toggleEditMode:function(){
            if(this.isDisplayForm()) return;
            var newState = !this.state.editMode;
            this.setState({editMode:newState});
            if(this.props.onChangeEditMode){
                this.props.onChangeEditMode(newState);
            }
        },

        enterToToggle:function(event){
            if(event.key == 'Enter'){
                this.toggleEditMode();
            }
        },

        bufferChanges:function(newValue, oldValue){
            this.triggerPropsOnChange(newValue, oldValue);
        },

        onChange:function(event, value){
            if(value === undefined) {
                value = event.currentTarget.getValue ? event.currentTarget.getValue() : event.currentTarget.value;
            }
            if(this.changeTimeout){
                global.clearTimeout(this.changeTimeout);
            }
            var newValue = value, oldValue = this.state.value;
            if(this.props.skipBufferChanges){
                this.triggerPropsOnChange(newValue, oldValue);
            }
            this.setState({
                dirty:true,
                value:newValue
            });
            if(!this.props.skipBufferChanges) {
                let timerLength = 250;
                if(this.props.attributes['type'] === 'password'){
                    timerLength = 1200;
                }
                this.changeTimeout = global.setTimeout(function () {
                    this.bufferChanges(newValue, oldValue);
                }.bind(this), timerLength);
            }
        },

        triggerPropsOnChange:function(newValue, oldValue){
            if(this.props.attributes['type'] === 'password'){
                this.toggleEditMode();
                this.props.onChange(newValue, oldValue, {type:this.props.attributes['type']});
            }else{
                this.props.onChange(newValue, oldValue);
            }
        },

        componentWillReceiveProps:function(newProps){
            var choices;
            if(newProps.attributes['choices']) {
                if(newProps.attributes['choices'] != this.props.attributes['choices']){
                    choices = this.loadExternalValues(newProps.attributes['choices']);
                }else{
                    choices = this.state.choices;
                }
            }
            this.setState({
                value:newProps.value,
                dirty:false,
                choices:choices
            });
        },

        getInitialState:function(){
            var choices;
            if(this.props.attributes['choices']) {
                choices = this.loadExternalValues(this.props.attributes['choices']);
            }
            return {
                editMode:false,
                dirty:false,
                value:this.props.value,
                choices:choices
            };
        },

        loadExternalValues:function(choices){
            var list_action;
            if(choices instanceof Map){
                return choices;
            }
            var output = new Map();
            if(choices.indexOf('json_list:') === 0){
                list_action = choices.replace('json_list:', '');
                output.set('0', pydio.MessageHash['ajxp_admin.home.6']);
                PydioApi.getClient().request({get_action:list_action}, function(transport){
                    var list = transport.responseJSON.LIST;
                    var newOutput = new Map();
                    if(transport.responseJSON.HAS_GROUPS){
                        for(key in list){
                            if(list.hasOwnProperty(key)){
                                // TODO: HANDLE OPTIONS GROUPS
                                for (var index=0;index<list[key].length;index++){
                                    newOutput.set(key+'-'+index, list[key][index].action);
                                }
                            }
                        }
                    }else{
                        for (var key in list){
                            if(list.hasOwnProperty(key)){
                                newOutput.set(key, list[key]);
                            }
                        }
                    }
                    this.setState({choices:newOutput});
                }.bind(this));
            }else if(choices.indexOf('json_file:') === 0){
                list_action = choices.replace('json_file:', '');
                output.set('0', pydio.MessageHash['ajxp_admin.home.6']);
                PydioApi.getClient().loadFile(list_action, function(transport){
                    var newOutput = new Map();
                    transport.responseJSON.map(function(entry){
                        newOutput.set(entry.key, entry.label);
                    });
                    this.setState({choices:newOutput});
                }.bind(this));
            }else if(choices == "AJXP_AVAILABLE_LANGUAGES"){
                global.pydio.listLanguagesWithCallback(function(key, label){
                    output.set(key, label);
                });
            }else if(choices == "AJXP_AVAILABLE_REPOSITORIES"){
                if(global.pydio.user){
                    global.pydio.user.repositories.forEach(function(repository){
                        output.set(repository.getId() , repository.getLabel());
                    });
                }
            }else{
                // Parse string and return map
                choices.split(",").map(function(choice){
                    var label,value;
                    var l = choice.split('|');
                    if(l.length > 1){
                        value = l[0];label=l[1];
                    }else{
                        value = label = choice;
                    }
                    if(global.pydio.MessageHash[label]) label = global.pydio.MessageHash[label];
                    output.set(value, label);
                });
            }
            return output;
        }

    };

    /**
     * React Mixin for the form helper : default properties that
     * helpers can receive
     */
    var HelperMixin = {
        propTypes:{
            paramName:React.PropTypes.string,
            paramAttributes:React.PropTypes.object,
            values:React.PropTypes.object,
            updateCallback:React.PropTypes.func
        }
    };

    /**
     * Text input, can be single line, multiLine, or password, depending on the
     * attributes.type key.
     */
    var InputText = React.createClass({

        mixins:[FormMixin],

        render:function(){
            if(this.isDisplayGrid() && !this.state.editMode){
                let value = this.state.value;
                if(this.props.attributes['type'] === 'password' && value){
                    value = '***********';
                }else{
                    value = this.state.value;
                }
                return <div onClick={this.props.disabled?function(){}:this.toggleEditMode} className={value?'':'paramValue-empty'}>{!value?'Empty':value}</div>;
            }else{
                let field = (
                    <ReactMUI.TextField
                        floatingLabelText={this.isDisplayForm()?this.props.attributes.label:null}
                        value={this.state.value}
                        onChange={this.onChange}
                        onKeyDown={this.enterToToggle}
                        type={this.props.attributes['type'] == 'password'?'password':null}
                        multiLine={this.props.attributes['type'] == 'textarea'}
                        disabled={this.props.disabled}
                        errorText={this.props.errorText}
                        autoComplete="off"
                    />
                );
                if(this.props.attributes['type'] === 'password'){
                    return (
                        <form autoComplete="off" style={{display:'inline'}}>{field}</form>
                    );
                }else{
                    return(
                        <span>{field}</span>
                    );
                }
            }
        }

    });

    var ValidPassword = React.createClass({

        mixins:[FormMixin],

        isValid:function(){
            return (this.state.value && this.checkMinLength(this.state.value) &&
                this.state.confirmValue && this.state.confirmValue === this.state.value);
        },

        checkMinLength:function(value){
            var minLength = parseInt(global.pydio.getPluginConfigs("core.auth").get("PASSWORD_MINLENGTH"));
            return !(value && value.length < minLength);
        },

        getMessage:function(messageId){
            if(this.context && this.context.getMessage){
                return this.context.getMessage(messageId, '');
            }else if(global.pydio && global.pydio.MessageHash){
                return global.pydio.MessageHash[messageId];
            }
        },

        getComplexityString:function(value){
            var response;
            PassUtils.checkPasswordStrength(value, function(segment, percent){
                var responseString;
                if(global.pydio && global.pydio.MessageHash){
                    responseString = this.getMessage(PassUtils.Options.pydioMessages[segment]);
                }else{
                    responseString = PassUtils.Options.messages[segment];
                }
                response = {
                    segment:segment,
                    color:(segment>1) ? PassUtils.Options.colors[segment] : null,
                    responseString:responseString
                };
            }.bind(this));
            return response;
        },

        onConfirmChange:function(event){
            this.setState({confirmValue:event.target.value});
            this.onChange(event, this.state.value);
        },

        render:function(){
            if(this.isDisplayGrid() && !this.state.editMode){
                var value = this.state.value;
                return <div onClick={this.props.disabled?function(){}:this.toggleEditMode} className={value?'':'paramValue-empty'}>{!value?'Empty':value}</div>;
            }else{
                let errorText = this.state.errorText;
                let className, confirmError;
                if(this.state.value){
                    var response = this.getComplexityString(this.state.value);
                    errorText = <span style={{color: response.color}}>{response.responseString}</span>;
                    if(response.segment > 1){
                        className = "mui-error-as-hint";
                    }
                }
                if(this.state.confirmValue && this.state.confirmValue !== this.state.value){
                    errorText = 'Passwords differ';
                    className = undefined;
                    confirmError = '   ';
                }
                let confirm;
                if(this.state.value && !this.props.disabled){
                    confirm = [
                        <div key="sep" style={{width: 20}}></div>,
                        <ReactMUI.TextField
                            key="confirm"
                            floatingLabelText={'Please Confirm Password'}
                            className={className}
                            value={this.state.confirmValue}
                            onChange={this.onConfirmChange}
                            type='password'
                            multiLine={false}
                            disabled={this.props.disabled}
                            style={{flex:1}}
                            errorText={confirmError}
                        />
                    ];
                }
                return(
                    <form autoComplete="off">
                        <div style={{display:'flex'}}>
                            <ReactMUI.TextField
                                floatingLabelText={this.isDisplayForm()?this.props.attributes.label:null}
                                className={className}
                                value={this.state.value}
                                onChange={this.onChange}
                                onKeyDown={this.enterToToggle}
                                type='password'
                                multiLine={false}
                                disabled={this.props.disabled}
                                errorText={errorText}
                                style={{flex:1}}
                            />
                            {confirm}
                        </div>
                    </form>
                );
            }
        }

    });

    /**
     * Checkboxk input
     */
    var InputBoolean = React.createClass({

        mixins:[FormMixin],

        getDefaultProps:function(){
            return {
                skipBufferChanges:true
            };
        },

        componentDidUpdate:function(){
            // Checkbox Hack
            var boolVal = this.getBooleanState();
            if(this.refs.checkbox && !this.refs.checkbox.isChecked() && boolVal){
                this.refs.checkbox.setChecked(true);
            }else if(this.refs.checkbox && this.refs.checkbox.isChecked() && !boolVal){
                this.refs.checkbox.setChecked(false);
            }
        },

        onCheck:function(event){
            var newValue = this.refs.checkbox.isChecked();
            this.props.onChange(newValue, this.state.value);
            this.setState({
                dirty:true,
                value:newValue
            });
        },

        getBooleanState:function(){
            var boolVal = this.state.value;
            if(typeof(this.state.value) == 'string'){
                boolVal = (boolVal == "true");
            }
            return boolVal;
        },

        render:function(){
            var boolVal = this.getBooleanState();
            return(
                <span>
                    <ReactMUI.Checkbox
                        ref="checkbox"
                        defaultSwitched={boolVal}
                        disabled={this.props.disabled}
                        onCheck={this.onCheck}
                        label={this.isDisplayForm()?this.props.attributes.label:null}
                        labelPosition={this.isDisplayForm()?'left':'right'}
                    />
                </span>
            );
        }

    });

    var AutocompleteBox = React.createClass({

        mixins:[FormMixin],

        onSuggestionSelected: function(value, event){
            this.onChange(event, value);
        },

        getInitialState:function(){
            return {loading : 0};
        },

        suggestionLoader:function(input, callback) {

            this.setState({loading:true});
            let values = {};
            if(this.state.choices){
                this.state.choices.forEach(function(v){
                    if(v.indexOf(input) === 0){
                        values[v] = v;
                    }
                });
            }
            callback(null, LangUtils.objectValues(values));
            this.setState({loading:false});

        },

        getSuggestions(input, callback){
            FuncUtils.bufferCallback('suggestion-loader-search', 350, function(){
                this.suggestionLoader(input, callback);
            }.bind(this));
        },

        suggestionValue: function(suggestion){
            return '';
        },

        renderSuggestion(value){
            return <span>{value}</span>;
        },

        render: function(){

            const inputAttributes = {
                id: 'pydioform-autosuggest',
                name: 'pydioform-autosuggest',
                className: 'react-autosuggest__input',
                placeholder: this.props.attributes['label'],
                value: this.state.value   // Initial value
            };
            return (
                <div className="pydioform_autocomplete">
                    <span className={"suggest-search icon-" + (this.state.loading ? 'refresh rotating' : 'search')}/>
                    <ReactAutoSuggest
                        ref="autosuggest"
                        cache={true}
                        showWhen = {input => true }
                        inputAttributes={inputAttributes}
                        suggestions={this.getSuggestions}
                        suggestionRenderer={this.renderSuggestion}
                        suggestionValue={this.suggestionValue}
                        onSuggestionSelected={this.onSuggestionSelected}
                    />
                </div>

            );
        }

    });

    /**
     * Select box input conforming to Pydio standard form parameter.
     */
    var InputSelectBox = React.createClass({
        mixins:[FormMixin],

        getDefaultProps:function(){
            return {
                skipBufferChanges:true
            };
        },

        onDropDownChange:function(event, index, item){
            this.onChange(event, item.payload);
            this.toggleEditMode();
        },

        onMultipleSelectChange:function(joinedValue, arrayValue){
            this.onChange(null, joinedValue);
            this.toggleEditMode();
        },

        render:function(){
            var currentValue = this.state.value;
            var currentValueIndex = 0;
            var index=0;
            var menuItems = [], multipleOptions = [], mandatory = true;
            if(!this.props.attributes['mandatory'] || this.props.attributes['mandatory'] != "true"){
                mandatory = false;
                menuItems.unshift({payload:-1, text: this.props.attributes['label'] +  '...'});
                index ++;
            }
            var itemsMap = this.state.choices;
            itemsMap.forEach(function(value, key){
                if(currentValue == key) currentValueIndex = index;
                menuItems.push({payload:key, text:value});
                multipleOptions.push({value:key, label:value});
                index ++;
            });
            if((this.isDisplayGrid() && !this.state.editMode) || this.props.disabled){
                var value = this.state.value;
                if(itemsMap.get(value)) value = itemsMap.get(value);
                return (
                    <div
                        onClick={this.props.disabled?function(){}:this.toggleEditMode}
                        className={value?'':'paramValue-empty'}>
                    {!value?'Empty':value} &nbsp;&nbsp;<span className="icon-caret-down"></span>
                    </div>
                );
            } else {
                var hasValue = false;
                if(this.props.multiple && this.props.multiple == true){
                    if(typeof currentValue == "string"){
                        currentValue = currentValue.split(",");
                    }
                    hasValue = currentValue.length ? true: false;
                    return (
                        <span className={"drop-down-with-floating-label multiple has-value"}>
                            <label className="drop-down-floating-label">{this.props.attributes.label}</label>
                            <ReactSelect
                                options={multipleOptions}
                                value={currentValue}
                                name="test"
                                delimiter=","
                                multi={true}
                                onChange={this.onMultipleSelectChange}
                            />
                        </span>
                    );
                }else{
                    hasValue = currentValueIndex > 0 || mandatory;
                    return(
                        <span className={"drop-down-with-floating-label" + (hasValue?" has-value":"")}>
                            <label className="drop-down-floating-label">{this.props.attributes.label}</label>
                            <ReactMUI.DropDownMenu
                                menuItems={menuItems}
                                onChange={this.onDropDownChange}
                                selectedIndex={currentValueIndex}
                                autoWidth={false}
                                className={this.props.className}
                            />
                        </span>
                    );
                }
            }
        }
    });

    /**
     * Text input that is converted to integer, and
     * the UI can react to arrows for incrementing/decrementing values
     */
    var InputInteger = React.createClass({

        mixins:[FormMixin],

        keyDown: function(event){
            var inc = 0, multiple=1;
            if(event.key == 'Enter'){
                this.toggleEditMode();
                return;
            }else if(event.key == 'ArrowUp'){
                inc = +1;
            }else if(event.key == 'ArrowDown'){
                inc = -1;
            }
            if(event.shiftKey){
                multiple = 10;
            }
            var parsed = parseInt(this.state.value);
            if(isNaN(parsed)) parsed = 0;
            var value = parsed + (inc * multiple);
            this.onChange(null, value);
        },

        render:function(){
            if(this.isDisplayGrid() && !this.state.editMode){
                var value = this.state.value;
                return <div onClick={this.props.disabled?function(){}:this.toggleEditMode} className={value?'':'paramValue-empty'}>{!value?'Empty':value}</div>;
            }else{
                var intval;
                if(this.state.value){
                    intval = parseInt(this.state.value) + '';
                    if(isNaN(intval)) intval = this.state.value + '';
                }else{
                    intval = '0';
                }
                return(
                    <span className="integer-input">
                        <ReactMUI.TextField
                            value={intval}
                            onChange={this.onChange}
                            onKeyDown={this.keyDown}
                            disabled={this.props.disabled}
                            floatingLabelText={this.isDisplayForm()?this.props.attributes.label:null}
                        />
                    </span>
                );
            }
        }

    });

    /**
     * UI for displaying and uploading an image,
     * using the binaryContext string.
     */
    var InputImage = React.createClass({

        mixins:[FormMixin],

        propTypes: {
            attributes: React.PropTypes.object,
            binary_context: React.PropTypes.string
        },

        componentWillReceiveProps(newProps){
            var imgSrc;
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
            var imgSrc, originalBinary;
            if(this.props.value){
                imgSrc = this.getBinaryUrl(this.props.value);
                originalBinary = this.props.value;
            }else if(this.props.attributes['defaultImage']){
                imgSrc = this.props.attributes['defaultImage'];
            }
            return {imageSrc:imgSrc, originalBinary:originalBinary};
        },

        getBinaryUrl: function(binaryId, isTemporary=false){
            var url = global.pydio.Parameters.get('ajxpServerAccess') + "&get_action=" +this.props.attributes['loadAction'];
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
            var params = "get_action=" +this.props.attributes['uploadAction'];
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
            var prevValue = this.state.value;
            this.setState({
                temporaryBinary:newBinaryName,
                value:null
            });
            if(this.props.onChange){
                var additionalFormData = {type:'binary'};
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
                        var result = transport.responseText.trim().replace(/<\w+(\s+("[^"]*"|'[^']*'|[^>])+)?>|<\/\w+>/gi, '');
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
                var prevValue = this.state.value;
                this.setState({
                    value:'ajxp-remove-original',
                    reset:true
                }, function(){
                    this.props.onChange('ajxp-remove-original', prevValue, {type:'binary'});
                }.bind(this));

            }
        },

        render: function(){
            var coverImageStyle = {
                backgroundImage:"url("+this.state.imageSrc+")",
                backgroundPosition:"50% 50%",
                backgroundSize:"cover"
            };
            var icons = [];
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

    var ActionRunnerMixin = {

        propTypes:{
            attributes:React.PropTypes.object.isRequired,
            applyButtonAction:React.PropTypes.func,
            actionCallback:React.PropTypes.func
        },

        applyAction:function(callback){
            var choicesValue = this.props.attributes['choices'].split(":");
            var firstPart = choicesValue.shift();
            if(firstPart == "run_client_action" && global.pydio){
                global.pydio.getController().fireAction(choicesValue.shift());
                return;
            }
            if(this.props.applyButtonAction){
                var parameters = {get_action:firstPart};
                if(choicesValue.length > 1){
                    parameters['action_plugin_id'] = choicesValue.shift();
                    parameters['action_plugin_method'] = choicesValue.shift();
                }
                if(this.props.attributes['name'].indexOf("/") !== -1){
                    parameters['button_key'] = PathUtils.getDirname(this.props.attributes['name']);
                }
                this.props.applyButtonAction(parameters, callback);
            }
        }

    };

    /**
     * Simple RaisedButton executing the applyButtonAction
     */
    var InputButton = React.createClass({

        mixins:[ActionRunnerMixin],


        applyButton:function(){

            var callback = this.props.actionCallback;
            if(!callback){
                callback = function(transport){
                    var text = transport.responseText;
                    if(text.startsWith('SUCCESS:')){
                        global.pydio.displayMessage('SUCCESS', transport.responseText.replace('SUCCESS:', ''));
                    }else{
                        global.pydio.displayMessage('ERROR', transport.responseText.replace('ERROR:', ''));
                    }
                };
            }
            this.applyAction(callback);

        },

        render:function(){
            return (
                <ReactMUI.RaisedButton
                    label={this.props.attributes['label']}
                    onClick={this.applyButton}
                    disabled={this.props.disabled}
                />
            );
        }
    });


    var MonitoringLabel = React.createClass({

        mixins:[ActionRunnerMixin],

        getInitialState:function(){
            let loadingMessage = 'Loading';
            if(this.context && this.context.getMessage){
                loadingMessage = this.context.getMessage(466, '');
            }else if(global.pydio && global.pydio.MessageHash){
                loadingMessage = global.pydio.MessageHash[466];
            }
            return {status:loadingMessage};
        },

        componentDidMount:function(){
            var callback = function(transport){
                this.setState({status:transport.responseText});
            }.bind(this);
            this._poller = function(){
                this.applyAction(callback);
            }.bind(this);
            this._poller();
            this._pe = global.setInterval(this._poller, 10000);
        },

        componentWillUnmount:function(){
            if(this._pe){
                global.clearInterval(this._pe);
            }
        },

        render: function(){
            return (<div>{this.state.status}</div>);
        }


    });


    /**
     * UI to drop a file (or click to browse), used by the InputImage component.
     */
    var FileDropzone = React.createClass({

        getDefaultProps: function() {
            return {
                supportClick: true,
                multiple: true,
                onDrop:function(){}
            };
        },

        getInitialState: function() {
            return {
                isDragActive: false
            }
        },

        propTypes: {
            onDrop: React.PropTypes.func.isRequired,
            size: React.PropTypes.number,
            style: React.PropTypes.object,
            supportClick: React.PropTypes.bool,
            accept: React.PropTypes.string,
            multiple: React.PropTypes.bool
        },

        onDragLeave: function(e) {
            this.setState({
                isDragActive: false
            });
        },

        onDragOver: function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "copy";

            this.setState({
                isDragActive: true
            });
        },

        onDrop: function(e) {
            e.preventDefault();

            this.setState({
                isDragActive: false
            });

            var files;
            if (e.dataTransfer) {
                files = e.dataTransfer.files;
            } else if (e.target) {
                files = e.target.files;
            }

            var maxFiles = (this.props.multiple) ? files.length : 1;
            for (var i = 0; i < maxFiles; i++) {
                files[i].preview = URL.createObjectURL(files[i]);
            }

            if (this.props.onDrop) {
                files = Array.prototype.slice.call(files, 0, maxFiles);
                this.props.onDrop(files, e, this);
            }
        },

        onClick: function () {
            if (this.props.supportClick === true) {
                this.open();
            }
        },

        open: function() {
            this.refs.fileInput.click();
        },

        onFolderPicked: function(e){
            if(this.props.onFolderPicked){
                this.props.onFolderPicked(e.target.files);
            }
        },

        openFolderPicker: function(){
            this.refs.folderInput.setAttribute("webkitdirectory", "true");
            this.refs.folderInput.click();
        },

        render: function() {

            var className = this.props.className || 'file-dropzone';
            if (this.state.isDragActive) {
                className += ' active';
            }

            let style = {
                width: this.props.size || 100,
                height: this.props.size || 100,
                //borderStyle: this.state.isDragActive ? "solid" : "dashed"
            };
            if(this.props.style){
                style = Object.assign(style, this.props.style);
            }
            if(this.props.enableFolders){
                var folderInput = <input style={{display:'none'}} name="userfolder" type="file" ref="folderInput" onChange={this.onFolderPicked}/>;
            }
            return (
                <div className={className} style={style} onClick={this.onClick} onDragLeave={this.onDragLeave} onDragOver={this.onDragOver} onDrop={this.onDrop}>
                    <input style={{display:'none'}} name="userfile" type="file" multiple={this.props.multiple} ref="fileInput" onChange={this.onDrop} accept={this.props.accept}/>
                    {folderInput}
                {this.props.children}
                </div>
            );
        }

    });

    /**
     * Display a form companion linked to a given input.
     * Props: helperData : contains the pluginId and the whole paramAttributes
     */
    var PydioFormHelper = React.createClass({

        propTypes:{
            helperData:React.PropTypes.object,
            close:React.PropTypes.func.isRequired
        },

        closeHelper:function(){
            this.props.close();
        },

        render: function(){
            var helper;
            if(this.props.helperData){
                var helpersCache = PydioFormManager.getHelpersCache();
                var pluginHelperNamespace = helpersCache[this.props.helperData['pluginId']]['namespace'];
                helper = (
                    <div>
                        <div className="helper-title">
                            <span className="helper-close mdi mdi-close" onClick={this.closeHelper}></span>
                            Pydio Companion
                        </div>
                        <div className="helper-content">
                            <PydioReactUI.AsyncComponent
                                {...this.props.helperData}
                                namespace={pluginHelperNamespace}
                                componentName="Helper"
                                paramName={this.props.helperData['paramAttributes']['name']}
                            />
                        </div>
                    </div>);
            }
            return <div className={'pydio-form-helper' + (helper?' helper-visible':' helper-empty')}>{helper}</div>;
        }

    });

    /**
     * Sub form with a selector, switching its fields depending
     * on the selector value.
     */
    var GroupSwitchPanel = React.createClass({

        propTypes:{
            paramAttributes:React.PropTypes.object.isRequired,
            parameters:React.PropTypes.array.isRequired,
            values:React.PropTypes.object.isRequired,
            onChange:React.PropTypes.func.isRequired
        },

        computeSubPanelParameters:function(){

            // CREATE SUB FORM PANEL
            // Get all values
            var switchName = this.props.paramAttributes['type'].split(":").pop();
            var switchValues = {};
            var parentName = this.props.paramAttributes['name'];
            var potentialSubSwitches = [];
            this.props.parameters.map(function(p){
                "use strict";
                if(!p['group_switch_name']) return;
                if(p['group_switch_name'] != switchName){
                    potentialSubSwitches.push(p);
                    return;
                }
                var crtSwitch = p['group_switch_value'];
                if(!switchValues[crtSwitch]){
                    switchValues[crtSwitch] = {
                        label :p['group_switch_label'],
                        fields : [],
                        values : {},
                        fieldsKeys:{}
                    };
                }
                p = LangUtils.deepCopy(p);
                delete p['group_switch_name'];
                p['name'] =  parentName + '/' + p['name'];
                var vKey = p['name'];
                var paramName = vKey;
                if(switchValues[crtSwitch].fieldsKeys[paramName]){
                    return;
                }
                switchValues[crtSwitch].fields.push(p);
                switchValues[crtSwitch].fieldsKeys[paramName] = paramName;
                if(this.props.values && this.props.values[vKey]){
                    switchValues[crtSwitch].values[paramName] = this.props.values[vKey];
                }
            }.bind(this));
            // Remerge potentialSubSwitches to each parameters set
            for(var k in switchValues){
                if(switchValues.hasOwnProperty(k)){
                    var sv = switchValues[k];
                    sv.fields = sv.fields.concat(potentialSubSwitches);
                }
            }

            return switchValues;

        },

        clearSubParametersValues:function(parentName, newValue, newFields){
            var vals = LangUtils.deepCopy(this.props.values);
            var toRemove = {};
            for(var key in vals){
                if(vals.hasOwnProperty(key) && key.indexOf(parentName+'/') === 0){
                    toRemove[key] = '';
                }
            }
            vals[parentName] = newValue;

            newFields.map(function(p){
                if(p.type == 'hidden' && p['default'] && !p['group_switch_name'] || p['group_switch_name'] == parentName) {
                    vals[p['name']] = p['default'];
                    if(toRemove[p['name']] !== undefined) delete toRemove[p['name']];
                }else if(p['name'].indexOf(parentName+'/') === 0 && p['default']){
                    if(p['type'] && p['type'].startsWith('group_switch:')){
                        //vals[p['name']] = {group_switch_value:p['default']};
                        vals[p['name']] = p['default'];
                    }else{
                        vals[p['name']] = p['default'];
                    }
                }
            });
            this.props.onChange(vals, true, toRemove);
            //this.onParameterChange(parentName, newValue);
        },

        onChange:function(newValues, dirty, removeValues){
            this.props.onChange(newValues, true, removeValues);
        },

        render:function(){
            var attributes = this.props.paramAttributes;
            var values = this.props.values;

            var paramName = attributes['name'];
            var switchValues = this.computeSubPanelParameters(attributes);
            var selectorValues = new Map();
            Object.keys(switchValues).map(function(k) {
                selectorValues.set(k, switchValues[k].label);
            });
            var selectorChanger = function(newValue){
                this.clearSubParametersValues(paramName, newValue, switchValues[newValue]?switchValues[newValue].fields:[]);
            }.bind(this);
            var subForm, selectorLegend, subFormHeader;
            var selector = (
                <InputSelectBox
                    key={paramName}
                    name={paramName}
                    className="group-switch-selector"
                    attributes={{
                        name:paramName,
                        choices:selectorValues,
                        label:attributes['label'],
                        mandatory:attributes['mandatory']
                    }}
                    value={values[paramName]}
                    onChange={selectorChanger}
                    displayContext='form'
                    disabled={this.props.disabled}
                    ref="subFormSelector"
                />
            );

            var helperMark;
            if(this.props.setHelperData && this.props.checkHasHelper && this.props.checkHasHelper(attributes['name'], this.props.helperTestFor)){
                var showHelper = function(){
                    this.props.setHelperData({paramAttributes:attributes, values:values});
                }.bind(this);
                helperMark = <span className="icon-question-sign" onClick={showHelper}></span>;
            }

            selectorLegend = (
                <div className="form-legend">{attributes['description']} {helperMark}</div>
            );
            if(values[paramName] && switchValues[values[paramName]]){
                subFormHeader = (
                    <h4>{values[paramName]}</h4>
                );
                subForm = (
                    <PydioFormPanel
                        onParameterChange={this.props.onParameterChange}
                        applyButtonAction={this.props.applyButtonAction}
                        disabled={this.props.disabled}
                        ref={paramName + '-SUB'}
                        key={paramName + '-SUB'}
                        className="sub-form"
                        parameters={switchValues[values[paramName]].fields}
                        values={values}
                        depth={this.props.depth+1}
                        onChange={this.onChange}
                        checkHasHelper={this.props.checkHasHelper}
                        setHelperData={this.props.setHelperData}
                        helperTestFor={values[paramName]}
                        accordionizeIfGroupsMoreThan={5}
                    />
                );
            }
            return (
                <div className="sub-form-group">
                    {selector}
                    {selectorLegend}
                    {subForm}
                </div>
            );
        }

    });

    /**
     * Sub form replicating itself (+/-)
     */
    var ReplicationPanel = React.createClass({

        propTypes:{
            parameters:React.PropTypes.array.isRequired,
            values:React.PropTypes.object,
            onChange:React.PropTypes.func,
            disabled:React.PropTypes.bool,
            binary_context:React.PropTypes.string,
            depth:React.PropTypes.number
        },

        buildSubValue:function(values, index=0){
            var subVal;
            var suffix = index==0?'':'_'+index;
            this.props.parameters.map(function(p){
                var pName = p['name'];
                if(values[pName+suffix] !== undefined){
                    if(!subVal) subVal = {};
                    subVal[pName] = values[pName+suffix];
                }
            });
            return subVal || false;
        },

        indexedValues:function(rowsArray){
            var index = 0;
            var values = {};
            rowsArray.map(function(row){
                var suffix = index==0?'':'_'+index;
                for(var p in row){
                    if(!row.hasOwnProperty(p)) continue;
                    values[p+suffix] = row[p];
                }
                index ++;
            });
            return values;
        },

        indexValues:function(rowsArray, removeLastRow){
            var indexed = this.indexedValues(rowsArray);
            if(this.props.onChange){
                if(removeLastRow){
                    var lastRow = {}, nextIndex = rowsArray.length-1;
                    this.props.parameters.map(function(p){
                        lastRow[p['name'] + '_' + nextIndex] = '';
                    });
                    this.props.onChange(indexed, true, lastRow);
                }else{
                    this.props.onChange(indexed, true);
                }
            }
        },

        instances:function(){
            // Analyze current value to grab number of rows.
            var rows = [], subVal, index = 0;
            while(subVal = this.buildSubValue(this.props.values, index)){
                index ++;
                rows.push(subVal);
            }
            if(!rows.length){
                var emptyValue={};
                this.props.parameters.map(function(p) {
                    emptyValue[p['name']] = p['default'] || '';
                });
                rows.push(emptyValue);
            }
            return rows;
        },

        addRow:function(){
            var newValue={}, currentValues = this.instances();
            this.props.parameters.map(function(p) {
                newValue[p['name']] = p['default'] || '';
            });
            currentValues.push(newValue);
            this.indexValues(currentValues);
        },

        removeRow:function(index){
            var instances = this.instances();
            var removeInst = instances[index];
            instances = LangUtils.arrayWithout(this.instances(), index);
            instances.push(removeInst);
            this.indexValues(instances, true);
        },

        swapRows:function(i,j){
            var instances = this.instances();
            var tmp = instances[j];
            instances[j] = instances[i];
            instances[i] = tmp;
            this.indexValues(instances);
        },

        onChange:function(index, newValues, dirty){
            var instances = this.instances();
            instances[index] = newValues;
            this.indexValues(instances);
        },

        onParameterChange:function(index, paramName, newValue, oldValue){
            var instances = this.instances();
            instances[index][paramName] = newValue;
            this.indexValues(instances);
        },

        render:function(){
            var replicationTitle, replicationDescription;
            var firstParam = this.props.parameters[0];
            replicationTitle = firstParam['replicationTitle'] || firstParam['label'];
            replicationDescription = firstParam['replicationDescription'] || firstParam['description'];

            var instances = this.instances();
            var rows = instances.map(function(subValues, index){
                var buttons = [];
                if(instances.length > 1){
                    if(index > 0){
                        var upF = function(){ this.swapRows(index, index-1) }.bind(this);
                        buttons.push(<ReactMUI.IconButton key="up" iconClassName="icon-caret-up" onClick={upF}/>);
                    }
                    if(index < instances.length -1){
                        var downF = function(){ this.swapRows(index, index+1) }.bind(this);
                        buttons.push(<ReactMUI.IconButton key="down" iconClassName="icon-caret-down" onClick={downF}/>);
                    }
                }
                if(index != 0 || instances.length > 1){
                    var removeF = function(){ this.removeRow(index); }.bind(this);
                    buttons.push(<ReactMUI.IconButton key="remove" iconClassName="icon-remove-sign" onClick={removeF}/>);
                }
                if(!buttons.length){
                    buttons.push(<ReactMUI.IconButton key="remove" className="disabled" iconClassName="icon-remove-sign" disabled={true}/>);
                }
                var actionBar = (
                    <div className="replicable-action-bar">{buttons}</div>
                );
                var onChange = function(values, dirty){ this.onChange(index, values, dirty); }.bind(this);
                var onParameterChange = function(paramName, newValue, oldValue){ this.onParameterChange(index, paramName, newValue, oldValue); }.bind(this);
                return (
                    <PydioFormPanel
                        {...this.props}
                        tabs={null}
                        key={index}
                        values={subValues}
                        onChange={null}
                        onParameterChange={onParameterChange}
                        header={actionBar}
                        className="replicable-group"
                        depth={this.props.depth}
                    />
                );
            }.bind(this));
            return (
                <div className="replicable-field">
                    <div className="title-bar">
                        <ReactMUI.IconButton key="add" style={{float:'right'}} iconClassName="icon-plus" tooltip="Add value" onClick={this.addRow}/>
                        <div className="title">{replicationTitle}</div>
                        <div className="legend">{replicationDescription}</div>
                    </div>
                    {rows}
                </div>

            );
        }

    });

    /**
     * Main form constructor
     * @param parameters Set of Pydio StandardForm parameters
     * @param values Set of values (object)
     * @param onParameterChange Trigger unitary function when one form input changes.
     * @param onChange Send all form values onchange, including eventually the removed ones (for dynamic panels)
     * @param disabled
     * @param binary_context a string added to image inputs upload/downlaod operations
     * @param depth  by default 0, subform will see their zDepth increased by one.
     * @param header Add an additional header component (added inside first subpanel)
     * @param footer  Add an additional footer component (added inside last subpanel).
     * @param additionalPanes Add full panes at the top or the bottom
     * @param tabs group form panels in tabs instead of displaying multiple papers.
     * @param setHelperData Pass helper data
     * @param checkHasHelper Function to check if a given parameter has an associated helper.
     */
    var PydioFormPanel = React.createClass({

        _hiddenValues:{},
        _internalValid:null,
        _parametersMetadata:null,

        propTypes:{
            parameters:React.PropTypes.array.isRequired,
            values:React.PropTypes.object,
            onParameterChange:React.PropTypes.func,
            onChange:React.PropTypes.func,
            onValidStatusChange:React.PropTypes.func,
            disabled:React.PropTypes.bool,
            binary_context:React.PropTypes.string,
            depth:React.PropTypes.number,

            /* Display Options */
            header:React.PropTypes.object,
            footer:React.PropTypes.object,
            additionalPanes:React.PropTypes.shape({
                top:React.PropTypes.array,
                bottom:React.PropTypes.array
            }),
            tabs:React.PropTypes.array,
            onTabChange:React.PropTypes.func,
            accordionizeIfGroupsMoreThan:React.PropTypes.number,
            onScrollCallback:React.PropTypes.func,
            limitToGroups:React.PropTypes.array,
            skipFieldsTypes:React.PropTypes.array,

            /* Helper Options */
            setHelperData:React.PropTypes.func,
            checkHasHelper:React.PropTypes.func,
            helperTestFor:React.PropTypes.string

        },

        externallySelectTab:function(index){
            try{
                let t = this.refs.tabs;
                let c = this.refs.tabs.props.children[index];
                t.handleTouchTap(index, c);
            }catch(e){
                if(global.console) global.console.log(e);
            }
        },

        getDefaultProps:function(){
            return { depth:0, values:{} };
        },

        componentWillReceiveProps: function(newProps){
            if(JSON.stringify(newProps.parameters) !== JSON.stringify(this.props.parameters)){
                this._internalValid = null;
                this._hiddenValues = {};
                this._parametersMetadata = {};
            }
        },

        getValues:function(){
            return this.props.values;//LangUtils.mergeObjectsRecursive(this._hiddenValues, this.props.values);
        },

        onParameterChange: function(paramName, newValue, oldValue, additionalFormData=null){
            // Update writeValues
            var newValues = LangUtils.deepCopy(this.getValues());
            if(this.props.onParameterChange) {
                this.props.onParameterChange(paramName, newValue, oldValue, additionalFormData);
            }
            if(additionalFormData){
                if(!this._parametersMetadata) this._parametersMetadata = {};
                this._parametersMetadata[paramName] = additionalFormData;
            }
            newValues[paramName] = newValue;
            var dirty = true;
            this.onChange(newValues, dirty);
        },

        onChange:function(newValues, dirty, removeValues){
            if(this.props.onChange) {
                //newValues = LangUtils.mergeObjectsRecursive(this._hiddenValues, newValues);
                for(var key in this._hiddenValues){
                    if(this._hiddenValues.hasOwnProperty(key) && newValues[key] === undefined && (!removeValues || removeValues[key] == undefined)){
                         newValues[key] = this._hiddenValues[key];
                    }
                }
                this.props.onChange(newValues, dirty, removeValues);
            }
            this.checkValidStatus(newValues);
        },

        onSubformChange:function(newValues, dirty, removeValues){
            var values = LangUtils.mergeObjectsRecursive(this.getValues(), newValues);
            if(removeValues){
                for(var k in removeValues){
                    if(removeValues.hasOwnProperty(k) && values[k] !== undefined){
                        delete values[k];
                        if(this._hiddenValues[k] !== undefined){
                            delete this._hiddenValues[k];
                        }
                    }
                }
            }
            this.onChange(values, dirty, removeValues);
        },

        onSubformValidStatusChange:function(newValidValue, failedMandatories){
            if((newValidValue !== this._internalValid || this.props.forceValidStatusCheck) && this.props.onValidStatusChange) {
                this.props.onValidStatusChange(newValidValue, failedMandatories);
            }
            this._internalValid = newValidValue;
        },

        applyButtonAction: function(parameters, callback){
            if(this.props.applyButtonAction){
                this.props.applyButtonAction(parameters, callback);
                return;
            }
            parameters = LangUtils.mergeObjectsRecursive(parameters, this.getValuesForPOST(this.getValues()));
            PydioApi.getClient().request(parameters, callback);
        },

        getValuesForPOST:function(values, prefix='DRIVER_OPTION_'){
            return PydioFormManager.getValuesForPOST(this.props.parameters, values, prefix, this._parametersMetadata);
        },

        checkValidStatus:function(values){
            var failedMandatories = [];
            this.props.parameters.map(function(p){
                if (['string', 'textarea', 'password', 'integer'].indexOf(p.type) > -1 && (p.mandatory == "true" || p.mandatory === true)) {
                    if(!values || !values.hasOwnProperty(p.name) || values[p.name] === undefined || values[p.name] === ""){
                        failedMandatories.push(p);
                    }
                }
                if( ( p.type === 'valid-password' ) && this.refs['form-element-' + p.name]){
                    if(!this.refs['form-element-' + p.name].isValid()){
                        failedMandatories.push(p);
                    }
                }
            }.bind(this));
            var previousValue, newValue;
            previousValue = this._internalValid;//(this._internalValid !== undefined ? this._internalValid : true);
            newValue = failedMandatories.length ? false : true;
            if((newValue !== this._internalValid || this.props.forceValidStatusCheck) && this.props.onValidStatusChange) {
                this.props.onValidStatusChange(newValue, failedMandatories);
            }
            this._internalValid = newValue;
        },

        componentDidMount:function(){
            this.checkValidStatus(this.props.values);
        },

        renderGroupHeader:function(groupLabel, accordionize, index, active){

            var properties = { key: 'group-' + groupLabel };
            if(accordionize){
                var current = (this.state && this.state.currentActiveGroup) ? this.state.currentActiveGroup : null;
                properties['className'] = 'group-label-' + (active ? 'active' : 'inactive');
                properties['onClick'] = function(){
                    this.setState({currentActiveGroup:(current != index ? index : null)});
                }.bind(this);
                groupLabel = [<span key="toggler" className={"group-active-toggler icon-angle-" + (current == index ? 'down' : 'right') }></span>, groupLabel];
            }

            return React.createElement(
                'h' + (3 + this.props.depth),
                properties,
                groupLabel
            );

        },

        render:function(){
            var allGroups = [];
            var values = this.getValues();
            var groupsOrdered = ['__DEFAULT__'];
            allGroups['__DEFAULT__'] = {FIELDS:[]};
            var replicationGroups = {};

            this.props.parameters.map(function(attributes){

                var type = attributes['type'];
                if(this.props.skipFieldsTypes && this.props.skipFieldsTypes.indexOf(type) > -1){
                    return;
                }
                var paramName = attributes['name'];
                var field;
                if(attributes['group_switch_name']) return;

                var group = attributes['group'] || '__DEFAULT__';
                if(!allGroups[group]){
                    groupsOrdered.push(group);
                    allGroups[group] = {FIELDS:[], LABEL:group};
                }

                var repGroup = attributes['replicationGroup'];
                if(repGroup) {

                    if (!replicationGroups[repGroup]) {
                        replicationGroups[repGroup] = {
                            PARAMS: [],
                            GROUP: group,
                            POSITION: allGroups[group].FIELDS.length
                        };
                        allGroups[group].FIELDS.push('REPLICATION:' + repGroup);
                    }
                    // Copy
                    var repAttr = LangUtils.deepCopy(attributes);
                    delete repAttr['replicationGroup'];
                    delete repAttr['group'];
                    replicationGroups[repGroup].PARAMS.push(repAttr);

                }else{

                    if(type.indexOf("group_switch:") === 0){

                        field = (
                            <GroupSwitchPanel
                                {...this.props}
                                onChange={this.onSubformChange}
                                paramAttributes={attributes}
                                parameters={this.props.parameters}
                                values={this.props.values}
                                key={paramName}
                                onScrollCallback={null}
                                limitToGroups={null}
                                onValidStatusChange={this.onSubformValidStatusChange}
                            />
                        );

                    }else if(attributes['type'] !== 'hidden'){

                        var helperMark;
                        if(this.props.setHelperData && this.props.checkHasHelper && this.props.checkHasHelper(attributes['name'], this.props.helperTestFor)){
                            var showHelper = function(){
                                this.props.setHelperData({
                                    paramAttributes:attributes,
                                    values:values,
                                    postValues:this.getValuesForPOST(values),
                                    applyButtonAction:this.applyButtonAction
                                }, this.props.helperTestFor);
                            }.bind(this);
                            helperMark = <span className="icon-question-sign" onClick={showHelper}></span>;
                        }
                        var mandatoryMissing = false;
                        var classLegend = "form-legend";
                        if(attributes['errorText']) {
                            classLegend = "form-legend mandatory-missing";
                        }else if(attributes['warningText']){
                            classLegend = "form-legend warning-message";
                        }else if( attributes['mandatory'] && (attributes['mandatory'] === "true" || attributes['mandatory'] === true) ){
                            if(['string', 'textarea', 'image', 'integer'].indexOf(attributes['type']) !== -1 && !values[paramName]){
                                mandatoryMissing = true;
                                classLegend = "form-legend mandatory-missing";
                            }
                        }

                        var props = {
                            ref:"form-element-" + paramName,
                            attributes:attributes,
                            name:paramName,
                            value:values[paramName],
                            onChange:function(newValue, oldValue, additionalFormData){
                                this.onParameterChange(paramName, newValue, oldValue, additionalFormData);
                            }.bind(this),
                            disabled:this.props.disabled || attributes['readonly'],
                            multiple:attributes['multiple'],
                            binary_context:this.props.binary_context,
                            displayContext:'form',
                            applyButtonAction:this.applyButtonAction,
                            errorText:mandatoryMissing?'Field cannot be empty':(attributes.errorText?attributes.errorText:null)
                        };

                        field = (
                            <div key={paramName} className={'form-entry-' + attributes['type']}>
                                {PydioForm.createFormElement(props)}
                                <div className={classLegend}>{attributes['warningText'] ? attributes['warningText'] : attributes['description']} {helperMark}</div>
                            </div>
                        );
                    }else{

                        this._hiddenValues[paramName] = (values[paramName] !== undefined ? values[paramName] : attributes['default']);

                    }

                    if(field) {
                        allGroups[group].FIELDS.push(field);
                    }

                }


            }.bind(this));

            for(var rGroup in replicationGroups){
                if (!replicationGroups.hasOwnProperty(rGroup)) continue;
                var rGroupData = replicationGroups[rGroup];
                allGroups[rGroupData.GROUP].FIELDS[rGroupData.POSITION] = (
                    <ReplicationPanel
                        {...this.props}
                        key={"replication-group-" + rGroupData.PARAMS[0].name}
                        onChange={this.onSubformChange}
                        onParameterChange={null}
                        values={this.getValues()}
                        depth={this.props.depth+1}
                        parameters={rGroupData.PARAMS}
                        applyButtonAction={this.applyButtonAction}
                        onScrollCallback={null}
                    />
                );
            }

            var groupPanes = [];
            var accordionize = (this.props.accordionizeIfGroupsMoreThan && groupsOrdered.length > this.props.accordionizeIfGroupsMoreThan);
            var currentActiveGroup = (this.state && this.state.currentActiveGroup) ? this.state.currentActiveGroup : 0;
            groupsOrdered.map(function(g, gIndex) {
                if(this.props.limitToGroups && this.props.limitToGroups.indexOf(g) === -1){
                    return;
                }
                var header, gData = allGroups[g];
                var className = 'pydio-form-group', active = false;
                if(accordionize){
                    active = (currentActiveGroup == gIndex);
                    if(gIndex == currentActiveGroup) className += ' form-group-active';
                    else className += ' form-group-inactive';
                }
                if (!gData.FIELDS.length) return;
                if (gData.LABEL && !(this.props.skipFieldsTypes && this.props.skipFieldsTypes.indexOf('GroupHeader') > -1)) {
                    header = this.renderGroupHeader(gData.LABEL, accordionize, gIndex, active);
                }
                if(this.props.depth == 0){
                    groupPanes.push(
                        <ReactMUI.Paper className={className} key={'pane-'+g}>
                            {gIndex==0 && this.props.header? this.props.header: null}
                            {header}
                            <div>
                                {gData.FIELDS}
                            </div>
                            {gIndex==groupsOrdered.length-1 && this.props.footer? this.props.footer: null}
                        </ReactMUI.Paper>
                    );
                }else{
                    groupPanes.push(
                        <div className={className} key={'pane-'+g}>
                            {gIndex==0 && this.props.header? this.props.header: null}
                            {header}
                            <div>
                                {gData.FIELDS}
                            </div>
                            {gIndex==groupsOrdered.length-1 && this.props.footer? this.props.footer: null}
                        </div>
                    );
                }
            }.bind(this));
            if(this.props.additionalPanes){
                var otherPanes = {top:[], bottom:[]};
                var depth = this.props.depth;
                var index = 0;
                for(var k in otherPanes){
                    if(!otherPanes.hasOwnProperty(k)) continue;
                    if(this.props.additionalPanes[k]){
                        this.props.additionalPanes[k].map(function(p){
                            if(depth == 0){
                                otherPanes[k].push(
                                    <ReactMUI.Paper className="pydio-form-group additional" key={'other-pane-'+index}>{p}</ReactMUI.Paper>
                                );
                            }else{
                                otherPanes[k].push(
                                    <div className="pydio-form-group additional" key={'other-pane-'+index}>{p}</div>
                                );
                            }
                            index++;
                        });
                    }
                }
                groupPanes = otherPanes['top'].concat(groupPanes).concat(otherPanes['bottom']);
            }

            if(this.props.tabs){
                var className = this.props.className;
                let initialSelectedIndex = 0;
                let i = 0;
                var tabs = this.props.tabs.map(function(tDef){
                    var label = tDef['label'];
                    var groups = tDef['groups'];
                    if(tDef['selected']){
                        initialSelectedIndex = i;
                    }
                    var panes = groups.map(function(gId){
                        if(groupPanes[gId]){
                            return groupPanes[gId];
                        }else{
                            return null;
                        }
                    });
                    i++;
                    return(
                        <ReactMUI.Tab label={label} key={label}>
                            <div className={(className?className+' ':' ') + 'pydio-form-panel' + (panes.length % 2 ? ' form-panel-odd':'')}>
                            {panes}
                            </div>
                        </ReactMUI.Tab>
                    );
                }.bind(this));
                return (
                    <div className="layout-fill vertical-layout tab-vertical-layout">
                        <ReactMUI.Tabs ref="tabs" initialSelectedIndex={initialSelectedIndex} onChange={this.props.onTabChange}>
                            {tabs}
                        </ReactMUI.Tabs>
                    </div>
                );

            }else{
                return (
                    <div className={(this.props.className?this.props.className+' ':' ') + 'pydio-form-panel' + (groupPanes.length % 2 ? ' form-panel-odd':'')} onScroll={this.props.onScrollCallback}>
                        {groupPanes}
                    </div>
                );
            }


        }

    });

    /**
     * Utility class to parse / handle pydio standard form definitions/values.
     */
    class PydioFormManager{

        static hasHelper(pluginId, paramName){

            var helpers = PydioFormManager.getHelpersCache();
            return (helpers[pluginId] && helpers[pluginId]['parameters'][paramName]);
        }

        static getHelpersCache(){
            if(!PydioFormManager.HELPERS_CACHE){
                var helperCache = {};
                var helpers = XMLUtils.XPathSelectNodes(global.pydio.Registry.getXML(), 'plugins/*/client_settings/resources/js[@type="helper"]');
                for(var i = 0; i<helpers.length; i++){
                    var helperNode = helpers[i];
                    var plugin = helperNode.getAttribute("plugin");
                    helperCache[plugin] = {namespace:helperNode.getAttribute('className'), parameters:{}};
                    var paramNodes = XMLUtils.XPathSelectNodes(helperNode, 'parameter');
                    for(var k=0; k<paramNodes.length;k++){
                        var paramNode = paramNodes[k];
                        helperCache[plugin]['parameters'][paramNode.getAttribute('name')] = true;
                    }
                }
                PydioFormManager.HELPERS_CACHE = helperCache;
            }
            return PydioFormManager.HELPERS_CACHE;
        }

        static parseParameters(xmlDocument, query){
            return XMLUtils.XPathSelectNodes(xmlDocument, query).map(function(node){
                return PydioFormManager.parameterNodeToHash(node);
            }.bind(this));
        }

        static parameterNodeToHash(paramNode){
            var paramsAtts = paramNode.attributes;
            var paramsObject = {};
            var collectCdata = false;
            for(var i=0; i<paramsAtts.length; i++){
                var attName = paramsAtts.item(i).nodeName;
                var value = paramsAtts.item(i).value;
                if( (attName == "label" || attName == "description" || attName == "group" || attName.indexOf("group_switch_") === 0) && MessageHash[value] ){
                    value = MessageHash[value];
                }
                if( attName == "cdatavalue" ){
                    collectCdata = true;
                    continue;
                }
                paramsObject[attName] = value;
            }
            if(collectCdata){
                paramsObject['value'] = paramNode.firstChild.value;
            }
            if(paramsObject['type'] == 'boolean'){
                if(paramsObject['value'] !== undefined) paramsObject['value'] = (paramsObject['value'] == "true");
                if(paramsObject['default'] !== undefined) paramsObject['default'] = (paramsObject['default'] == "true");
            }else if(paramsObject['type'] == 'integer'){
                if(paramsObject['value'] !== undefined) paramsObject['value'] = parseInt(paramsObject['value']);
                if(paramsObject['default'] !== undefined) paramsObject['default'] = parseInt(paramsObject['default']);
            }
            return paramsObject;
        }

        static createFormelement(props){
            var value;
            switch(props.attributes['type']){
                case 'boolean':
                    value = <InputBoolean {...props}/>;
                    break;
                case 'string':
                case 'textarea':
                case 'password':
                    value = <InputText {...props}/>;
                    break;
                case 'valid-password':
                    value = <ValidPassword {...props}/>;
                    break;
                case 'integer':
                    value = <InputInteger {...props}/>;
                    break;
                case 'button':
                    value = <InputButton {...props}/>;
                    break;
                case 'monitor':
                    value = <MonitoringLabel {...props}/>;
                    break;
                case 'image':
                    value = <InputImage {...props}/>;
                    break;
                case 'select':
                    value = <InputSelectBox {...props}/>;
                    break;
                case 'autocomplete':
                    value = <AutocompleteBox {...props}/>;
                    break;
                case 'legend':
                    value = null;
                    break;
                case 'hidden':
                    value = null;
                    break;
                default:
                    if(!props.value){
                        value = <span className="paramValue-empty">Empty</span>;
                    }else{
                        value = props.value;
                    }
                    break;
            }
            return value;
        }

        /**
         *
         * Extract POST-ready values from a combo parameters/values
         *
         * @param definitions Array Standard Form Definition array
         * @param values Object Key/Values of the current form
         * @param prefix String Optional prefix to add to all parameters (by default DRIVER_OPTION_).
         * @returns Object Object with all pydio-compatible POST parameters
         */
        static getValuesForPOST(definitions, values, prefix='DRIVER_OPTION_', additionalMetadata=null){
            var clientParams = {};
            for(var key in values){
                if(values.hasOwnProperty(key)) {
                    clientParams[prefix+key] = values[key];
                    var defType = null;
                    for(var d = 0; d<definitions.length; d++){
                        if(definitions[d]['name'] == key){
                            defType = definitions[d]['type'];
                            break;
                        }
                    }
                    if(!defType){

                        var parts=key.split('/');
                        var last, prev;
                        if(parts.length > 1) {
                            last = parts.pop();
                            prev = parts.pop();
                        }
                        for(var k = 0; k<definitions.length; k++){
                            if(last !== undefined){
                                if(definitions[k]['name'] == last && definitions[k]['group_switch_name'] && definitions[k]['group_switch_name'] == prev){
                                    defType = definitions[k]['type'];
                                    break;
                                }
                            }else{
                                if(definitions[k]['name'] == key) {
                                    defType = definitions[k]['type'];
                                    break;
                                }
                            }
                        }

                    }
                    //definitions.map(function(d){if(d.name == theKey) defType = d.type});
                    if(defType){
                        if(defType == "image") defType = "binary";
                        clientParams[prefix+ key + '_ajxptype'] = defType;
                    }
                    if(additionalMetadata && additionalMetadata[key]){
                        for(var meta in additionalMetadata[key]){
                            if(additionalMetadata[key].hasOwnProperty(meta)){
                                clientParams[prefix + key + '_' + meta] = additionalMetadata[key][meta];
                            }
                        }
                    }
                }
            }

            // Reorder tree keys
            var allKeys = Object.keys(clientParams);
            allKeys.sort();
            allKeys.reverse();
            var treeKeys = {};
            allKeys.map(function(key){
                if(key.indexOf("/") === -1) return;
                if(key.endsWith("_ajxptype")) return;
                var typeKey = key + "_ajxptype";
                var parts = key.split("/");
                var parentName = parts.shift();
                var parentKey;
                while(parts.length > 0){
                    if(!parentKey){
                        parentKey = treeKeys;
                    }
                    if(!parentKey[parentName]) {
                        parentKey[parentName] = {};
                    }
                    parentKey = parentKey[parentName];
                    parentName = parts.shift();
                }
                var type = clientParams[typeKey];
                delete clientParams[typeKey];
                if(parentKey && !parentKey[parentName]) {
                    if(type == "boolean"){
                        var v = clientParams[key];
                        parentKey[parentName] = (v == "true" || v == 1 || v === true );
                    }else if(type == "integer") {
                        parentKey[parentName] = parseInt(clientParams[key]);
                    }else if(type && type.startsWith("group_switch:") && typeof clientParams[key] == "string"){
                        parentKey[parentName] = {group_switch_value:clientParams[key]};
                    }else{
                        parentKey[parentName] = clientParams[key];
                    }
                }else if(parentKey && type && type.startsWith('group_switch:')){
                    parentKey[parentName]["group_switch_value"] = clientParams[key];
                }
                delete clientParams[key];
            });
            for(key in treeKeys){
                if(!treeKeys.hasOwnProperty(key)) continue;
                var treeValue = treeKeys[key];
                if(clientParams[key + '_ajxptype'] && clientParams[key + '_ajxptype'].indexOf('group_switch:') === 0
                    && !treeValue['group_switch_value']){
                    treeValue['group_switch_value'] = clientParams[key];
                }

                clientParams[key] = JSON.stringify(treeValue);
                clientParams[key+'_ajxptype'] = "text/json";

            }

            // Clean XXX_group_switch parameters
            for(var theKey in clientParams){
                if(!clientParams.hasOwnProperty(theKey)) continue;

                if(theKey.indexOf("/") == -1 && clientParams[theKey] && clientParams[theKey + "_ajxptype"] && clientParams[theKey + "_ajxptype"].startsWith("group_switch:")){
                    if(typeof clientParams[theKey] == "string"){
                        clientParams[theKey] = JSON.stringify({group_switch_value:clientParams[theKey]});
                        clientParams[theKey + "_ajxptype"] = "text/json";
                    }
                }
                if(clientParams.hasOwnProperty(theKey)){
                    if(theKey.endsWith("_group_switch")){
                        delete clientParams[theKey];
                    }
                }
            }

            return clientParams;
        }


    }

    var PydioForm = global.PydioForm || {};

    PydioForm.createFormElement = PydioFormManager.createFormelement;
    PydioForm.Manager = PydioFormManager;
    PydioForm.InputText = InputText;
    PydioForm.ValidPassword = ValidPassword;
    PydioForm.InputBoolean = InputBoolean;
    PydioForm.InputInteger = InputInteger;
    PydioForm.InputButton = InputButton;
    PydioForm.MonitoringLabel = MonitoringLabel;
    PydioForm.InputSelectBox = InputSelectBox;
    PydioForm.AutocompleteBox = AutocompleteBox;
    PydioForm.InputImage = InputImage;
    PydioForm.FormPanel = PydioFormPanel;
    PydioForm.PydioHelper = PydioFormHelper;
    PydioForm.HelperMixin = HelperMixin;
    PydioForm.FileDropZone = FileDropzone;

    global.PydioForm = PydioForm;

})(window);
