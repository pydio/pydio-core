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
 * A monitor for user "idle" state to prevent session timing out.
 */
Class.create("ActivityMonitor", {
	
	_serverSessionTime:0,
	_warningTime:0,
	_logoutTime:0,
	/* Minutes before end of session */
	_warningMinutes:3,
	_renewMinutes:10,
	_logoutMinutes:0,
	_lastActive:0,	
	_state:'active',
    _longTaskRunning : false,
	
	/**
	 * Constructor
	 * @param serverSessionTime Integer The server session timeout
	 * @param clientSessionTime Integer The client session timeout
	 * @param warningMinutes Integer The number of minutes before timeout where the user is warned.
	 */
	initialize : function(serverSessionTime, clientSessionTime, warningMinutes){
		if(!serverSessionTime) return;
        if(serverSessionTime <= 60*this._renewMinutes){
            this._renewMinutes = 2;
        }
		if(clientSessionTime == -1){
			this._renewTime = serverSessionTime - this._renewMinutes*60;
			this.serverInterval = window.setInterval(this.serverObserver.bind(this), Math.min((Math.pow(2,31)-1), this._renewTime*1000));
			return;
		}
		this._serverSessionTime = serverSessionTime;
		if(warningMinutes) {
            this._warningMinutes = warningMinutes;
            this._warningTime = clientSessionTime - this._warningMinutes*60;
        }else{
            this._warningTime = false
        }
		this._logoutTime = clientSessionTime - this._logoutMinutes*60;
		this._renewTime = serverSessionTime - this._renewMinutes*60;
		this._lastActive = this.getNow();
		var activityObserver = this.activityObserver.bind(this);
		document.observe("ajaxplorer:user_logged", function(){
			// Be sure not to multiply the setInterval
			this._lastActive = this.getNow();
			if(this.interval) window.clearInterval(this.interval);
			if(this.serverInterval) window.clearInterval(this.serverInterval);
			$(document.body).stopObserving("keypress", activityObserver);
			$(document.body).stopObserving("mouseover", activityObserver);
			$(document.body).stopObserving("mousemove", activityObserver);
			document.stopObserving("ajaxplorer:server_answer", activityObserver);
			this._state = 'inactive';
			if(ajaxplorer.user) {
				this._state = 'active';
				$(document.body).observe("keypress", activityObserver );
				$(document.body).observe("mouseover", activityObserver );
				$(document.body).observe("mousemove", activityObserver );
				document.observe("ajaxplorer:server_answer", activityObserver );
				this.interval = window.setInterval(this.idleObserver.bind(this), 5000);
				this.serverInterval = window.setInterval(this.serverObserver.bind(this), Math.min((Math.pow(2,31)-1), this._renewTime*1000));
			}
		}.bind(this));
        document.observe("ajaxplorer:longtask_starting", function(){
            this._longTaskRunning = true;
        }.bind(this));
        document.observe("ajaxplorer:longtask_finished", function(){
            this._longTaskRunning = false;
        }.bind(this));
	},
	/**
	 * Listener to clear the timer 
	 */
	activityObserver : function(event){
        if(event && event.memo && event.memo.discrete){
            return;
        }
		if(this._state == 'warning') return;
		if(this.timer){
			window.clearTimeout(this.timer);
		}
		this.timer = window.setTimeout(this.activityUpdater.bind(this), 1000);
	},


	/**
	 * Set last activity time
	 */
	activityUpdater : function(){
		//console.log('activity!');
		this._lastActive = this.getNow();
	},
	
	/**
	 * Pings the server
	 */
	serverObserver : function(){
		new Ajax.Request(window.ajxpServerAccessPath, 
		{
			method:'get',
			parameters:{get_action:'ping'}
		});		
	},
	
	/**
	 * Listener for "idle" state of the user
	 */
	idleObserver : function(){
		if(this._state == 'inactive') return;
        if(this._longTaskRunning){
            this.activityUpdater();
            return;
        }
		var idleTime = (this.getNow() - this._lastActive);
		if( idleTime >= this._logoutTime ){
			//console.log('firing logout');
			this.removeWarningState();
			this._state = 'active';
			if(this.interval) window.clearInterval(this.interval);
			if(this.serverInterval) window.clearInterval(this.serverInterval);
            window.setTimeout(function(){
                ajaxplorer.actionBar.fireDefaultAction("expire");
            }, 1000);
            return;
		}
		if( this._warningTime && idleTime >= this._warningTime ){
			if(this._state == 'active'){
				this.setWarningState();
			}
			this.updateWarningTimer((this._logoutTime - idleTime));
		}
	},
	
	/**
	 * Reactivate window
	 */
	exitIdleState : function(){
		this.removeWarningState();
		this.activityUpdater();
		this._state = 'active';
		window.clearInterval(this.interval);
		this.interval = window.setInterval(this.idleObserver.bind(this), 5000);
	},
	
	/**
	 * Put the window in "warning" state : overlay, shaking timer, chronometer.
	 */
	setWarningState : function(){
		this._state = 'warning';
		window.clearInterval(this.interval);
		this.interval = window.setInterval(this.idleObserver.bind(this), 1000);
		if(!this.warningPane){
			var mess = MessageHash[375].replace("__IDLE__", Math.round(this._warningTime/60) + 'mn');
			mess = mess.replace("__LOGOUT__", "<span class=\"warning_timer\"></span>");
			this.warningPane = new Element('div', {id:"activity_monitor_warning", className:'dialogBox', style:'padding:3px'}).update('<div class="dialogContent">'+mess+'<br><span class="click_anywhere">'+MessageHash[376]+'</span></div>');
			$(document.body).insert(this.warningPane);			
            displayLightBoxById("activity_monitor_warning");
            $('overlay').setStyle({cursor:'pointer'});
            $('overlay').observeOnce("click", this.exitIdleState.bind(this));
            $('activity_monitor_warning').observeOnce("click", this.exitIdleState.bind(this));
            new Effect.Shake(this.warningPane);
		}
	},
	
	/**
	 * Chronometer for warning before timeout
	 * @param time Integer
	 */
	updateWarningTimer : function(time){
		var stringTime = Math.floor(time/60)+'mn'+(time%60) + 's';
		this.warningPane.down('span.warning_timer').update(stringTime);
		if(this.warningPane.visible() && time%60%10 == 0){
			new Effect.Shake(this.warningPane.down('div.dialogContent'));
		}
	},
	
	/**
	 * Removes the overlay of warning state
	 */
	removeWarningState : function(){
		if(this.opaFx){
			this.opaFx.cancel();
		}
		$('overlay').setStyle({cursor:'default', opacity:0.4});
		hideLightBox();
	},
	
	/**
	 * Utility to get the time
	 * @returns Integer
	 */
	getNow : function(){
		return Math.round((new Date()).getTime() / 1000);
	}
	
	
});