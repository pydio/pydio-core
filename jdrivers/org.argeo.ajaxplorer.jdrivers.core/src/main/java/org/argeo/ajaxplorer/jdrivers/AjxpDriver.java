package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletRequest;

public interface AjxpDriver {
	public AjxpAction getAction(HttpServletRequest request); 
}
