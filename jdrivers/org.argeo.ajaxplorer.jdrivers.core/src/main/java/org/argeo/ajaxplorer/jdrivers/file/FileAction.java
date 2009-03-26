package org.argeo.ajaxplorer.jdrivers.file;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.argeo.ajaxplorer.jdrivers.AjxpAction;

public abstract class FileAction implements AjxpAction<FileDriver> {
	protected final Log log = LogFactory.getLog(getClass());
}
