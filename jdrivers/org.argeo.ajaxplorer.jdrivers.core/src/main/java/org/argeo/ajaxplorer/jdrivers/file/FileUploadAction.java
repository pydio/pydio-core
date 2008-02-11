package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.commons.io.FileUtils;
import org.apache.commons.io.IOUtils;
import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.multipart.MultipartHttpServletRequest;

public class FileUploadAction extends FileAction {

	public AjxpAnswer execute(HttpServletRequest request) {
		log.debug("Execute upload");

		if (!(request instanceof MultipartHttpServletRequest)) {
			throw new AjxpDriverException(
					"Cann only deal with MultipartHttpServletRequest");
		}
		MultipartHttpServletRequest mpr = (MultipartHttpServletRequest) request;
		String dir = mpr.getParameter("dir");
		String fileName = mpr.getParameter("Filename");

		InputStream in = null;
		OutputStream out = null;
		try {
			MultipartFile file = mpr.getFile("Filedata");
			in = file.getInputStream();
			out = new FileOutputStream(getFileDriverContext()
					.getBasePath()
					+ dir + File.separator + fileName);
			IOUtils.copy(in, out);
			return new AxpUploadAnswer();
		} catch (IOException e) {
			throw new AjxpDriverException("Cannot upload file.", e);
		} finally {
			IOUtils.closeQuietly(in);
			IOUtils.closeQuietly(out);
		}
	}

	protected class AxpUploadAnswer implements AjxpAnswer {

		public void updateResponse(HttpServletResponse response) {
			// TODO Auto-generated method stub

		}

	}

}
