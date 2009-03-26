package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;

import javax.servlet.http.HttpServletResponse;

public class FileImageProxyAction extends AbstractFileDownloadAction {

	@Override
	protected void setHeaders(HttpServletResponse response, File file) {
		FileType fileType = FileType.findType(file);
		response.setContentType(fileType.getImageType());
		response.setContentLength((int) file.length());
		response.setHeader("Cache-Control", "public");
	}

}
