package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;

import javax.servlet.http.HttpServletRequest;

import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;

public class FileMkdirAction extends FileAction {

	public AjxpAnswer execute(HttpServletRequest request) {
		String dir = request.getParameter("dir");
		String dirName = request.getParameter("dirname");

		File newDir = getFileDriverContext().getFile(dir, dirName);
		newDir.mkdirs();

		postProcess(newDir);
		
		return AjxpAnswer.DO_NOTHING;
	}

	
	protected void postProcess(File newDir){
		
	}
}
