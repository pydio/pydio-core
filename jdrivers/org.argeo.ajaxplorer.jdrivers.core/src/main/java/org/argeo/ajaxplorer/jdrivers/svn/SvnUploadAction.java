package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.tmatesoft.svn.core.SVNException;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileUploadAction;

public class SvnUploadAction extends FileUploadAction<SvnDriver> {
	@Override
	protected void postProcess(SvnDriver driver, File file) {
		try {
			driver.beginWriteAction(file.getParentFile());

			log.debug("SVN Add: " + file);
			driver.getManager().getWCClient().doAdd(file, true,
					file.isDirectory(), true, true);

			driver.commitAll("Commit file " + file.getName());
			driver.completeWriteAction(file.getParentFile());
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot commit file " + file, e);
		} finally {
			driver.rollbackWriteAction(file.getParentFile());
		}
	}

}
