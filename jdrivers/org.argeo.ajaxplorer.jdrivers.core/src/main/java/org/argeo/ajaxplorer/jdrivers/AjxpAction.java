package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletRequest;

public interface AjxpAction {
	public AjxpAnswer execute(HttpServletRequest request);
}
