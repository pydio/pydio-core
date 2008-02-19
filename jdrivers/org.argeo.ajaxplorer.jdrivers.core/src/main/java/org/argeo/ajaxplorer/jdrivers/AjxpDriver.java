package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletRequest;

public interface AjxpDriver {
	public AjxpAnswer executeAction(HttpServletRequest request); 
}
