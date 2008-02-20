package org.argeo.ajaxplorer.jdrivers.file;

import java.io.File;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;

import org.argeo.ajaxplorer.jdrivers.AjxpAnswer;

public class FileDeleteAction<T extends FileDriver> extends FileAction {

	public AjxpAnswer execute(FileDriver driver, HttpServletRequest request) {
		Map<Object, Object> params = request.getParameterMap();
		for (Object paramKey : params.keySet()) {
			String param = paramKey.toString();
			log.debug("param=" + param + " (" + params.get(paramKey));
			if (param.length() < 4)
				continue;
			else {

				if (param.substring(0, 4).equals("file")) {
					String[] values = (String[]) params.get(paramKey);
					for (String path : values) {
						File file = driver.getFile(path);
						executeDelete((T) driver, file);
					}
				}
			}
		}

		return AjxpAnswer.DO_NOTHING;
	}

	protected void executeDelete(T driver, File file) {
		log.debug("Delete file " + file);
		file.delete();
	}

}
