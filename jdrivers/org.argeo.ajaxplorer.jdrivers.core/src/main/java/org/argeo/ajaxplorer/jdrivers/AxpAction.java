package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletRequest;

public interface AxpAction {
	public AxpAnswer execute(HttpServletRequest request);
}
