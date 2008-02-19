package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDeleteAction;
import org.argeo.ajaxplorer.jdrivers.file.FileDriver;

import org.tmatesoft.svn.core.SVNException;
import org.tmatesoft.svn.core.internal.io.fs.FSRepositoryFactory;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNRevision;

public class SvnDeleteAction extends FileDeleteAction {

	protected final SVNClientManager manager;

	public SvnDeleteAction() {
		FSRepositoryFactory.setup();
		manager = SVNClientManager.newInstance();
	}

	@Override
	protected void executeDelete(FileDriver driver, File file) {
		File baseDir = new File(driver.getBasePath());
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
