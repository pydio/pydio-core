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

/**
 * Bind to the BackgroundTasksManager to display its state.
 */
Class.create("BackgroundManagerPane", {

	/**
	 * Constructor
	 * @param actionManager ActionManager
	 */
	initialize:function(actionManager){
        if(!actionManager){
            actionManager = pydio.getController();
        }
        this.bgManager = actionManager.getBackgroundTasksManager();
        if(!this.bgManager) return;

        this.insertPanel();

        this.bgManager.observe("update_message", this.updatePanelMessage.bind(this));
        this.bgManager.observe("update_message_error", this.updatePanelError.bind(this));
        this.bgManager.observe("tasks_finished", this.hidePanel.bind(this));
	},

    insertPanel: function(){
        this.panel = new Element('div').addClassName('backgroundPanel');
        if(Prototype.Browser.IE){
            this.panel.setStyle({width:'35%'});
        }
        this.panel.hide();
        document.body.insert(this.panel);
    },

    updatePanelMessage : function(message){
        var imgString = '<img src="'+ajxpResourcesFolder+'/images/loadingImage.gif" width="16" align="absmiddle">';
        this.panel.update(imgString+' '+message);
        Effect.Appear(this.panel);
    },

	/**
	 * Interrupt the task on error
	 * @param errorMessage String
	 */
	updatePanelError:function(errorMessage){
		this.panel.update(errorMessage);
		this.panel.insert(this.makeCloseLink());
	},
	/**
	 * All tasks are processed
	 */
	hidePanel:function(){
        Effect.SwitchOff(this.panel);
	},
	
	/**
	 * Create a "Close" link
	 */
	makeCloseLink:function(){
		return new Element('a', {href:'#'}).update('Close').observe('click', function(e){
			Event.stop(e);
            this.hidePanel();
		}.bind(this));
	}
});