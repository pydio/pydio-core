package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;

import javax.servlet.http.HttpServletResponse;

public class FileDownloadAction extends AbstractFileDownloadAction {

	@Override
	protected void setHeaders(HttpServletResponse response, File file) {
		setDefaultDownloadHeaders(response, file.getName(), file.length());
	}

	public static void setDefaultDownloadHeaders(HttpServletResponse response,
			String fileName, Long fileLength) {
		response.setContentType("application/force-download; name=\""
				+ fileName + "\"");
		response.setHeader("Content-Transfer-Encoding", "binary");
		if (fileLength != null)
			response.setContentLength(fileLength.intValue());
		response.setHeader("Content-Disposition", "attachement; filename=\""
				+ fileName + "\"");
		response.setHeader("Expires", "0");
		response.setHeader("Cache-Control", "no-cache, must-revalidate");
		response.setHeader("Pragma", "no-cache");
	}
}
