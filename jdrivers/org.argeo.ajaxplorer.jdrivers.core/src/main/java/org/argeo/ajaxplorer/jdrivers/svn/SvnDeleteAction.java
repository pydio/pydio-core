package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.tmatesoft.svn.core.SVNException;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNRevision;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDeleteAction;

public class SvnDeleteAction extends FileDeleteAction<SvnDriver> {
	@Override
	protected void executeDelete(SvnDriver driver, File file) {
		File baseDir = new File(driver.getBasePath());
		SVNClientManager manager = driver.getManager();
		try {
			log.debug("SVN Update: " + baseDir);
			manager.getUpdateClient().doUpdate(baseDir, SVNRevision.HEAD, true);
			log.debug("SVN Delete: " + file);
			manager.getWCClient().doDelete(file, true, false);
			log.debug("SVN Commit: " + baseDir);
			manager.getCommitClient().doCommit(new File[] { baseDir }, true,
					"Commit delete of " + file, true, true);
			log.debug("SVN Update: " + baseDir);
			manager.getUpdateClient().doUpdate(baseDir, SVNRevision.HEAD, true);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot delete file " + file, e);
		}
	}

}
