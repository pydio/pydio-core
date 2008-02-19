package org.argeo.ajaxplorer.jdrivers;

import java.util.Map;
import java.util.TreeMap;

import javax.servlet.http.HttpServletRequest;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;

public class SimpleAjxpDriver implements AjxpDriver {
	protected final Log log = LogFactory.getLog(getClass());
	private Map<String, AjxpAction<? extends AjxpDriver>> actions = new TreeMap<String, AjxpAction<? extends AjxpDriver>>();

	public AjxpAnswer executeAction(HttpServletRequest request) {
		String actionStr = request.getParameter("get_action");
		if (actionStr == null) {
			actionStr = request.getParameter("action");
		}
		if (!actions.containsKey(actionStr)) {
			throw new AjxpDriverException("Action " + actionStr
					+ " not defined.");
		}
		AjxpAction action = actions.get(actionStr);
		return action.execute(this,request);
	}

	public void setActions(Map<String, AjxpAction<? extends AjxpDriver>> actions) {
		this.actions = actions;
	}

}
