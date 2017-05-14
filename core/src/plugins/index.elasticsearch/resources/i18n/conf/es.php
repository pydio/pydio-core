<?php
/*
* Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
* The latest code can be found at <https://pydio.com>.
*/
$mess=array(
"ElasticSearch Search Engine" => "Motor de Búsqueda ElasticSearch",
"ElasticSearch implementation to index all files and search a whole repository quickly." => "Implementa de ElasticSearch para indexar todos los archivos de un repositorio rápidamente.",
"Max results displayed" => "Máximo número de resultados",
"Set the maximum results that will be displayed." => "Configura el número máximo de resultados que se muestran.",
"Index Content" => "Indexado de contenido",
"Parses the file when possible and index its content (see plugin global options)" => "Indexa el contenido del archivo cuando es posible (ver configuración global del plugin)",
"Index Meta Fields" => "Indexa Meta Campos",
"Which additionnal fields to index and search" => "Qué campos adicionales se indexan y buscan",
"Repository keywords" => "Palabras clave del repositorio",
"If your repository path is defined dynamically by specific keywords like AJXP_USER, or your own, mention them here." => "Si la ruta del workspace esta definida dinámicamente con variables como AJXP_USER o propias, introducelas aquí.",
"Parse Content Until" => "Tamaño max. Idexado",
"Skip content parsing and indexation for files bigger than this size (must be in Bytes)" => "Ignora indexado de contenido en archivos mayores a este tamaño (en Bytes)",
"HTML files" => "Archivos HTML",
"List of extensions to consider as HTML file and parse content" => "Lista de extensiones para considerar un archivo como HTML y leer su contenido",
"Text files" => "Archivos de texto",
"List of extensions to consider as Text file and parse content" => "Lista de extensiones para considerar un archivo como archivo de texto",
"Unoconv Path" => "Ruta a Unoconv",
"Full path on the server to the 'unoconv' binary" => "Ruta completa en el servidor al binario 'unoconv'",
"PdftoText Path" => "Ruta a PdftoText",
"Full path on the server to the 'pdftotext' binary" => "Ruta completa en el servidor al binario'pdftotext'",
"Query Analyzer" => "Analizador de Consulta",
"Analyzer used by Zend to parse the queries. Warning, the UTF8 analyzers require the php mbstring extension." => "Analizador usado por Zend para leer las consultas. Atención, los analizadores UTF8 necesitan la extension php mbstring.",
"Wildcard limitation" => "Limite de Wildcard",
"For the sake of performances, it is not recommanded to use wildcard as a very first character of a query string. Lucene recommends asking the user minimum 3 characters before wildcard. Still, you can set it to 0 if necessary for your usecases." => "Por cuestión de rendimiento, no es recomendable usar wildcards como el primer carácter de la consulta. Lucene recomienda pedir al usario tres carácteres como mínimo. Aun así, se puede configurar como 0 si es necesario.",
"Auto-Wildcard" => "Auto-Wildcard",
"Automatically append a * after the user query to make the search broader" => "Añade automáticamente * al final de la consulta para ampliar la búsqueda",
"ElasticSearch Host" => "ElasticSearch Host",
"ElasticSearch Server host (without http)" => "ElasticSearch Server host (sin http)",
"ElasticSearch Port" => "Puerto ElasticSearch",
"ElasticSearch Server port (default 9200)" => "Puerto de ElasticSearch Server (por defecto 9200)",
);
