package org.argeo.ajaxplorer.jdrivers.svn;

import java.io.File;
import java.io.FileFilter;

import javax.servlet.http.HttpServletRequest;

import org.argeo.ajaxplorer.jdrivers.file.FileLsAction;

public class SvnLsAction extends FileLsAction {

	@Override
	protected FileFilter createFileFilter(HttpServletRequest request, File dir) {
		return new FileFilter(){

			public boolean accept(File pathname) {
				if(pathname.getName().equals(".svn")){
					return false;
				}
				else{
					return true;
				}
			}
			
		};
	}

	
}
