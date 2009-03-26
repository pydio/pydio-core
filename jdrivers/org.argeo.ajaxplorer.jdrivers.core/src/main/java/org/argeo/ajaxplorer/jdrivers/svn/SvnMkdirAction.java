package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.tmatesoft.svn.core.SVNException;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileMkdirAction;

public class SvnMkdirAction extends FileMkdirAction<SvnDriver> {
	@Override
	protected void postProcess(SvnDriver driver, File newDir) {
		try {
			driver.beginWriteAction(newDir.getParentFile());

			log.debug("SVN Add: " + newDir);
			driver.getManager().getWCClient().doAdd(newDir, true,
					newDir.isDirectory(), true, true);

			driver.commitAll("Commit new dir " + newDir.getName());
			driver.completeWriteAction(newDir.getParentFile());
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot commit new dir" + newDir, e);
		} finally {
			driver.rollbackWriteAction(newDir.getParentFile());
		}
	}

}
