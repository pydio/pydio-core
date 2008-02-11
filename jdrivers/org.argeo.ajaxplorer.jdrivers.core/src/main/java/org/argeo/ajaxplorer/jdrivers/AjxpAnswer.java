package org.argeo.ajaxplorer.jdrivers;

import javax.servlet.http.HttpServletResponse;

public interface AjxpAnswer {
	public static AjxpAnswer DO_NOTHING = new AjxpAnswer(){
		public void updateResponse(HttpServletResponse response) {
		}
	};

	public void updateResponse(HttpServletResponse response);
}
