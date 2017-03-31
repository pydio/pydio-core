/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com/>.
 *
 */
/**
 * Utilitary class for manipulating file/folders pathes
 */
export default class PathUtils{

    static getBasename(fileName)
    {
        if(fileName == null) return null;
        var separator = "/";
        if(fileName.indexOf("\\") != -1) separator = "\\";
        return fileName.substr(fileName.lastIndexOf(separator)+1, fileName.length);
    }

    static getDirname(fileName)
    {
        return fileName.substr(0, fileName.lastIndexOf("/"));
    }

    static getAjxpMimeType(item){
        if(!item) return "";
        if(item instanceof Map){
            return (item.get('ajxp_mime') || PathUtils.getFileExtension(item.get('filename')));
        }else if(item.getMetadata){
            return (item.getMetadata().get('ajxp_mime') || PathUtils.getFileExtension(item.getPath()));
        }else{
            return (item.getAttribute('ajxp_mime') || PathUtils.getFileExtension(item.getAttribute('filename')));
        }
    }

    static getFileExtension(fileName)
    {
        if(!fileName || fileName == "") return "";
        var split = PathUtils.getBasename(fileName).split('.');
        if(split.length > 1) return split[split.length-1].toLowerCase();
        return '';
    }

    static roundFileSize(filesize, size_unit="o"){
        if (filesize >= 1073741824) {filesize = Math.round(filesize / 1073741824 * 100) / 100 + " G"+size_unit;}
        else if (filesize >= 1048576) {filesize = Math.round(filesize / 1048576 * 100) / 100 + " M"+size_unit;}
        else if (filesize >= 1024) {filesize = Math.round(filesize / 1024 * 100) / 100 + " K"+size_unit;}
        else {filesize = filesize + " "+size_unit;}
        return filesize;
    }

    /**
     *
     * @param dateObject Date
     * @param format String
     * @returns {*}
     */
    static formatModifDate(dateObject, format){
        if(!format && window && window.pydio && pydio.MessageHash) {
            format = pydio.MessageHash["date_format"];
        }
        if(!format) return 'no format';
        format = format.replace("d", (dateObject.getDate()<10?'0'+dateObject.getDate():dateObject.getDate()));
        format = format.replace("D", dateObject.getDay());
        format = format.replace("Y", dateObject.getFullYear());
        format = format.replace("y", dateObject.getYear());
        var month = dateObject.getMonth() + 1;
        format = format.replace("m", (month<10?'0'+month:month));
        format = format.replace("H", (dateObject.getHours()<10?'0':'')+dateObject.getHours());
        // Support 12 hour format compatibility
        format = format.replace("h", (dateObject.getHours() % 12 || 12));
        format = format.replace("p", (dateObject.getHours() < 12 ? "am" : "pm"));
        format = format.replace("P", (dateObject.getHours() < 12 ? "AM" : "PM"));
        format = format.replace("i", (dateObject.getMinutes()<10?'0':'')+dateObject.getMinutes());
        format = format.replace("s", (dateObject.getSeconds()<10?'0':'')+dateObject.getSeconds());
        return format;
    }

}