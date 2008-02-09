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

public abstract class AbstractFileDownloadAction extends FileAction {
	public AxpAnswer execute(HttpServletRequest request) {
		String fileStr = request.getParameter(getFileParameter());
		if (fileStr == null) {
			throw new AxpDriverException(
					"A  file to download needs to be provided.");
		}
		File file = new File(getFileDriverContext().getBasePath() + fileStr);
		return new AxpBasicDownloadAnswer(file);
	}

	/** Return 'file' by default. */
	protected String getFileParameter() {
		return "file";
	}

	/** To be overridden. Do nothing by default. */
	protected void setHeaders(HttpServletResponse response, File file) {
		// do nothing
	}

	protected class AxpBasicDownloadAnswer implements AxpAnswer {
		private final File file;

		public AxpBasicDownloadAnswer(File file) {
			this.file = file;
		}

		public void updateResponse(HttpServletResponse response) {
			InputStream in = null;
			OutputStream out = null;
			try {
				setHeaders(response, file);

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
