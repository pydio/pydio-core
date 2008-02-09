package org.argeo.ajaxplorer.jdrivers.web;

import java.io.IOException;

import javax.servlet.ServletConfig;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.argeo.ajaxplorer.jdrivers.AxpAction;
import org.argeo.ajaxplorer.jdrivers.AxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AxpDriver;
import org.springframework.web.context.WebApplicationContext;
import org.springframework.web.context.support.WebApplicationContextUtils;
import org.springframework.web.servlet.HttpServletBean;

public class AxpDriverServlet extends HttpServletBean {
	protected final Log log = LogFactory.getLog(getClass());
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
		processRequest("GET", req, resp);
	}

	@Override
	protected void doPost(HttpServletRequest req, HttpServletResponse resp)
			throws ServletException, IOException {
		processRequest("POST", req, resp);
	}

	protected void processRequest(String method, HttpServletRequest req,
			HttpServletResponse resp) throws ServletException, IOException {
		long id = System.currentTimeMillis();
		try {
			log
					.debug(id + " Received " + method + ": "
							+ req.getParameterMap());

			AxpAction action = driver.getAction(req);
			AxpAnswer answer = action.execute(req);
			answer.updateResponse(resp);

			log.debug(id + " " + method + " completed");
		} catch (Exception e) {
			log.error(id + " Cannot process request.", e);
			throw new ServletException("Cannot process request " + id, e);
		}

	}

	public void setDriverName(String driverName) {
		this.driverName = driverName;
	}

}
