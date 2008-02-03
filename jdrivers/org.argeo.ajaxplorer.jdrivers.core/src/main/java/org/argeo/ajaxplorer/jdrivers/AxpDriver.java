package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletRequest;

public interface AxpDriver {
	public AxpAction getAction(HttpServletRequest request); 
}
