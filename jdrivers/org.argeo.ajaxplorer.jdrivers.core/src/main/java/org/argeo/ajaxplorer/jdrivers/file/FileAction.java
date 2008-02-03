package org.argeo.ajaxplorer.jdrivers.file;

import org.argeo.ajaxplorer.jdrivers.AxpAction;

public abstract class FileAction implements AxpAction {
	private FileDriverContext fileDriverContext;

	public FileDriverContext getFileDriverContext() {
		return fileDriverContext;
	}

	public void setFileDriverContext(FileDriverContext fileDriverContext) {
		this.fileDriverContext = fileDriverContext;
	}

}
