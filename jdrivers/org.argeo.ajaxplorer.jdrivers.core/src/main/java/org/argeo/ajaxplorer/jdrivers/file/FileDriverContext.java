package org.argeo.ajaxplorer.jdrivers.file;

public class FileDriverContext {
	private String basePath;
	private String encoding = "UTF-8";

	public String getBasePath() {
		return basePath;
	}

	public void setBasePath(String basePath) {
		if (basePath.charAt(basePath.length() - 1) != '/')
			basePath = basePath + '/';
		this.basePath = basePath;
	}

	public String getEncoding() {
		return encoding;
	}

	public void setEncoding(String encoding) {
		this.encoding = encoding;
	}

}
