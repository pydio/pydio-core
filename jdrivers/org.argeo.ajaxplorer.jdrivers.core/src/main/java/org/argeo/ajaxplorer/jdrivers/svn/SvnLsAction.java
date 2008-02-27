package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;
import java.io.FileFilter;
import java.util.List;
import java.util.Vector;

import org.tmatesoft.svn.core.SVNException;
import org.tmatesoft.svn.core.wc.SVNInfo;
import org.tmatesoft.svn.core.wc.SVNRevision;
import org.tmatesoft.svn.core.wc.SVNWCClient;

import org.argeo.ajaxplorer.jdrivers.AjxpDriverException;
import org.argeo.ajaxplorer.jdrivers.file.FileLsAction;

public class SvnLsAction extends FileLsAction<SvnDriver, SvnAjxpFile> {

	@Override
	protected List<SvnAjxpFile> listFiles(SvnDriver driver, final String path,
			final boolean dirOnly) {
		try {
			File dir = driver.getFile(path);
			SVNWCClient client = driver.getManager().getWCClient();

			final List<SvnAjxpFile> res = new Vector<SvnAjxpFile>();
			FileFilter filter = createFileFilter(dir);
			File[] files = dir.listFiles(filter);
			for (File file : files) {
				SVNInfo info = client.doInfo(file, SVNRevision.WORKING);
				if (dirOnly) {
					if (file.isDirectory())
						res.add(new SvnAjxpFile(info, path));
				} else {
					res.add(new SvnAjxpFile(info, path));
				}
			}
			return res;
		} catch (SVNException e) {
			throw new AjxpDriverException("Cannot list svn dir " + path);
		}
	}

	@Override
	protected FileFilter createFileFilter(File dir) {
		return new FileFilter() {

			public boolean accept(File pathname) {
				if (pathname.getName().equals(".svn")) {
					return false;
				} else {
					return true;
				}
			}

		};
	}

}
