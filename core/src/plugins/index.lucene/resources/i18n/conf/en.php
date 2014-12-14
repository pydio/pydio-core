<?php
/*
* Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
* The latest code can be found at <http://pyd.io/>.
*/
$mess=array(
"Lucene Search Engine" => "Lucene Search Engine",
"Zend_Search_Lucene implementation to index all files and search a whole repository quickly." => "Zend_Search_Lucene implementation to index all files and search a whole workspace quickly.",
"Index Content" => "Index Content",
"Parses the file when possible and index its content (see plugin global options)" => "Parses the file when possible and index its content (see plugin global options)",
"Index Meta Fields" => "Index Meta Fields",
"Which additionnal fields to index and search" => "Which additionnal fields to index and search",
"Repository keywords" => "Repository keywords",
"If your repository path is defined dynamically by specific keywords like AJXP_USER, or your own, mention them here." => "If your workspace path is defined dynamically by specific keywords like AJXP_USER, or your own, mention them here.",
"Parse Content Until" => "Parse Content Until",
"Skip content parsing and indexation for files bigger than this size (must be in Bytes)" => "Skip content parsing and indexation for files bigger than this size (must be in Bytes)",
"HTML files" => "HTML files",
"List of extensions to consider as HTML file and parse content" => "List of extensions to consider as HTML file and parse content",
"Text files" => "Text files",
"List of extensions to consider as Text file and parse content" => "List of extensions to consider as Text file and parse content",
"Unoconv Path" => "Unoconv Path",
"Full path on the server to the 'unoconv' binary" => "Full path on the server to the 'unoconv' binary",
"PdftoText Path" => "PdftoText Path",
"Full path on the server to the 'pdftotext' binary" => "Full path on the server to the 'pdftotext' binary",
"Query Analyzer" => "Query Analyzer",
"Analyzer used by Zend to parse the queries. Warning, the UTF8 analyzers require the php mbstring extension." => "Analyzer used by Zend to parse the queries. Warning, the UTF8 analyzers require the php mbstring extension.",
"Wildcard limitation" => "Wildcard limitation",
"For the sake of performances, it is not recommanded to use wildcard as a very first character of a query string. Lucene recommends asking the user minimum 3 characters before wildcard. Still, you can set it to 0 if necessary for your usecases." => "For the sake of performances, it is not recommanded to use wildcard as a very first character of a query string. Lucene recommends asking the user minimum 3 characters before wildcard. Still, you can set it to 0 if necessary for your usecases.",
"Auto-Wildcard" => "Auto-Wildcard",
"Automatically append a * after the user query to make the search broader" => "Automatically append a * after the user query to make the search broader",
);