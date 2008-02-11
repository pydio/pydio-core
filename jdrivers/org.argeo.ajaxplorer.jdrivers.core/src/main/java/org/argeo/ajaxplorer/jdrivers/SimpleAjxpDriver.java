package org.argeo.ajaxplorer.jdrivers;

import java.util.Map;
import java.util.TreeMap;

import javax.servlet.http.HttpServletRequest;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;

public class SimpleAjxpDriver implements AjxpDriver {
	protected final Log log = LogFactory.getLog(getClass());
	private Map<String, AjxpAction> actions = new TreeMap<String, AjxpAction>();

	public AjxpAction getAction(HttpServletRequest request) {
/*
		log.debug("Request " + request + ", " + request.getMethod() + ", "
				+ request.getParameterMap() + ", ");
		InputStream in = null;
		try {
			in = request.getInputStream();
			// log.debug("Request:\n"+IOUtils.toString(in));
		} catch (IOException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
		IOUtils.closeQuietly(in);
		for (Object param : request.getParameterMap().keySet()) {
			log.debug("Param: " + param + "="
					+ request.getParameterMap().get(param));
		}

		Enumeration<String> en = request.getAttributeNames();
		while (en.hasMoreElements()) {
			String name = en.nextElement();
			log.debug("Attr: " + name + ": " + request.getAttribute(name));
		}

		if (request instanceof MultipartHttpServletRequest) {
			MultipartHttpServletRequest mpr = (MultipartHttpServletRequest) request;
			log.debug("Multipart req: " + mpr);
			try {
				
				for (Object param : mpr.getParameterMap().keySet()) {
					log.debug("ParamMPR: " + param + "="
							+ mpr.getParameterMap().get(param));
				}
				for (Object param : mpr.getFileMap().keySet()) {
					log
							.debug("ParamFile: " + param + "="
									+ mpr.getFileMap().get(param));
				}
			} catch (Exception e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
*/
		String action = request.getParameter("get_action");
		if (action == null) {
			action = request.getParameter("action");
		}
		if (!actions.containsKey(action)) {
			throw new AjxpDriverException("Action " + action + " not defined.");
		}
		return actions.get(action);
	}

	public void setActions(Map<String, AjxpAction> actions) {
		this.actions = actions;
	}

}
