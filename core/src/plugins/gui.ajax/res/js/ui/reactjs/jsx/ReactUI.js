(function(global){

    class Builder{

        constructor(pydio){
            this._pydio = pydio;
            this.guiLoaded = false;
            this._focusables = [];
            this.modal = window.modal;
            this._componentsRegistry = new Map();
            this.modalSupportsComponents = false;
        }

        insertChildFromString(parent, html){
            let element = document.createElement('div');
            element.innerHTML = html;
            for(let i = 0; i < element.childNodes.length; i++){
                parent.appendChild(element.childNodes[i].cloneNode(true));
            }
        }

        initTemplates(){

            if(!this._pydio.getXmlRegistry()) return;

            const tNodes = XPathSelectNodes(this._pydio.getXmlRegistry(), "client_configs/template");
            for(let i=0;i<tNodes.length;i++){

                let target = tNodes[i].getAttribute("element");
                let themeSpecific = tNodes[i].getAttribute("theme");
                if(themeSpecific && this._pydio.Parameters.get("theme") && this._pydio.Parameters.get("theme") != themeSpecific){
                    continue;
                }
                let targetObj = document.getElementById(target);
                if(!targetObj){
                    let tags = document.getElementsByTagName(target);
                    if(tags.length) targetObj = tags[0];
                }
                if(targetObj){
                    let position = tNodes[i].getAttribute("position");
                    const HTMLString = this.updateHrefBase(tNodes[i].firstChild.nodeValue);
                    this.insertChildFromString(targetObj, HTMLString);
                    //obj[position].evalScripts();
                }
            }
            this.guiLoaded = true;
            this._pydio.notify("gui_loaded");

        }

        refreshTemplateParts(){

            let componentsToMount = new Map();
            let parts = XPathSelectNodes(this._pydio.getXmlRegistry(), "client_configs/template_part[@component]");
            parts.map(function(node){
                if(node.getAttribute("theme") && node.getAttribute("theme") != this._pydio.Parameters.get("theme")){
                    return;
                }
                let pydioId = node.getAttribute("ajxpId");
                let element = document.getElementById(pydioId);
                let namespace = node.getAttribute("namespace");
                let componentName = node.getAttribute("component");
                if(!element){
                    return;
                }
                let namespacesToLoad = [namespace];
                if(node.getAttribute("dependencies")){
                    node.getAttribute("dependencies").split(",").forEach(function(d){
                        namespacesToLoad.push(d);
                    });
                }
                let props = {};
                if(node.getAttribute("props")){
                    props = JSON.parse(node.getAttribute("props"));
                }
                props['pydio']      = this._pydio;
                props['pydioId']    = pydioId;

                componentsToMount.set(pydioId, {
                    namespace:namespace,
                    componentName:componentName,
                    namespacesToLoad:namespacesToLoad,
                    props:props
                });

            }.bind(this));

            let componentsToRegister = new Map();

            // Now compare current and new
            this._componentsRegistry.forEach(function(existing, existingId){

                let element = document.getElementById(existingId);
                if(componentsToMount.has(existingId)){
                    let registered = existing;
                    let toMount = componentsToMount.get(existingId);

                    if(registered.namespace === toMount.namespace && registered.componentName === toMount.componentName){
                        // Do not unmount / remount, just refresh
                        element.__REACT_COMPONENT.forceUpdate();
                    }else{
                        ReactDOM.unmountComponentAtNode(document.getElementById(existingId));
                        this._componentsRegistry.delete(existingId);
                        element.__REACT_COMPONENT = null;
                        ResourcesManager.loadClassesAndApply(toMount.namespacesToLoad, function(){
                            element.__REACT_COMPONENT = ReactDOM.render(
                                React.createElement(window[toMount.namespace][toMount.componentName], toMount.props),
                                element
                            );
                            componentsToRegister.set(existingId, toMount);
                        }.bind(this));
                    }
                }else {
                    ReactDOM.unmountComponentAtNode(element);
                    element.__REACT_COMPONENT = null;
                    this._componentsRegistry.delete(existingId);
                }
                componentsToMount.delete(existingId);

            }.bind(this));

            componentsToRegister.forEach(function(c, k){
                this._componentsRegistry.set(k, c);
            }.bind(this));

            componentsToMount.forEach(function(toMount, newId){

                let element = document.getElementById(newId);
                ResourcesManager.loadClassesAndApply(toMount.namespacesToLoad, function(){
                    element.__REACT_COMPONENT = ReactDOM.render(
                        React.createElement(window[toMount.namespace][toMount.componentName], toMount.props),
                        element
                    );
                    this._componentsRegistry.set(newId, toMount);
                }.bind(this));

            }.bind(this));


        }

        updateHrefBase(cdataContent){
            return cdataContent;
            /*
            if(Prototype.Browser.IE10){
                var base = document.getElementsByTagName('base')[0].getAttribute("href");
                if(!base){
                    return cdataContent;
                }
                return cdataContent.replace('data-hrefRebase="', 'href="'+base);
            }
            */
        }

        /**
         *
         * @param component
         */
        registerEditorOpener(component){
            this._editorOpener = component;
        }

        unregisterEditorOpener(component){
            if(this._editorOpener === component) {
                this._editorOpener = null;
            }
        }

        getEditorOpener(){
            return this._editorOpener;
        }

        openCurrentSelectionInEditor(editorData, forceNode){
            var selectedNode =  forceNode ? forceNode : this._pydio.getContextHolder().getUniqueNode();
            if(!selectedNode) return;
            if(!editorData){
                var selectedMime = getAjxpMimeType(selectedNode);
                var editors = this._pydio.Registry.findEditorsForMime(selectedMime, false);
                if(editors.length && editors[0].openable && !(editors[0].write && selectedNode.getMetadata().get("ajxp_readonly") === "true")){
                    editorData = editors[0];
                }
            }
            if(editorData){
                this._pydio.Registry.loadEditorResources(editorData.resourcesManager);
                var editorOpener = this.getEditorOpener();
                if(!editorOpener || editorData.modalOnly){
                    modal.openEditorDialog(editorData);
                }else{
                    editorOpener.openEditorForNode(selectedNode, editorData);
                }
            }else{
                if(this._pydio.Controller.getActionByName("download")){
                    this._pydio.Controller.getActionByName("download").apply();
                }
            }
        }

        registerModalOpener(component){
            this._modalOpener = component;
            this.modalSupportsComponents = true;
        }

        unregisterModalOpener(){
            this._modalOpener = null;
            this.modalSupportsComponents = false;
        }

        openComponentInModal(namespace, componentName, props){
            this._modalOpener.open(namespace, componentName, props);
        }

        /**
         * PROXY TO PROTOTYPE UI
         * @param seedInputField
         * @param existingCaptcha
         * @param captchaAnchor
         * @param captchaPosition
         * @returns {*}
         */
        loadSeedOrCaptcha(seedInputField, existingCaptcha, captchaAnchor, captchaPosition){
            let p = new PydioUI(this._pydio);
            return p.loadSeedOrCaptcha(seedInputField, existingCaptcha, captchaAnchor, captchaPosition);
        }
        initObjects(){}
        updateI18nTags(){}
        insertForm(formId, formCode){
            let formsContainer = document.getElementById("all_forms");
            if(!formsContainer.querySelector('#' + formId)){
                this.insertChildFromString(formsContainer, formCode);
            }
        }
        removeForm(formId){
            let formsContainer = document.getElementById("all_forms");
            let child = formsContainer.querySelector('#' + formId);
            if(child){
                formsContainer.removeChild(child);
            }
        }
        mountComponents(componentsNodes){}

        disableShortcuts(){}
        disableNavigation(){}
        enableShortcuts(){}
        enableNavigation(){}
        disableAllKeyBindings(){}
        enableAllKeyBindings(){}

        blurAll(){}
        focusOn(){}
        focusLast(){}
        
    }

    let ns = global.ReactUI || {};
    ns.Builder = Builder;
    global.ReactUI = ns;

})(window);