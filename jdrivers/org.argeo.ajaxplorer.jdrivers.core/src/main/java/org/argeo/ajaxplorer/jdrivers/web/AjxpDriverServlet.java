package org.argeo.ajaxplorer.jdrivers.web;

import java.io.IOException;
import java.util.Enumeration;

import javax.servlet.ServletConfig;
import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.argeo.ajaxplorer.jdrivers.AjxpAction;
import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AjxpDriver;

import org.springframework.beans.BeanWrapper;
import org.springframework.beans.BeanWrapperImpl;
import org.springframework.beans.BeansException;
import org.springframework.web.context.WebApplicationContext;
import org.springframework.web.context.support.WebApplicationContextUtils;
import org.springframework.web.servlet.HttpServletBean;

public class AjxpDriverServlet extends HttpServletBean {
	static final long serialVersionUID = 1l;

	protected final Log log = LogFactory.getLog(getClass());
	private String driverBean;
	private AjxpDriver driver;

	@Override
	public void init(ServletConfig sc) throws ServletException {
		super.init(sc);
		WebApplicationContext context = WebApplicationContextUtils
				.getRequiredWebApplicationContext(sc.getServletContext());
		driverBean = sc.getInitParameter("driverBean");
		if (driverBean == null) {
			throw new ServletException(
					"No driver found, please set the driverBean property");
		}

		logger.info("Loading driver " + driverBean);
		driver = (AjxpDriver) context.getBean(driverBean);

		BeanWrapper wrapper = new BeanWrapperImpl(driver);
		Enumeration<String> en = sc.getInitParameterNames();
		while (en.hasMoreElements()) {
			String name = en.nextElement();
			if (name.indexOf(driverBean + '.') == 0
					&& name.length() > (driverBean.length() + 1)) {
				String propertyName = name.substring(driverBean.length() + 1);
				String value = sc.getInitParameter(name);
				if (value != null) {
					try {
						wrapper.setPropertyValue(propertyName, value);
					} catch (BeansException e) {
						throw new ServletException("Cannot set property "
								+ propertyName + " of bean " + driverBean, e);
					}
				}
			}
		}
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

			// AjxpAction action = driver.getAction(req);
			// AjxpAnswer answer = action.execute(req);
			AjxpAnswer answer = driver.executeAction(req);

			answer.updateResponse(resp);

			log.debug(id + " " + method + " completed");
		} catch (Exception e) {
			log.error(id + " Cannot process request.", e);
			throw new ServletException("Cannot process request " + id, e);
		}

	}

	public void setDriverBean(String driverName) {
		this.driverBean = driverName;
	}

}
