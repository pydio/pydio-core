/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : A monitor for user "idle" state to prevent session timing out.
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
	
	initialize : function(serverSessionTime, clientSessionTime, warningMinutes){
		if(!serverSessionTime) return;
		this._serverSessionTime = serverSessionTime;
		if(warningMinutes) this._warningMinutes = warningMinutes;
		this._warningTime = clientSessionTime - this._warningMinutes*60;
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
				this.serverInterval = window.setInterval(this.serverObserver.bind(this), this._renewTime*1000);
			}
		}.bind(this));		
	},
		
	activityObserver : function(){
		if(this._state == 'warning') return;
		if(this.timer){
			window.clearTimeout(this.timer);
		}
		this.timer = window.setTimeout(this.activityUpdater.bind(this), 1000);
	},
	
	activityUpdater : function(){
		//console.log('activity!');
		this._lastActive = this.getNow();
	},
	
	serverObserver : function(){
		new Ajax.Request(window.ajxpServerAccessPath, 
		{
			method:'get',
			parameters:{ping:'true'}
		});		
	},
	
	idleObserver : function(){
		if(this._state == 'inactive') return;
		var idleTime = (this.getNow() - this._lastActive);
		if( idleTime >= this._logoutTime ){
			//console.log('firing logout');
			this.removeWarningState();
			this._state = 'active';
			if(this.interval) window.clearInterval(this.interval);
			if(this.serverInterval) window.clearInterval(this.serverInterval);
			ajaxplorer.actionBar.fireDefaultAction("expire");
			return;
		}
		if( idleTime >= this._warningTime ){
			if(this._state == 'active'){
				this.setWarningState();
			}
			this.updateWarningTimer((this._logoutTime - idleTime));
		}
	},
	
	exitIdleState : function(){
		this.removeWarningState();
		this.activityUpdater();
		this._state = 'active';
		window.clearInterval(this.interval);
		this.interval = window.setInterval(this.idleObserver.bind(this), 5000);
	},
	
	setWarningState : function(){
		this._state = 'warning';
		window.clearInterval(this.interval);
		this.interval = window.setInterval(this.idleObserver.bind(this), 1000);
		if(!this.warningPane){
			var mess = MessageHash[375].replace("__IDLE__", Math.round(this._warningTime/60) + 'mn');
			mess = mess.replace("__LOGOUT__", "<span class=\"warning_timer\"></span>");
			this.warningPane = new Element('div', {id:"activity_monitor_warning", className:'dialogBox', style:'padding:3px'}).update('<div class="dialogContent">'+mess+'<br><span class="click_anywhere">'+MessageHash[376]+'</span></div>');
			$(document.body).insert(this.warningPane);			
		}
		displayLightBoxById("activity_monitor_warning");
		$('overlay').setStyle({cursor:'pointer'});
		$('overlay').observeOnce("click", this.exitIdleState.bind(this));
		$('activity_monitor_warning').observeOnce("click", this.exitIdleState.bind(this));
		new Effect.Shake(this.warningPane);
		this.opaFx = new Effect.Opacity($('overlay'), {
			from:0.4, 
			to : 1,
			duration: this._warningMinutes*60
		});
	},
	
	updateWarningTimer : function(time){
		var stringTime = Math.floor(time/60)+'mn'+(time%60) + 's';
		this.warningPane.down('span.warning_timer').update(stringTime);
		if(this.warningPane.visible() && time%60%10 == 0){
			new Effect.Shake(this.warningPane);
		}
	},
	
	removeWarningState : function(){
		if(this.opaFx){
			this.opaFx.cancel();
		}
		$('overlay').setStyle({cursor:'default', opacity:0.4});
		hideLightBox();
	},
	
	getNow : function(){
		return Math.round((new Date()).getTime() / 1000);
	}
	
	
});