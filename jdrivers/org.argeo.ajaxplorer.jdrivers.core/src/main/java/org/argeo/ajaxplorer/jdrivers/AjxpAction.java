package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletRequest;

public interface AjxpAction<T extends AjxpDriver>{
	public AjxpAnswer execute(T driver, HttpServletRequest request);
}
