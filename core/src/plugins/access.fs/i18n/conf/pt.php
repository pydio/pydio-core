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
"File System (Standard)" => "Sistema de Ficheiros (padrão)",
"The most standard access to a filesystem located on the server." => "O tipo de acesso padrão para aceder ao sistema de ficheiros do servidor.",
"Path" => "Caminho",
"Real path to the root folder on the server" => "Caminho Real para a raíz da pasta no servidor",
"Create" => "Criar",
"Create folder if it does not exists" => "Criar pasta caso esta não exista",
"File Creation Mask" => "Máscara de criação de Ficheiro",
"Optionnaly apply a chmod operation. Value must be numeric, like 0777, 0644, etc." => "Opcionalmente utilizar o 'chmod' para aplicar permissões. Os valores devem ser numéricos, por exemplo: 0777, 0644, etc.",
"Purge Days" => "Dias de Limpeza",
"Option to purge documents after a given number of days. This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opção para limpar os documentos após um certo número de dias. Requer configurar manualmente uma tarefa cronológica (CRON). Deixe o valor 0  caso não pretenda utilizar esta função.",
"Real Size Probing" => "Tamanho Real de Teste",
"Use system command line to get the filesize instead of php built-in function (fixes the 2Go limitation)" => "Utilizar a linha de comandos para obter um tamanho de ficheiro em vez da função incorporada no PHP (corrige o limite de 2GB)",
"X-SendFile Active" => "Activar X-SendFile",
"Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)" => "Delega todas as operações de transferência para o servidor utilizado o X-SendFile header. ATENÇÃO: Este é um módulo externo que tem que ser instalado no Apache. Este módulo encontra-se activado por pré definição no Lighttpd. ATENÇÃO: Tem que manualmente adicionar as pastas onde os ficheiros serão transferidos na configuração do módulo (Na pasta XSendFilePath)",
"Data template" => "Modelo de Dados",
"Path to a directory on the filesystem whose content will be copied to the repository the first time it is loaded." => "Caminho para a pasta no sistema de ficheiros cujo conteúdos serão copiados para a Área de Trabalho na primeira vez que esta for carregada.",
"Purge Days (Hard limit)" => "Purge Days (Hard limit)",
"Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature.",
"Purge Days (Soft limit)" => "Purge Days (Soft limit)",
"Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature.",
"Remote Sorting" => "Remote Sorting",
"Force remote sorting when in paginated mode. Warning, this can impact the performances." => "Force remote sorting when in paginated mode. Warning, this can impact the performances.",
"Use POSIX" => "Use POSIX",
"Use php POSIX extension to read files permissions. Only works on *nix systems." => "Use php POSIX extension to read files permissions. Only works on *nix systems.",
"X-Accel-Redirect Active" => "X-Accel-Redirect Active",
"Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping" => "Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping",
);