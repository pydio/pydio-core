package org.argeo.ajaxplorer.jdrivers.file;

import java.io.IOException;
import java.io.InputStream;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.commons.io.IOUtils;
import org.argeo.ajaxplorer.jdrivers.AxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AxpDriverException;

public class FileUploadAction extends FileAction {

	public AxpAnswer execute(HttpServletRequest request) {
		log.debug("Execute upload");
		InputStream in = null;
		try {
			in = request.getInputStream();
			String str = IOUtils.toString(in);
			log.debug("File: " + str);
			return new AxpUploadAnswer();
		} catch (IOException e) {
			throw new AxpDriverException("Cannot upload file.", e);
		} finally {
			IOUtils.closeQuietly(in);
		}
	}

	protected class AxpUploadAnswer implements AxpAnswer {

		public void updateResponse(HttpServletResponse response) {
			// TODO Auto-generated method stub

		}

	}

}
