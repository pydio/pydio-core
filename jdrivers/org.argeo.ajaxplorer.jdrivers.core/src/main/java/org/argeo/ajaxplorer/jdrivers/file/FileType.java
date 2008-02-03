package org.argeo.ajaxplorer.jdrivers.file;

import org.argeo.ajaxplorer.jdrivers.AxpDriverException;

public enum FileType {
	FOLDER("folder.png", "Directory"), UNKNOWN("mime_empty.png", "Unkown"), GIF(
			"image.png", "GIF Picture"), JPEG("image.png", "JPEG Picture"), PNG(
			"image.png", "PNG Picture");

	private final String icon;
	private final String mimeString;

	FileType(String icon, String mimeString) {
		this.icon = icon;
		this.mimeString = mimeString;
	}

	public String getIcon() {
		return icon;
	}

	public String getMimeString() {
		return mimeString;
	}

	public boolean isImage() {
		return this == GIF || this == JPEG || this == PNG;
	}

	public String getImageType() {
		switch (this) {
		case GIF:
			return "image/gif";
		case JPEG:
			return "image/jpeg";
		case PNG:
			return "image/png";
		}
		throw new AxpDriverException("Image type undefined for " + this);
	}

	/**
	 * Find the type based on the extension.
	 * 
	 * @param ext
	 *            the extension, null for a directory
	 */
	public static FileType findType(String extArg) {
		if (extArg == null)
			return FOLDER;

		String ext = extArg.toLowerCase();
		if (ext.equals("jpg") || ext.equals("jpeg"))
			return JPEG;
		else if (ext.equals("gif"))
			return GIF;
		else if (ext.equals("png"))
			return PNG;
		else
			return UNKNOWN;
	}
}
