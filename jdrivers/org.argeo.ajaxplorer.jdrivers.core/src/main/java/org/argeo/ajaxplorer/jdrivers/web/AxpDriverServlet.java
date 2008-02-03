package org.argeo.ajaxplorer.jdrivers.web;

import java.io.IOException;

import javax.servlet.ServletConfig;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.argeo.ajaxplorer.jdrivers.AxpAction;
import org.argeo.ajaxplorer.jdrivers.AxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AxpDriver;
import org.springframework.web.context.WebApplicationContext;
import org.springframework.web.context.support.WebApplicationContextUtils;
import org.springframework.web.servlet.HttpServletBean;

public class AxpDriverServlet extends HttpServletBean {

	private String driverName;
	private AxpDriver driver;

	@Override
	public void init(ServletConfig sc) throws ServletException {
		super.init(sc);
		WebApplicationContext context = WebApplicationContextUtils
				.getRequiredWebApplicationContext(sc.getServletContext());
		driverName = sc.getInitParameter("driverName");
		logger.info("Loading driver " + driverName);
		driver = (AxpDriver) context.getBean(driverName);
	}

	@Override
	protected void doGet(HttpServletRequest req, HttpServletResponse resp)
			throws ServletException, IOException {
		AxpAction action = driver.getAction(req);
		AxpAnswer answer = action.execute(req);
		answer.updateResponse(resp);
	}

	public void setDriverName(String driverName) {
		this.driverName = driverName;
	}

}
