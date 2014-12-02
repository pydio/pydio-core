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
"Lucene Search Engine" => "Lucene Suchmaschine",
"Zend_Search_Lucene implementation to index all files and search a whole repository quickly." => "Zend_Search_Lucene Implementierung um alle Dateien zu indexieren und die Arbeitsumgebung schnell zu durchsuchen.",
"Index Content" => "Inhalt indexieren",
"Parses the file when possible and index its content (see plugin global options)" => "Falls möglich den Inhalt der Dateien analysieren und indexieren (siehe globale Einstellungen)",
"Index Meta Fields" => "Metafelder indexieren",
"Which additionnal fields to index and search" => "Welche Metafelder werden indexiert",
"Repository keywords" => "Parameter der Arbeitsumgebung",
"If your repository path is defined dynamically by specific keywords like AJXP_USER, or your own, mention them here." => "Wenn der Ordner zur Arbeitsumgebung dynamisch generiert wird müssen die Parameter hier angegeben werden. (z.B. AJXP_USER, oder selbst angelegte)",
"Parse Content Until" => "Werkzeug zum analysieren des Inhalts",
"Skip content parsing and indexation for files bigger than this size (must be in Bytes)" => "Keine Analyse des Inhalts wenn die Datei größer ist als die hier angegebene Größe in Byte.",
"HTML files" => "HTML Dateien",
"List of extensions to consider as HTML file and parse content" => "Liste von Dateiendungen, die bei der Analyse als HTML-Datei behandelt werden",
"Text files" => "Text Dateien",
"List of extensions to consider as Text file and parse content" => "Liste von Dateiendungen, die bei der Analyse als Text-Datei behandelt werden",
"Unoconv Path" => "Unoconv Pfad",
"Full path on the server to the 'unoconv' binary" => "Absoluter Pfad zur ausführbaren Binärdatei 'unoconv'",
"PdftoText Path" => "PdftoText Pfad",
"Full path on the server to the 'pdftotext' binary" => "Absoluter Pfad zur ausführbaren Binärdatei 'pdftotext'",
"Query Analyzer" => "Anfrage-Analysator",
"Analyzer used by Zend to parse the queries. Warning, the UTF8 analyzers require the php mbstring extension." => "Analysator den Zend verwendet um Anfragen zu analysieren. Warnung: Der UTF8 Analysator benötigt die PHP-Erweiterung 'mbstring'.",
"Wildcard limitation" => "Wildcard ab",
"For the sake of performances, it is not recommanded to use wildcard as a very first character of a query string. Lucene recommends asking the user minimum 3 characters before wildcard. Still, you can set it to 0 if necessary for your usecases." => "Um die Geschwindigkeit nicht negativ zu beeinflussen sollten Wildcards nicht als erstes Zeichen verwendet werden. Lucerne empfiehlt, dass vom Benutzer mindestens drei Zeichen verlangt werden, bevor ein Wildcard möglich ist. Trotzdem wäre es hier möglich 0 einzutragen, wenn dies für Ihren Anwendungsfall nötig ist.",
);
