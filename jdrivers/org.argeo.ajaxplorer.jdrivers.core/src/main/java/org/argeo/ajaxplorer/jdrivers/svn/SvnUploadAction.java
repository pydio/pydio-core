package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDriver;
import org.argeo.ajaxplorer.jdrivers.file.FileUploadAction;
import org.tmatesoft.svn.core.SVNException;
import org.tmatesoft.svn.core.internal.io.fs.FSRepositoryFactory;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNRevision;

public class SvnUploadAction extends FileUploadAction {
	static {
		FSRepositoryFactory.setup();
	}

	@Override
	protected void postProcess(FileDriver driver, File file) {
		SVNClientManager manager = SVNClientManager.newInstance();
		File baseDir = new File(driver.getBasePath());
		try {
			log.debug("SVN Update: " + baseDir);
			manager.getUpdateClient().doUpdate(baseDir, SVNRevision.HEAD, true);
			log.debug("SVN Add: " + file);
			manager.getWCClient().doAdd(file, true, file.isDirectory(), true,
					true);
			log.debug("SVN Commit: " + baseDir);
			manager.getCommitClient().doCommit(new File[] { baseDir }, true,
					"Commit file " + file, true, true);
			log.debug("SVN Update: " + baseDir);
			manager.getUpdateClient().doUpdate(baseDir, SVNRevision.HEAD, true);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot commit file " + file, e);
		}
	}

}
