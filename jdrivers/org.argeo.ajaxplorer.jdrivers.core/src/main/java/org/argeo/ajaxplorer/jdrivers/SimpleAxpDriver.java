package org.argeo.ajaxplorer.jdrivers;

import java.util.Map;
import java.util.TreeMap;

import javax.servlet.http.HttpServletRequest;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;

public class SimpleAxpDriver implements AxpDriver {
	protected final Log log = LogFactory.getLog(getClass());
	private Map<String, AxpAction> actions = new TreeMap<String, AxpAction>();

	public AxpAction getAction(HttpServletRequest request) {
		String action = request.getParameter("get_action");
		if (action == null) {
			action = request.getParameter("action");
		}
		if (!actions.containsKey(action)) {
			throw new AxpDriverException("Action " + action + " not defined.");
		}
		return actions.get(action);
	}

	public void setActions(Map<String, AxpAction> actions) {
		this.actions = actions;
	}

}
