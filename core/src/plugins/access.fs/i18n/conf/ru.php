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
"File System (Standard)" => "Файловая система (Стандарт)",
"The most standard access to a filesystem located on the server." => "Стандартный доступ к файловой системе на сервере.",
"Path" => "Путь",
"Real path to the root folder on the server" => "Абсолютный путь до корневой папки на сервере",
"Create" => "Создать",
"Create folder if it does not exists" => "Создать папку, если её ещё нет",
"File Creation Mask" => "Маска создания файлов",
"Optionnaly apply a chmod operation. Value must be numeric, like 0777, 0644, etc." => "Опционально, выполнить chmod. Значение должно быть числовым: 0777, 0644 и т. п.",
"Purge Days" => "Дни очистки (purge)",
"Option to purge documents after a given number of days. This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Опция для очистки документов после данного количества дней. Требуется вручную настроить задание в CRON. Оставьте 0, если не хотите использовать эту возможность.",
"Real Size Probing" => "Проба реального размера",
"Use system command line to get the filesize instead of php built-in function (fixes the 2Go limitation)" => "Использовать системную команду для получения размера файла вместо встроенной в php (обход лимита в 2G)",
"X-SendFile Active" => "X-SendFile активен",
"Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)" => "Делегировать все операции скачивания web-серверу через использование заголовка X-SendFile. Внимание, это дополнительный модуль для Apache. Модуль активен по умолчанию в Lighttpd. Предупреждение. Вы должны вручную добавить папки, откуда файлы будут загружаться, в конфигурация модуля (параметр XSendFilePath)",
"Data template" => "Шаблон данных",
"Path to a directory on the filesystem whose content will be copied to the repository the first time it is loaded." => "Путь до каталога в файловой системе, который будет скопирован в рабочее пространство при первом использовании.",
"Purge Days (Hard limit)" => "Дни очистки (Hard limit)",
"Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Опция для очистки документо после указанного количества дней (даже для общих). Требуется вручную настроить задание в CRON. Оставьте 0, если не хотите использовать эту возможность.",
"Purge Days (Soft limit)" => "Дни очистки (Soft limit)",
"Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Опция для очистки документо после указанного количества дней (НЕ для общих). Требуется вручную настроить задание в CRON. Оставьте 0, если не хотите использовать эту возможность.",
"Remote Sorting" => "Удалённая сортировка",
"Force remote sorting when in paginated mode. Warning, this can impact the performances." => "Принудительная удалённая сортировка в режиме разбивки на страницы. Может повлиять на быстродействие.",
"Use POSIX" => "Использовать POSIX",
"Use php POSIX extension to read files permissions. Only works on *nix systems." => "Использовать расширение POSIX для php для чтения прав доступа к файлам. Только для *nix систем.",
"X-Accel-Redirect Active" => "X-Accel-Redirect Активен",
"Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping" => "Делегировать все операции скачивания для nginx с использованием заголовка X-Accel-Redirect. Необходима настройка nginx, например X-Accel-Mapping",
);
