package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;
import java.io.FileFilter;
import java.io.OutputStreamWriter;
import java.util.List;
import java.util.Vector;

import javax.servlet.ServletOutputStream;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.commons.io.IOUtils;

import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;
import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;

public class FileLsAction<T extends FileDriver, F extends AjxpFile> extends FileAction {

	public AjxpAnswer execute(FileDriver driver, HttpServletRequest request) {
		String modeStr = request.getParameter("mode");
		final LsMode mode;
		if (modeStr == null)
			mode = LsMode.NULL;
		else if (modeStr.equals("complete"))
			mode = LsMode.COMPLETE;
		else if (modeStr.equals("file_list"))
			mode = LsMode.FILE_LIST;
		else if (modeStr.equals("search"))
			mode = LsMode.SEARCH;
		else
			throw new AjxpDriverException("Unkown mode " + modeStr);

		String path = request.getParameter("dir");
		if (path == null) {
			path = "/";
		}

		boolean dirOnly = false;
		if (mode == LsMode.NULL || mode == LsMode.COMPLETE) {
			dirOnly = true;
		}

		List<F> ajxpFiles = listFiles((T)driver, path, dirOnly);
		/*
		 * File[] files = dir.listFiles(createFileFilter(request, dir)); List<AjxpFile>
		 * ajxpFiles = new Vector<AjxpFile>(); for (File file : files) { if
		 * (file.isDirectory()) { ajxpFiles.add(new AjxpFile(file, path)); }
		 * else { if (!dirOnly) ajxpFiles.add(new AjxpFile(file, path)); } }
		 */
		return new AxpLsAnswer(driver, ajxpFiles, mode);
	}

	protected List<F> listFiles(T driver,
			String path, boolean dirOnly) {
		File dir = driver.getFile(path);

		if (!dir.exists())
			throw new AjxpDriverException("Dir " + dir + " does not exist.");

		FileFilter filter = createFileFilter(dir);
		File[] files = dir.listFiles(filter);
		List<F> ajxpFiles = new Vector<F>();
		for (File file : files) {
			if (file.isDirectory()) {
				ajxpFiles.add((F)new AjxpFile(file, path));
			} else {
				if (!dirOnly)
					ajxpFiles.add((F)new AjxpFile(file, path));
			}
		}
		return ajxpFiles;
	}

	/** To be overridden. Accept all by default. */
	protected FileFilter createFileFilter(File dir) {
		return new FileFilter() {
			public boolean accept(File pathname) {
				return true;
			}

		};
	}

	protected class AxpLsAnswer implements AjxpAnswer {
		private final List<F> files;
		private final LsMode mode;
		private final FileDriver driver;

		public AxpLsAnswer(FileDriver driver, List<F> files, LsMode mode) {
			this.files = files;
			this.mode = mode;
			this.driver = driver;
		}

		public void updateResponse(HttpServletResponse response) {
			final String encoding = driver.getEncoding();
			response.setCharacterEncoding(encoding);
			response.setContentType("text/xml");

			ServletOutputStream out = null;
			OutputStreamWriter writer = null;
			try {
				out = response.getOutputStream();
				writer = new OutputStreamWriter(out, encoding);
				writer.write("<?xml version=\"1.0\" encoding=\"" + encoding
						+ "\"?>");
				// TODO add current path
				writer.write("<tree>");
				for (AjxpFile file : files) {
					writer.write(file.toXml(mode, encoding));
				}
				writer.write("</tree>");
				writer.flush();

			} catch (Exception e) {
				throw new AjxpDriverException("Could not write response.", e);
			} finally {
				IOUtils.closeQuietly(writer);
				IOUtils.closeQuietly(out);
			}
		}

	}
}
