package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import org.tmatesoft.svn.core.SVNException;
import org.tmatesoft.svn.core.SVNURL;
import org.tmatesoft.svn.core.internal.io.fs.FSRepositoryFactory;
import org.tmatesoft.svn.core.io.SVNRepository;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNInfo;
import org.tmatesoft.svn.core.wc.SVNRevision;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileDriver;

public class SvnDriver extends FileDriver {
	private SVNURL baseUrl;
	private SVNClientManager manager;

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
			throw new AjxpDriverException("No base path was provided.");
			// TODO: default base path?
		}
		log.info("SVN driver initialized with base url " + baseUrl
				+ " and base path " + basePath);
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
		if (baseUrl == null) {
			throw new AjxpDriverException(
					"No SVN URL provided, cannot check out.");
		}

		// Make sure directory exists
		baseDir.mkdirs();

		throw new AjxpDriverException(
				"Automatic check out not yet implemented.");
	}

	public void setBaseUrl(String baseUrl) {
		try {
			this.baseUrl = SVNURL.parseURIDecoded(baseUrl);
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot parse SVN URL " + baseUrl, e);
		}
	}

	public SVNClientManager getManager() {
		return manager;
	}

}
