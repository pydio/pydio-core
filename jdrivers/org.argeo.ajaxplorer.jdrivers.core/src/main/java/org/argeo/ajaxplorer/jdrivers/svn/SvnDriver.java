package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.springframework.beans.factory.BeanNameAware;
import org.tmatesoft.svn.core.SVNException;
import org.tmatesoft.svn.core.SVNURL;
import org.tmatesoft.svn.core.internal.io.fs.FSRepositoryFactory;
import org.tmatesoft.svn.core.io.SVNRepository;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNInfo;
import org.tmatesoft.svn.core.wc.SVNRevision;
import org.tmatesoft.svn.core.wc.admin.SVNAdminClient;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDriver;

public class SvnDriver extends FileDriver implements BeanNameAware {
	private final static String DEFAULT_DATA_PATH = System
			.getProperty("user.home")
			+ File.separator + "AjaXplorerArchiver" + File.separator + "data";

	private final static long WRITE_ACTION_TIMEOUT = 10 * 60 * 1000;

	private SVNURL baseUrl;
	private SVNClientManager manager;

	private String beanName;

	private boolean isInWriteAction = false;

	public void init() {
		FSRepositoryFactory.setup();
		manager = SVNClientManager.newInstance();

		String basePath = getBasePath();
		if (basePath != null) {
			File baseDir = new File(basePath);
			if (baseDir.exists()) {// base dir exists
				boolean shouldCheckOut = baseDirChecks(baseDir);
				if (shouldCheckOut) {
					checkOut(baseDir);
				}
			} else {
				checkOut(baseDir);
			}
		} else {
			String defaultBasePath = DEFAULT_DATA_PATH + File.separator
					+ "svnwc" + File.separator + beanName;
			log.warn("No base path provided, use " + defaultBasePath);
			setBasePath(defaultBasePath);

			File baseDir = new File(getBasePath());
			if (!baseDir.exists()) {
				baseDir.mkdirs();
			}

			if (baseDirChecks(baseDir)) {
				if (getBaseUrl() == null) {
					String defaultRepoPath = DEFAULT_DATA_PATH + File.separator
							+ "svnrepos" + File.separator + beanName;
					log.warn("No base URL found, create repository at "
							+ defaultRepoPath);
					baseUrl = createRepository(new File(defaultRepoPath));
				}
				checkOut(new File(getBasePath()));
			}
		}
		log.info("SVN driver initialized with base url " + getBaseUrl()
				+ " and base path " + getBasePath());
	}

	/** Builds a SVN URL. */
	public SVNURL getSVNURL(String relativePath) {
		try {
			return baseUrl.appendPath(relativePath, false);
		} catch (SVNException e) {
			throw new AjxpDriverException(
					"Cannot build URL from relative path " + relativePath
							+ " and base url " + baseUrl);
		}
	}

	public SVNRepository getRepository() {
		try {
			return manager.createRepository(baseUrl, true);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot create repository for "
					+ baseUrl, e);
		}
	}

	/**
	 * Verifies that the provided existing base dir is ok and whether one should
	 * check out. Set the base url from the working copy.
	 * 
	 * @return whether one should check out.
	 */
	protected boolean baseDirChecks(File baseDir) {
		if (!baseDir.isDirectory()) {
			throw new AjxpDriverException("Base path " + baseDir
					+ " is not a directory.");
		}

		try {// retrieves SVN infos
			SVNInfo info = manager.getWCClient().doInfo(baseDir,
					SVNRevision.WORKING);
			SVNURL baseUrlTemp = info.getURL();
			if (baseUrl != null) {
				if (!baseUrl.equals(baseUrlTemp)) {
					throw new AjxpDriverException(
							"SVN URL of the working copy "
									+ baseUrlTemp
									+ " is not compatible with provided baseUrl "
									+ baseUrl);
				}
			} else {
				this.baseUrl = baseUrlTemp;
			}
			return false;
		} catch (SVNException e) {// no info retrieved
			log
					.warn("Could not retrieve SVN info from "
							+ baseDir
							+ "("
							+ e.getMessage()
							+ "). Guess that it is and empty dir and try to check out from provided URL.");
			if (baseDir.listFiles().length != 0) {
				throw new AjxpDriverException("Base dir " + baseDir
						+ " is not a working copy and not an empty dir.");
			}
			return true;
		}
	}

	protected void checkOut(File baseDir) {
		if (getBaseUrl() == null) {
			throw new AjxpDriverException(
					"No SVN URL provided, cannot check out.");
		}

		// Make sure directory exists
		baseDir.mkdirs();

		try {
			long revision = manager.getUpdateClient().doCheckout(getBaseUrl(),
					baseDir, SVNRevision.UNDEFINED, SVNRevision.HEAD, true);
			log.info("Checked out from " + baseUrl + " to " + baseDir
					+ " at revision " + revision);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot check out from " + baseUrl
					+ " to " + baseDir, e);
		}
	}

	protected SVNURL createRepository(File repoDir) {
		try {
			SVNAdminClient adminClient = manager.getAdminClient();
			return adminClient.doCreateRepository(repoDir, null, true, false);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot create repository at "
					+ repoDir, e);
		}
	}

	private void updateIfRequired(File dir) {
		try {
			SVNInfo wcInfo = manager.getWCClient().doInfo(getBaseDir(),
					SVNRevision.WORKING);
			SVNRevision wcRev = wcInfo.getRevision();
			SVNInfo repoInfo = manager.getWCClient().doInfo(getBaseUrl(),
					null, SVNRevision.HEAD);
			SVNRevision repoRev = repoInfo.getRevision();

			if (log.isTraceEnabled())
				log
						.trace("WC Revision=" + wcRev + ", Repo Revision="
								+ repoRev);

			if (!wcRev.equals(repoRev)) {
				log.debug("Update working copy from revision " + wcRev
						+ " to revision " + repoRev);
				manager.getUpdateClient().doUpdate(getBaseDir(),
						SVNRevision.HEAD, true);
			}
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot update working copy "
					+ getBaseDir(),e);
		}
	}

	public synchronized void beginWriteAction(File dir) {
		if (isInWriteAction) {
			try {
				wait(WRITE_ACTION_TIMEOUT);
			} catch (InterruptedException e) {
				// silent
			}
			if (isInWriteAction) {
				throw new AjxpDriverException(
						"Still in write action after timeout "
								+ WRITE_ACTION_TIMEOUT + " ms.");
			}
		}

		isInWriteAction = true;
		updateIfRequired(dir);
	}

	public synchronized void completeWriteAction(File dir) {
		isInWriteAction = false;
		notifyAll();
	}

	public synchronized void rollbackWriteAction(File dir) {
		// TODO: revert?
		isInWriteAction = false;
		notifyAll();
	}
	
	public void commitAll(String message) throws SVNException{
		if(log.isTraceEnabled())
		log.trace("SVN Commit: " + getBaseDir());
		manager.getCommitClient().doCommit(new File[] { getBaseDir() }, true,
				message, true, true);

	}

	/** Spring bean name, set at initialization. */
	public void setBeanName(String beanName) {
		this.beanName = beanName;
	}

	public void setBaseUrl(String baseUrl) {
		try {
			this.baseUrl = SVNURL.parseURIDecoded(baseUrl);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot parse SVN URL " + baseUrl, e);
		}
	}

	public SVNURL getBaseUrl() {
		return baseUrl;
	}

	public SVNClientManager getManager() {
		return manager;
	}

}
