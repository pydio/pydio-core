package org.argeo.ajaxplorer.jdrivers;

import java.util.Map;
import java.util.TreeMap;

import javax.servlet.http.HttpServletRequest;


public class SimpleAxpDriver implements AxpDriver {
	private Map<String, AxpAction> actions = new TreeMap<String, AxpAction>();

	public AxpAction getAction(HttpServletRequest request) {
		String action = request.getParameter("get_action");
		if(!actions.containsKey(action)){
			throw new AxpDriverException("Action "+action+" not defined.");
		}
		return actions.get(action);
	}

	public void setActions(Map<String, AxpAction> actions) {
		this.actions = actions;
	}
	
	

}
