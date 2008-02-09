package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;
import java.io.InputStream;
import java.io.OutputStream;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.commons.io.FileUtils;
import org.apache.commons.io.IOUtils;
import org.argeo.ajaxplorer.jdrivers.AxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AxpDriverException;

public class FileDownloadAction extends FileAction {

	public AxpAnswer execute(HttpServletRequest request) {
		String fileStr = request.getParameter("file");
		File file = new File(getFileDriverContext().getBasePath() + fileStr);
		return new AxpDownloadAnswer(file);
	}

	protected class AxpDownloadAnswer implements AxpAnswer {
		private final File file;

		public AxpDownloadAnswer(File file) {
			this.file = file;
		}

		public void updateResponse(HttpServletResponse response) {
			InputStream in = null;
			OutputStream out = null;
			try {
				response.setContentType("application/force-download; name=\""
						+ file.getName() + "\"");
				response.setHeader("Content-Transfer-Encoding", "binary");
				response.setContentLength((int) file.length());
				response.setHeader("Content-Disposition",
						"attachement; filename=\"" + file.getName() + "\"");
				response.setHeader("Expires", "0");
				response
						.setHeader("Cache-Control", "no-cache, must-revalidate");
				response.setHeader("Pragma", "no-cache");

				if (log.isDebugEnabled())
					log.debug("Download file " + file);
				in = FileUtils.openInputStream(file);
				out = response.getOutputStream();

				IOUtils.copy(in, out);
				out.flush();

			} catch (Exception e) {
				e.printStackTrace();
				throw new AxpDriverException("Cannot download file " + file, e);
			} finally {
				IOUtils.closeQuietly(in);
				IOUtils.closeQuietly(out);
			}

		}

	}
}
