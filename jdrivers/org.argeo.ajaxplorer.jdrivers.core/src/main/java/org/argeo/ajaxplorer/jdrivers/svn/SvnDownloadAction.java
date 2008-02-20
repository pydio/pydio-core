package org.argeo.ajaxplorer.jdrivers.svn;

import javax.servlet.ServletOutputStream;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.tmatesoft.svn.core.io.SVNRepository;

import org.apache.commons.io.IOUtils;

import org.argeo.ajaxplorer.jdrivers.AjxpAction;
import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDownloadAction;

public class SvnDownloadAction implements AjxpAction<SvnDriver> {

	public AjxpAnswer execute(SvnDriver driver, HttpServletRequest request) {
		String path = request.getParameter("file");
		if (path.charAt(path.length() - 1) == '/') {
			// probably a directory
			return AjxpAnswer.DO_NOTHING;
		}

		String revStr = request.getParameter("rev");
		Long rev = Long.parseLong(revStr);
		return new SvnDownloadAnswer(driver, path, rev);
	}

	public class SvnDownloadAnswer implements AjxpAnswer {
		private final SvnDriver driver;
		private final String path;
		private final Long rev;

		public SvnDownloadAnswer(SvnDriver driver, String path, Long rev) {
			this.driver = driver;
			this.path = path;
			this.rev = rev;
		}

		public void updateResponse(HttpServletResponse response) {
			ServletOutputStream out = null;
			try {
				SVNRepository repository = driver.getRepository();
				out = response.getOutputStream();
				repository.getFile(path, rev, null, out);

				FileDownloadAction.setDefaultDownloadHeaders(response,
						getFileName(), null);

			} catch (Exception e) {
				throw new AjxpDriverException("Cannot download revision " + rev
						+ " of path " + path, e);
			} finally {
				IOUtils.closeQuietly(out);
			}
		}

		protected String getFileName() {
			int lastIndexSlash = path.lastIndexOf('/');
			final String origFileName;
			if (lastIndexSlash != -1) {
				origFileName = path.substring(lastIndexSlash + 1);
			} else {
				origFileName = path;
			}

			int firstIndexPoint = origFileName.indexOf('.');
			String prefix = origFileName.substring(0, firstIndexPoint);
			String ext = origFileName.substring(firstIndexPoint);
			return prefix + "-" + rev + ext;
		}
	}
}
