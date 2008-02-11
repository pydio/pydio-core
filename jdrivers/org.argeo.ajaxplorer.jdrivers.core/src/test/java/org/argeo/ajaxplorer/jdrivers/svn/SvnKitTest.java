package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;

import junit.framework.TestCase;

import org.tmatesoft.svn.core.internal.io.fs.FSRepositoryFactory;
import org.tmatesoft.svn.core.internal.io.svn.SVNRepositoryFactoryImpl;
import org.tmatesoft.svn.core.wc.ISVNOptions;
import org.tmatesoft.svn.core.wc.SVNClientManager;
import org.tmatesoft.svn.core.wc.SVNRevision;
import org.tmatesoft.svn.core.wc.SVNWCUtil;

public class SvnKitTest extends TestCase {

	public void testUpdate() throws Exception {
		SVNRepositoryFactoryImpl.setup();
		FSRepositoryFactory.setup();
		String path = "C:/mbaudier/test/ajxpRoot/";
		File file = new File(path);
		ISVNOptions options = SVNWCUtil.createDefaultOptions(true);
		SVNClientManager manager = SVNClientManager.newInstance(options);
		manager.getUpdateClient().doUpdate(file, SVNRevision.HEAD, true);
	}
}
