package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;
import java.io.PrintWriter;
import java.io.StringReader;
import java.io.StringWriter;
import java.io.Writer;
import java.util.List;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.tmatesoft.svn.core.internal.io.fs.FSRepositoryFactory;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNRevision;
import org.tmatesoft.svn.core.wc.xml.SVNXMLLogHandler;
import org.tmatesoft.svn.core.wc.xml.SVNXMLSerializer;

import org.apache.commons.io.IOUtils;
import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;

import org.argeo.ajaxplorer.jdrivers.AjxpAction;
import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;

public class SvnLogAction implements AjxpAction<SvnDriver> {
	private final Log log = LogFactory.getLog(getClass());
	protected final SVNClientManager manager;

	public SvnLogAction() {
		FSRepositoryFactory.setup();
		manager = SVNClientManager.newInstance();
	}

	public AjxpAnswer execute(SvnDriver driver, HttpServletRequest request) {
		String fileStr = request.getParameter("file");
		log.debug("Log file " + fileStr);
		if (fileStr == null) {
			throw new AjxpDriverException("A  file needs to be provided.");
		}
		File file = new File(driver.getBasePath() + fileStr);
		return new SvnLogAnswer(driver, file);
	}

	protected class SvnLogAnswer implements AjxpAnswer {
		private final SvnDriver driver;
		private final File file;

		public SvnLogAnswer(SvnDriver driver, File file) {
			this.driver = driver;
			this.file = file;
		}

		public void updateResponse(HttpServletResponse response) {
			StringWriter writer = null;
			PrintWriter servletWriter = null;
			StringReader reader = null;
			try {
				// writer = response.getWriter();
				writer = new StringWriter();
				writer.append("<tree>");
				writer.append("<log>");
				SVNXMLSerializer serializer = new SVNXMLSerializer(writer);
				SVNXMLLogHandler logHandler = new SVNXMLLogHandler(serializer);
				manager.getLogClient().doLog(new File[] { file },
						SVNRevision.create(0), SVNRevision.HEAD, true, false,
						100, logHandler);
				writer.append("</log>");
				writer.append("</tree>");

				String message = writer.toString();
				if(log.isTraceEnabled()){
					log.trace(message);
				}
				
				reader = new StringReader(message);
				
				servletWriter = response.getWriter();
				
				List<String> lines = IOUtils.readLines(reader);
				IOUtils.writeLines(lines, "", servletWriter);
			} catch (Exception e) {
				throw new AjxpDriverException(
						"Cannot retrieve log for " + file, e);
			} finally {
				IOUtils.closeQuietly(writer);
				IOUtils.closeQuietly(reader);
				IOUtils.closeQuietly(servletWriter);
			}
		}

	}
}
