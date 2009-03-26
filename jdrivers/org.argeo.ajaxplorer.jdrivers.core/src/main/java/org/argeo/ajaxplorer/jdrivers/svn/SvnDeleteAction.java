package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.tmatesoft.svn.core.SVNException;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDeleteAction;

public class SvnDeleteAction extends FileDeleteAction<SvnDriver> {
	@Override
	protected void executeDelete(SvnDriver driver, File file) {
		try {
			driver.beginWriteAction(file.getParentFile());

			log.debug("SVN Delete: " + file);
			driver.getManager().getWCClient().doDelete(file, true, false);

			driver.commitAll("Commit delete of " + file.getName());
			driver.completeWriteAction(file.getParentFile());
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot delete file " + file, e);
		} finally {
			driver.rollbackWriteAction(file.getParentFile());
		}
	}

}
