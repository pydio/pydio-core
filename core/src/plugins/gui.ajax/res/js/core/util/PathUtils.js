"use strict";

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

var PathUtils = (function () {
    function PathUtils() {
        _classCallCheck(this, PathUtils);
    }

    PathUtils.getBasename = function getBasename(fileName) {
        if (fileName == null) return null;
        var separator = "/";
        if (fileName.indexOf("\\") != -1) separator = "\\";
        return fileName.substr(fileName.lastIndexOf(separator) + 1, fileName.length);
    };

    PathUtils.getDirname = function getDirname(fileName) {
        return fileName.substr(0, fileName.lastIndexOf("/"));
    };

    PathUtils.getAjxpMimeType = function getAjxpMimeType(item) {
        if (!item) return "";
        if (item instanceof Map) {
            return item.get("ajxp_mime") || PathUtils.getFileExtension(item.get("filename"));
        } else if (item.getMetadata) {
            return item.getMetadata().get("ajxp_mime") || PathUtils.getFileExtension(item.getPath());
        } else {
            return item.getAttribute("ajxp_mime") || PathUtils.getFileExtension(item.getAttribute("filename"));
        }
    };

    PathUtils.getFileExtension = function getFileExtension(fileName) {
        if (!fileName || fileName == "") return "";
        var split = PathUtils.getBasename(fileName).split(".");
        if (split.length > 1) return split[split.length - 1].toLowerCase();
        return "";
    };

    PathUtils.roundFileSize = function roundFileSize(filesize) {
        var size_unit = arguments[1] === undefined ? "o" : arguments[1];

        if (filesize >= 1073741824) {
            filesize = Math.round(filesize / 1073741824 * 100) / 100 + " G" + size_unit;
        } else if (filesize >= 1048576) {
            filesize = Math.round(filesize / 1048576 * 100) / 100 + " M" + size_unit;
        } else if (filesize >= 1024) {
            filesize = Math.round(filesize / 1024 * 100) / 100 + " K" + size_unit;
        } else {
            filesize = filesize + " " + size_unit;
        }
        return filesize;
    };

    /**
     *
     * @param dateObject Date
     * @param format String
     * @returns {*}
     */

    PathUtils.formatModifDate = function formatModifDate(dateObject, format) {
        if (!format && window && window.pydio && pydio.MessageHash) {
            format = pydio.MessageHash["date_format"];
        }
        if (!format) return "no format";
        format = format.replace("d", dateObject.getDate() < 10 ? "0" + dateObject.getDate() : dateObject.getDate());
        format = format.replace("D", dateObject.getDay());
        format = format.replace("Y", dateObject.getFullYear());
        format = format.replace("y", dateObject.getYear());
        var month = dateObject.getMonth() + 1;
        format = format.replace("m", month < 10 ? "0" + month : month);
        format = format.replace("H", (dateObject.getHours() < 10 ? "0" : "") + dateObject.getHours());
        // Support 12 hour format compatibility
        format = format.replace("h", dateObject.getHours() % 12 || 12);
        format = format.replace("p", dateObject.getHours() < 12 ? "am" : "pm");
        format = format.replace("P", dateObject.getHours() < 12 ? "AM" : "PM");
        format = format.replace("i", (dateObject.getMinutes() < 10 ? "0" : "") + dateObject.getMinutes());
        format = format.replace("s", (dateObject.getSeconds() < 10 ? "0" : "") + dateObject.getSeconds());
        return format;
    };

    return PathUtils;
})();