package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;

import javax.servlet.http.HttpServletResponse;

public class FileDownloadAction extends AbstractFileDownloadAction {

	@Override
	protected void setHeaders(HttpServletResponse response, File file) {
		response.setContentType("application/force-download; name=\""
				+ file.getName() + "\"");
		response.setHeader("Content-Transfer-Encoding", "binary");
		response.setContentLength((int) file.length());
		response.setHeader("Content-Disposition", "attachement; filename=\""
				+ file.getName() + "\"");
		response.setHeader("Expires", "0");
		response.setHeader("Cache-Control", "no-cache, must-revalidate");
		response.setHeader("Pragma", "no-cache");
	}
}
