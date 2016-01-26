/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
Class.create("PydioUI", {

    __ajxpClassRegexp: new RegExp(/ajxpClass="([0-9a-zA-Z]+)"/g),

    initialize: function(pydioObject){

        this._pydio = pydioObject;
        this._guiCompRegistry = $A();
        this._guiComponentsConfigs = $H();
        this._focusables = [];
        this.blockShortcuts = false;
        this.blockNavigation = false;
        this.blockEditorShortcuts = false;

        this._editorOpener = null;
        this._messageBoxReference = null;

        this._instancesCache = $H();
        this.modal = window.modal;
    },


    removeInstanceFromCache: function(instanceId){
        this._instancesCache.unset(instanceId);
    },
    /**
     *
     * @param passedTarget
     */
    registerEditorOpener: function(ajxpWidget){
        this._editorOpener = ajxpWidget;
        this._editorObserver = function(tabId){
            var tabData = ajxpWidget.tabulatorData.detect(function(tabInfo){return tabInfo.id == tabId});
            if(tabData && tabData.ajxpNode){
                this._pydio.getContextHolder().setSelectedNodes([tabData.ajxpNode]);
            }
        }.bind(this);
        ajxpWidget.observe("switch", this._editorObserver);
    },
    unregisterEditorOpener: function(ajxpWidget){
        if(this._editorOpener == ajxpWidget) {
            this._editorOpener.stopObserving("switch", this._editorObserver);
            this._editorOpener = null;
        }
    },
    getEditorOpener:function(){
        return this._editorOpener;
    },

    openCurrentSelectionInEditor:function(editorData, forceNode){
        var selectedNode =  forceNode ? forceNode : this._pydio.getContextHolder().getUniqueNode();
        if(!selectedNode) return;
        if(!editorData){
            var selectedMime = getAjxpMimeType(selectedNode);
            var editors = this._pydio.Registry.findEditorsForMime(selectedMime, false);
            if(editors.length && editors[0].openable){
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
    },

    registerAsMessageBoxReference: function(element){
        this._messageBoxReference = element;
    },
    clearMessageBoxReference:function(){
        this._messageBoxReference = null;
    },
    getMessageBoxReference: function(){
        return $(this._messageBoxReference);
    },

    insertForm:function(formId, formCode){
        if($('all_forms').down('#'+formId)) return;
        $('all_forms').insert(formCode);
    },

    removeForm: function(formId){
        var existing = $('all_forms').down('#'+formId);
        if(existing) existing.remove();
    },

    mountComponents: function(componentsNodes){
        $A(componentsNodes).each(function(node){
            if(node.getAttribute('type') == 'react' && React){
                var namespace = node.getAttribute('namespace');
                var compName= node.getAttribute('name');
                var hookElement = $(node.getAttribute('element'));
                var unMountFunc = null;
                if(!hookElement){
                    hookElement = new Element('div', {id:node.getAttribute('element')});
                    $(document.body).insert(hookElement);
                    // TODO: ADD THIS AS A MIXIN?
                    unMountFunc = function(){hookElement.remove();};
                }
                if(namespace){
                    ResourcesManager.loadClassesAndApply([namespace], function(){
                        React.render(
                            React.createElement(window[namespace][compName], {}),
                            hookElement
                        )
                    });
                }else{
                    ResourcesManager.loadClassesAndApply([compName], function(){
                        React.render(
                            React.createElement(window[compName], {}),
                            hookElement
                        )
                    });
                }
            }
        });
    },

    /**
     * Initialize GUI Objects
     */
    initObjects: function(){

        /*********************
         /* STANDARD MECHANISMS
         /*********************/
        this.contextMenu = new Proto.Menu({
            selector: '', // context menu will be shown when element with class name of "contextmenu" is clicked
            className: 'menu desktop', // this is a class which will be attached to menu container (used for css styling)
            menuItems: [],
            fade:false,
            zIndex:2000
        });
        var protoMenu = this.contextMenu;
        var pydioObject = this._pydio;

        protoMenu.options.beforeShow = function(e){
            var element = Event.element(e);
            if(element.hasClassName('ajxpNodeProvider') || element.up('.ajxpNodeProvider') || element.hasClassName('selected-webfx-tree-item') || element.up('.selected-webfx-tree-item')){
                this.options.currentActionsContext = 'selectionContext';
            }else{
                this.options.currentActionsContext = 'genericContext';
            }
            this.options.menuItems = pydioObject.getController().getContextActions(this.options.currentActionsContext, ["inline"]);
            this.refreshList();
        }.bind(protoMenu);
        protoMenu.options.beforeHide = function(e){
            this.options.currentActionsContext = null;
        }.bind(protoMenu);
        document.observe("ajaxplorer:actions_refreshed", function(){
            if(this.options.currentActionsContext){
                this.options.menuItems = pydioObject.getController().getContextActions(this.options.currentActionsContext, ["inline"]);
                this.refreshList();
            }
        }.bind(protoMenu));

        if(pydioObject.getXmlRegistry()){
            pydioObject.Controller.loadActionsFromRegistry(pydioObject.getXmlRegistry());
        }
        pydioObject.observe("registry_loaded", function(registry){
            if(Prototype.Browser.IE) ResourcesManager.loadAutoLoadResources(registry);
            pydioObject.Controller.loadActionsFromRegistry(registry);
        }.bind(this) );

        document.observe("ajaxplorer:context_changed", function(event){
            var path = this.getContextNode().getPath();
            var appTitle = pydioObject.Parameters.get("customWording").title || "Pydio";
            document.title = appTitle + ' - '+(getBaseName(path)?getBaseName(path):'/');

            // Auto Save
            if(pydioObject.skipLsHistory || !pydioObject.user || !pydioObject.user.getActiveRepository()
                || pydio.getPluginConfigs("core.conf").get("SKIP_USER_HISTORY") === true) {
                return;
            }
            window.setTimeout(function(){
                var data = pydioObject.user.getPreference("ls_history", true) || {};
                data = new Hash(data);
                data.set(pydioObject.user.getActiveRepository(), pydioObject.getContextNode().getPath());
                pydioObject.user.setPreference("ls_history", data, true);
                pydioObject.user.savePreference("ls_history");
            }.bind(this), 200 );


        }.bind(pydioObject) );
        modal.updateLoadingProgress('Actions Initialized');

        this.activityMonitor = new ActivityMonitor(
            window.ajxpBootstrap.parameters.get('session_timeout'),
            window.ajxpBootstrap.parameters.get('client_timeout'),
            window.ajxpBootstrap.parameters.get('client_timeout_warning'));

        /*********************
         /* USER GUI
         /*********************/
        this.guiLoaded = false;
        var mainElement = $(ajxpBootstrap.parameters.get('MAIN_ELEMENT'));
        var classNames = $H();
        mainElement.select('[ajxpClass]').each(function(el){
            classNames.set(el.readAttribute('ajxpClass'), true);
        });
        classNames = classNames.keys();

        ResourcesManager.loadClassesAndApply(classNames, function(){
            this.buildGUI(mainElement);
            document.fire("ajaxplorer:before_gui_load");
            // Rewind components creation!
            if(this._guiCompRegistry){
                this.initAjxpWidgets(this._guiCompRegistry);
            }
            this.guiLoaded = true;
            document.fire("ajaxplorer:gui_loaded");
            document.observe("ajaxplorer:repository_list_refreshed", function(e){
                mainElement.classNames().findAll(function(c){ return c.startsWith('ajxp_ws-'); }).each(function(cName){
                    mainElement.removeClassName(cName);
                });
                if(e.memo.active && e.memo.list){
                    mainElement.addClassName('ajxp_ws-' + e.memo.list.get(e.memo.active).getSlug());
                }
            });
            modal.updateLoadingProgress('GUI Initialized');
            this.initTabNavigation();
            this.blockShortcuts = false;
            this.blockNavigation = false;
            // TODO : ADD TO XML TEMPLATES INSTEAD
            this.bgManagerPane = new BackgroundManagerPane();
            modal.updateLoadingProgress('Navigation loaded');
        }.bind(this));


    },

    initAjxpWidgets : function(compRegistry){
        var lastInst;
        if(compRegistry.length){
            for(var i=compRegistry.length;i>0;i--){
                var el = compRegistry[i-1];
                var ajxpId = el.ajxpId;
                compRegistry[i-1] = new el['ajxpClass'](el.ajxpNode, el.ajxpOptions);
                this._instancesCache.set(ajxpId, compRegistry[i-1]);
                lastInst = compRegistry[i-1];
            }
            if(lastInst){
                lastInst.resize();
            }
            for(var j=0;j<compRegistry.length;j++){
                var obj = compRegistry[j];
                if(Class.objectImplements(obj, "IFocusable")){
                    obj.setFocusBehaviour();
                    this.registerFocusable(obj);
                }
                if(Class.objectImplements(obj, "IContextMenuable")){
                    obj.setContextualMenu(this.contextMenu);
                }
                if(Class.objectImplements(obj, "IActionProvider")){
                    this._pydio.Controller.updateGuiActions(ProtoCompat.hash2map(obj.getActions()));
                }
            }
        }
    },

    /**
     * Builds the GUI based on the XML definition (template)
     * @param domNode
     * @param compRegistry
     */
    buildGUI : function(domNode, compRegistry){
        if(domNode.nodeType != 1) return;
        if(!this._guiCompRegistry) this._guiCompRegistry = $A([]);
        if(!compRegistry){
            compRegistry = this._guiCompRegistry;
        }
        domNode = $(domNode);
        var ajxpClassName = domNode.readAttribute("ajxpClass") || "";
        var ajxpClass = Class.getByName(ajxpClassName);
        var ajxpId = domNode.readAttribute("id") || "";
        var ajxpOptions = {};
        if(domNode.readAttribute("ajxpOptions")){
            try{
                ajxpOptions = domNode.readAttribute("ajxpOptions").evalJSON();
            }catch(e){
                alert("Error while parsing JSON for GUI template part " + ajxpId + "!");
            }
        }
        if(ajxpClass && ajxpId && Class.objectImplements(ajxpClass, "IAjxpWidget")){
            compRegistry.push({ajxpId:ajxpId, ajxpNode:domNode, ajxpClass:ajxpClass, ajxpOptions:ajxpOptions});
        }
        $A(domNode.childNodes).each(function(node){
            this.buildGUI(node, compRegistry);
        }.bind(this) );
    },

    findAjxpClassesInText: function(cdataContent){
        var match = this.__ajxpClassRegexp.exec(cdataContent);
        var requiredClasses = $H();
        while(match !=null){
            requiredClasses.set(match[1], true);
            match = this.__ajxpClassRegexp.exec(cdataContent);
        }
        return requiredClasses;
    },

    updateHrefBase: function(cdataContent){
        if(!Prototype.Browser.IE10){
            return cdataContent;
        }
        var base = $$('base')[0].getAttribute("href");
        if(!base){
            return cdataContent;
        }
        return cdataContent.replace('data-hrefRebase="', 'href="'+base);
    },

    /**
     * Parses a client_configs/template_part node
     */
    refreshTemplateParts : function(){
        var parts = XPathSelectNodes(this._pydio.getXmlRegistry(), "client_configs/template_part");
        var toUpdate = {};
        var restoreUpdate = {};

        var requiredClasses = $H();
        var requiredComponents = $H();

        if(!this.templatePartsToRestore){
            this.templatePartsToRestore = $A();
        }
        for(var i=0;i<parts.length;i++){
            if(parts[i].getAttribute("theme") && parts[i].getAttribute("theme") != ajxpBootstrap.parameters.get("theme")){
                continue;
            }
            var ajxpId = parts[i].getAttribute("ajxpId");
            var ajxpClassName = parts[i].getAttribute("ajxpClass");
            var ajxpOptionsString = parts[i].getAttribute("ajxpOptions");
            if(parts[i].getAttribute("components")){
                parts[i].getAttribute("components").split(",").forEach(function(v){
                    requiredComponents.set(v, true);
                });
            }
            var cdataContent = "";
            if(parts[i].firstChild && parts[i].firstChild.nodeType == 4 && parts[i].firstChild.nodeValue != ""){
                cdataContent = parts[i].firstChild.nodeValue;
                cdataContent = this.updateHrefBase(cdataContent);
            }
            if(ajxpId){
                toUpdate[ajxpId] = [ajxpClassName, ajxpOptionsString, cdataContent];
                requiredClasses = requiredClasses.merge(this.findAjxpClassesInText(cdataContent));
            }
        }
        this.templatePartsToRestore.each(function(key){
            var part = this.findOriginalTemplatePart(key);
            if(part){
                var ajxpClassName = part.getAttribute("ajxpClass");
                var ajxpOptionsString = part.getAttribute("ajxpOptions");
                var cdataContent = part.innerHTML;
                restoreUpdate[key] = [ajxpClassName, ajxpOptionsString, cdataContent];
            }
        }.bind(this));

        var callback = function(){
            var futurePartsToRestore = $A();// $A(Object.keys(toUpdate));
            for(var id in restoreUpdate){
                this.refreshGuiComponent(id, restoreUpdate[id][0], restoreUpdate[id][1], restoreUpdate[id][2]);
            }
            for(id in toUpdate){
                var ajxpClass = Class.getByName(toUpdate[id][0]);
                if(ajxpClass && Class.objectImplements(ajxpClass, "IAjxpWidget")){
                    this.refreshGuiComponent(id, toUpdate[id][0], toUpdate[id][1], toUpdate[id][2]);
                    futurePartsToRestore.push(id);
                }
            }
            this.templatePartsToRestore = futurePartsToRestore;
            this.refreshGuiComponentConfigs();
        }.bind(this);

        if(requiredComponents.keys().size()){
            ResourcesManager.loadWebComponentsAndApply(requiredComponents.keys(), callback);
        }else{
            ResourcesManager.loadClassesAndApply(requiredClasses.keys(), callback);
        }

    },

    /**
     * Applies a template_part by removing existing components at this location
     * and recreating new ones.
     * @param ajxpId String The id of the DOM anchor
     * @param ajxpClass IAjxpWidget A widget class
     * @param ajxpClassName
     * @param ajxpOptionsString
     * @param cdataContent
     */
    refreshGuiComponent:function(ajxpId, ajxpClassName, ajxpOptionsString, cdataContent){
        if(!this._instancesCache.get(ajxpId)) return;

        // First destroy current component, unregister actions, etc.
        var oldObj = this._instancesCache.get(ajxpId);
        if(!oldObj.__className) {
            if(!$(ajxpId)) return;
            oldObj = $(ajxpId).ajxpPaneObject;
        }
        if(!oldObj){
            alert('Cannot find GUI component ' + ajxpId + ' to be refreshed!');
            return;
        }
        if(oldObj.__className == ajxpClassName && oldObj.__ajxpOptionsString == ajxpOptionsString){
            return;
        }
        var ajxpOptions = {};
        if(ajxpOptionsString){
            ajxpOptions = ajxpOptionsString.evalJSON();
        }

        if(oldObj.htmlElement) var anchor = oldObj.htmlElement;
        oldObj.destroy();

        if(cdataContent && anchor){
            anchor.insert(cdataContent);
            var compReg = $A();
            $A(anchor.children).each(function(el){
                this.buildGUI(el, compReg);
            }.bind(this));
            if(compReg.length) this.initAjxpWidgets(compReg);
        }
        var ajxpClass = Class.getByName(ajxpClassName);
        var obj = new ajxpClass($(ajxpId), ajxpOptions);

        if(Class.objectImplements(obj, "IFocusable")){
            obj.setFocusBehaviour();
            this.registerFocusable(obj);
        }
        if(Class.objectImplements(obj, "IContextMenuable")){
            obj.setContextualMenu(this.contextMenu);
        }
        if(Class.objectImplements(obj, "IActionProvider")){
            this._pydio.Controller.updateGuiActions(ProtoCompat.hash2map(obj.getActions()));
        }

        if($(ajxpId).up('[ajxpClass]') && $(ajxpId).up('[ajxpClass]').ajxpPaneObject && $(ajxpId).up('[ajxpClass]').ajxpPaneObject.scanChildrenPanes){
            $(ajxpId).up('[ajxpClass]').ajxpPaneObject.scanChildrenPanes($(ajxpId).up('[ajxpClass]').ajxpPaneObject.htmlElement, true);
        }

        obj.__ajxpOptionsString = ajxpOptionsString;

        this._instancesCache.unset(oldObj);
        this._instancesCache.set(ajxpId, obj);
        obj.resize();
        //delete(oldObj);
    },

    /**
     * Spreads a client_configs/component_config to all gui components.
     * It will be the mission of each component to check whether its for him or not.
     */
    refreshGuiComponentConfigs : function(){
        this._guiComponentsConfigs = $H();
        var nodes = XPathSelectNodes(this._pydio.getXmlRegistry(), "client_configs/component_config");
        if(!nodes.length) return;
        for(var i=0;i<nodes.length;i++){
            try{
                this.setGuiComponentConfig(nodes[i]);
            }catch(e){
                if(window.console) console.error("Error while setting ComponentConfig", e);
            }
        }
        var element = $(window.ajxpBootstrap.parameters.get("MAIN_ELEMENT"));
        if(element && element.ajxpPaneObject &&  element.ajxpPaneObject.resize){
            window.setTimeout(function(){
                // Fire top resize event once after all css are loaded.
                element.ajxpPaneObject.resize();
            }, 500);
        }
    },

    /**
     * Apply the componentConfig to the AjxpObject of a node
     * @param domNode IAjxpWidget
     */
    setGuiComponentConfig : function(domNode){
        var className = domNode.getAttribute("className");
        var classId = domNode.getAttribute("classId") || null;
        var classConfig = new Hash();
        if(classId){
            classConfig.set(classId, domNode);
        }else{
            classConfig.set('all', domNode);
        }
        var cumul = this._guiComponentsConfigs.get(className);
        if(!cumul) cumul = $A();
        cumul.push(classConfig);
        this._guiComponentsConfigs.set(className, cumul);
        document.fire("ajaxplorer:component_config_changed", {className:className, classConfig:classConfig});
    },

    getGuiComponentConfigs : function(className){
        return this._guiComponentsConfigs.get(className);
    },

    /**
     * Inserts the main template in the GUI.
     */
    initTemplates:function(passedTarget, mainElementName){
        if(!this._pydio.getXmlRegistry()) return;
        var tNodes = XPathSelectNodes(this._pydio.getXmlRegistry(), "client_configs/template");
        for(var i=0;i<tNodes.length;i++){
            var target = tNodes[i].getAttribute("element");
            var themeSpecific = tNodes[i].getAttribute("theme");
            if(themeSpecific && window.ajxpBootstrap.parameters.get("theme") && window.ajxpBootstrap.parameters.get("theme") != themeSpecific){
                continue;
            }
            if(mainElementName && target != mainElementName){
                continue;
            }
            if($(target) || $$(target).length || passedTarget){
                if($(target)) target = $(target);
                else target = $$(target)[0];
                if(passedTarget) target = passedTarget;
                var position = tNodes[i].getAttribute("position");
                var obj = {}; obj[position] = this.updateHrefBase(tNodes[i].firstChild.nodeValue);
                target.insert(obj);
                obj[position].evalScripts();
            }
        }
        modal.updateLoadingProgress('Html templates loaded');
    },

    findOriginalTemplatePart : function(ajxpId){
        var tmpElement = new Element("div", {style:"display:none;"});
        $$("body")[0].insert(tmpElement);
        this.initTemplates(tmpElement, window.ajxpBootstrap.parameters.get("MAIN_ELEMENT"));
        var tPart = tmpElement.down('[id="'+ajxpId+'"]');
        if(tPart) tPart = tPart.clone(true);
        tmpElement.remove();
        return tPart;
    },



    getRegisteredComponentsByClassName: function(className){
        return this._guiCompRegistry.select(function(guiComponent){
            return (guiComponent.__className == className);
        });
    },

    /**
     * Search all ajxp_message_id tags and update their value
     */
    updateI18nTags: function(){
        var messageTags = $$('[ajxp_message_id]');
        messageTags.each(function(tag){
            var messageId = tag.getAttribute("ajxp_message_id");
            try{
                tag.update(this._pydio.MessageHash[messageId]);
            }catch(e){}
        }.bind(this));
    },

    /**
     * Trigger a captcha image
     * @param seedInputField HTMLInput The seed value
     * @param existingCaptcha HTMLImage An image (optional)
     * @param captchaAnchor HTMLElement Where to insert the image if created.
     * @param captchaPosition String Position.insert() possible key.
     */
    loadSeedOrCaptcha : function(seedInputField, existingCaptcha, captchaAnchor, captchaPosition){
        var connexion = new Connexion();
        connexion.addParameter("get_action", "get_seed");
        connexion.onComplete = function(transport){
            if(transport.responseJSON){
                seedInputField.value = transport.responseJSON.seed;
                var src = window.ajxpServerAccessPath + '&get_action=get_captcha&sid='+Math.random();
                var refreshSrc = ajxpResourcesFolder + '/images/actions/16/reload.png';
                if(existingCaptcha){
                    existingCaptcha.src = src;
                }else{
                    var insert = {};
                    var string = '<div class="main_captcha_div" style="padding-top: 4px;"><div class="dialogLegend" ajxp_message_id="389">'+MessageHash[389]+'</div>';
                    string += '<div class="captcha_container"><img id="captcha_image" align="top" src="'+src+'" width="170" height="80"><img align="top" style="cursor:pointer;" id="captcha_refresh" src="'+refreshSrc+'" with="16" height="16"></div>';
                    string += '<div class="SF_element">';
                    string += '		<div class="SF_label" ajxp_message_id="390">'+MessageHash[390]+'</div> <div class="SF_input"><input type="text" class="dialogFocus dialogEnterKey" style="width: 100px; padding: 0px;" name="captcha_code"></div>';
                    string += '</div>';
                    string += '<div style="clear:left;margin-bottom:7px;"></div></div>';
                    insert[captchaPosition] = string;
                    captchaAnchor.insert(insert);
                    modal.refreshDialogPosition();
                    modal.refreshDialogAppearance();
                    $('captcha_refresh').observe('click', function(){
                        $('captcha_image').src = window.ajxpServerAccessPath + '&get_action=get_captcha&sid='+Math.random();
                    });
                }
            }else{
                seedInputField.value = transport.responseText;
                if(existingCaptcha){
                    existingCaptcha.up('.main_captcha_div').remove();
                    modal.refreshDialogPosition();
                    modal.refreshDialogAppearance();
                }
            }
        };
        connexion.sendAsync();
    },


    /**
     * Focuses on a given widget
     * @param object IAjxpFocusable
     */
    focusOn : function(object){
        if(!this._focusables || this._focusables.indexOf(object) == -1) {
            return;
        }
        this._focusables.each(function(obj){
            if(obj != object) obj.blur();
        });
        object.focus();
    },

    /**
     * Blur all widgets
     */
    blurAll : function(){
        this._focusables.each(function(f){
            if(f.hasFocus) this._lastFocused = f;
            f.blur();
        }.bind(this) );
    },

    /**
     * @param widget IAjxpFocusable
     */
    registerFocusable: function(widget){
        if(-1 == this._focusables.indexOf(widget) && widget.htmlElement){
            this._focusables.push(widget);
        }
    },

    /**
     * @param widget IAjxpFocusable
     */
    unregisterFocusable: function(widget){
        this._focusables = this._focusables.without(widget);
    },

    /**
     * Find last focused IAjxpFocusable and focus it!
     */
    focusLast : function(){
        if(this._lastFocused) this.focusOn(this._lastFocused);
    },

    /**
     * Create a Tab navigation between registerd IAjxpFocusable
     */
    initTabNavigation: function(){
        // ASSIGN OBSERVER
        Event.observe(document, "keydown", function(e)
        {
            if(e.keyCode == Event.KEY_TAB)
            {
                if(this.blockNavigation) return;
                var objects = [];
                $A(this._focusables).each(function(el){
                    if(el.htmlElement && el.htmlElement.visible()){
                        objects.push(el);
                    }
                });
                var shiftKey = e['shiftKey'];
                var foundFocus = false;
                for(var i=0; i<objects.length;i++)
                {
                    if(objects[i].hasFocus)
                    {
                        objects[i].blur();
                        var nextIndex;
                        if(shiftKey)
                        {
                            if(i>0) nextIndex=i-1;
                            else nextIndex = (objects.length) - 1;
                        }
                        else
                        {
                            if(i<objects.length-1)nextIndex=i+1;
                            else nextIndex = 0;
                        }
                        objects[nextIndex].focus();
                        foundFocus = true;
                        break;
                    }
                }
                if(!foundFocus && objects[0]){
                    this.focusOn(objects[0]);
                }
                Event.stop(e);
            }
            if(this.blockShortcuts || e['ctrlKey'] || e['metaKey']) return;
            if (!(e.keyCode != Event.KEY_DELETE && ( e.keyCode > 90 || e.keyCode < 65 ))) {
                this._pydio.Controller.fireActionByKey(e, (e.keyCode == Event.KEY_DELETE ? "key_delete" : String.fromCharCode(e.keyCode).toLowerCase()));
            }
        }.bind(this));
    },


    /**
     * Blocks all access keys
     */
    disableShortcuts: function(){
        this.blockShortcuts = true;
    },

    /**
     * Unblocks all access keys
     */
    enableShortcuts: function(){
        this.blockShortcuts = false;
    },

    /**
     * blocks all tab keys
     */
    disableNavigation: function(){
        this.blockNavigation = true;
    },

    /**
     * Unblocks all tab keys
     */
    enableNavigation: function(){
        this.blockNavigation = false;
    },

    disableAllKeyBindings : function(){
        this.blockNavigation = this.blockShortcuts = this.blockEditorShortcuts = true;
    },

    enableAllKeyBindings : function(){
        this.blockNavigation = this.blockShortcuts = this.blockEditorShortcuts = false;
    }

});