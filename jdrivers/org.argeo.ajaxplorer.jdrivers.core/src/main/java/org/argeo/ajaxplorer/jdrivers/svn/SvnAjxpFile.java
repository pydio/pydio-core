package org.argeo.ajaxplorer.jdrivers.svn;

import org.tmatesoft.svn.core.wc.SVNInfo;

import org.argeo.ajaxplorer.jdrivers.file.AjxpFile;
import org.argeo.ajaxplorer.jdrivers.file.LsMode;

public class SvnAjxpFile extends AjxpFile {

	protected final SVNInfo info;

	public SvnAjxpFile(SVNInfo info, String parentPath) {
		super(info.getFile(), parentPath);
		this.info = info;
	}

	@Override
	protected void addAdditionalAttrs(StringBuffer buf, LsMode mode,
			String encoding) {
		addAttr("author", info.getAuthor(), buf);
		addAttr("revision", Long.toString(info.getRevision().getNumber()), buf);
	}

}
