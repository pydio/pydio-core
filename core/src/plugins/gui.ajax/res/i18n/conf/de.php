<?php
/*
* Copyright 2007-2014 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

/* *****************************************************************************
* Initial translation:  Martin Schaible <martin@martinschaible.ch>
***************************************************************************** */ 

/* Do not use HTML entities! It would mess up everything */ 
$mess=array(
"Client Plugin" => "Client-Plugin",
"Browser-based rich interface. Contains configurations for theming, custom welcome message, etc." => "Browser-basierende Oberfläche. Beinhaltet die Konfiguraton der Themen, kundenspezifische Willkommens-Mitteilung, usw.",
"Main Options" => "Haupt-Optionen",
"Theme" => "Theme",
"Theme used for display" => "Theme für die Anzeige der Oberfläche",
"Start Up Screen" => "Start Up Screen",
"Title Font Size" => "Schriftgrösse des Titels",
"Font sized used for the title in the start up screen" => "Schriftgrösse des Titels auf der Anmelde-Seite",
"Custom Icon" => "Eigenes Bild",
"URI to a custom image to be used as start up logo" => "URI zu einem eigenen Bild für die Anmelde-Seite",
"Icon Width" => "Breite des Bildes",
"Width of the custom image (by default 35px)" => "Breite des Bildes (Standard 35px)",
"Welcome Message" => "Willkommens-Mitteilung",
"An additionnal message displayed in the start up screen" => "Eine zusätzliche Mitteilung für die Anmelde-Seite",
"Client Session Config" => "Konfiguration der Client-Session",
"Client Timeout" => "Client-Timeout",
"The length of the client session in SECONDS. By default, it's copying the server session length. In most PHP installation, it will be 1440, ie 24minutes. You can set this value to 0, this will make the client session 'infinite' by pinging the server at regular occasions (thus keeping the PHP session alive). This is not a recommanded setting for evident security reasons." => "Die Länge der Client-Session in Sekunden. Standardmässig wird die Session-Länge des Servers genutzt, welche bei den meisten PHP-installationen 1440 Sekunden (24 Minuten) beträgt. Sie können den Wert auf 0 setzen, was eine unendlich lange Client-Session ergibt. In diesem Fall wird der Server regelmässig angepingt und die PHP-Session bleibt bestehen). Diese Einstellung ist aus Sicherheitsgründen nicht empfehlenswert.",
"Warning Before" => "Warnung bevor die Session abläuft",
"Number of MINUTES before the session expiration for issuing an alert to the user" => "Anzahl von Minuten vor dem Ablauf der Session, bevor dem Benutzer eine Warnung ausgeben wird",
"Google Analytics" => "Google Analytics",
"Analytics ID" => "Analytics-ID",
"Id of your GA account, something like UE-XXXX-YY" => "ID Ihres GA-Kontos (Beispiel: UE-XXXX-YY)",
"Analytics Domain" => "Analytics-Domäne",
"Set the domain for your analytics reports (not mandatory!)" => "Setzen Sie die Domain für Ihre Analytics-Berichte (Nicht zwingend!)",
"Analytics Events" => "Analytics Ereignisse",
"Use Events Logging, experimental only implemented on download action in Pydio" => "Ereigniss-Protokollierung, noch im Expermental-Status und nur für die Download-Aktion von Pydio implementiert.",
"Icon Only" => "Nur mit Bild",
"Skip the title, only display an image" => "Nur das Bild wird angezeigt. Der Titel wird ausgeblended",
"Icon Path (Legacy)" => "Bildpfad (Legacy)",
"Icon Height" => "Höhe des Bildes",
"Height of the custom icon (with the px mention)" => "Höhe des Bildes (px als Einheit angeben)",
"Top Toolbar" => "Obere Werkzeugleiste",
"Title" => "Titel",
"Append a title to the image logo" => "Ein Titel für das Logo hinzufügen",
"Logo" => "Logo",
"Replace the top left logo in the top toolbar" => "Ersatz für das obere linke Logo der Werkzeugleiste",
"Logo Height" => "Höhe des Logos",
"Manually set a logo height" => "Die Höhe des Logos setzen",
"Logo Width" => "Breite des Logos",
"Manually set a logo width" => "Die Breite des Logos setzen",
"Logo Top" => "Oberer Abstand des Logos",
"Manually set a top offset" => "Einen zusätzlichen oberen Abstand setzen",
"Logo Left" => "Linker Abstand des Logos",
"Manually set a left offset" => "Einen zusätzlichen linken Abstand setzen",
"Additional JS frameworks" => "Zusätzliche JS-Frameworks",
"Additional JS frameworks description" => "Ein Komma-delimitierte Liste von Pfaden zu JS-Dateien, welche VOR dem Start des Pydio-Frameworks geladen werden müssen. Der bevorzugte Weg ist, dass Ressourcen-Deklarationen von Plugins genutzt werden sollen. In einigen Fällen kann es Sinn manchen, JS-Dateien beim Start der Seite zu laden.",
"Login Screen" => "Anmelde-Seite",
"Welcome Page" => "Willkommen-Seite",
"Replace the logo displayed in the welcome page" => "Ersatz für das Logo auf der Willkommen-Seite",
"Custom Background (4)" => "Eigener Hintergrund (4)",
"Background Attributes (4)" => "Eigenschaften des Hintergrundes (4)",
"Custom Background (5)" => "Eigener Hintergrund (5)",
"Background Attributes (5)" => "Eigenschaften des Hintergrundes (5)",
"Custom Background (6)" => "Eigener Hintergrund (6)",
"Background Attributes (6)" => "Eigenschaften des Hintergrundes (6)",
"Attributes of the image used as a background" => "Eigenschaften des Hintergrund-Bildes",
"Center in Page (no-repeat)" => "In Seite zentriert (Nicht-wiederholend)",
"Fetch Window (repeat vertically)" => "Ganzes Fenster (Vertikal wiederholend)",
"Fetch Window (no repeat)" => "Ganzes Fenster (Nicht-wiederholend)",
"Tile (repeat both directions)" => "Nebeneinander (In beide Richtungen wiederholend)",
);
