<?php
/*
* Copyright 2007-2017 Charles du Jeu <contact (at) cdujeu.me>
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
"Message Queuing" => "Message Queuing",
"MQ Abstraction for dynamic dispatching" => "MQ Abstraction for dynamic dispatching",
"Inner Messaging" => "Inner Messaging",
"MQ Instance" => "MQ Instanc",
"Choose the plugin" => "Choose the plugin",
"Post the notification in a temporary queue. You must set up the scheduler accordingly to make sure the queue is then consumed on a regularly basis." => "Post the notification in a temporary queue. You must set up the scheduler accordingly to make sure the queue is then consumed on a regularly basis.",
"Queue notifications" => "Queue notifications",
"WebSocket Server" => "WebSocket Server",
"WebSocket server is running" => "WebSocket server is running",
"WebSocket" => "WebSocket",
"WebSocket client connect address" => "WebSocket client connect address",
"WS Client Address" => "WS Client Address",
"WebSocket client connect port" => "WebSocket client connect port",
"WS Client Port" => "WS Client Port",
"WebSocket client secure connection" => "WebSocket client secure connection",
"WS Client SSL" => "WS Client SSL",
"WebSocket server bind address" => "WebSocket server bind address",
"WS Server Host" => "WS Server Host",
"WebSocket server bind port" => "WebSocket server bind port",
"WS Server Port" => "WS Server Port",
"WebSocket handler path" => "WebSocket handler path",
"WS Path" => "WS Path",
"WebSocket admin key" => "WebSocket admin key",
"WS Key" => "WS Key",
"WebSocket Server Status" => "WebSocket Server Status",
"Try to detect if the server is running correctly" => "Try to detect if the server is running correctly",
"Run WebSocket Server" => "Run WebSocket Server",
"Switch the WS server ON" => "Switch the WS server ON",
"Stop WebSocket Server" => "Stop WebSocket Server",
"Switch the WS server OFF" => "Switch the WS server OFF",
"Alternative poller frequency" => "Alternative poller frequency",
"If WebSocket server is not running, a polling mechanism will replace it. Fix the frequency of refresh, in seconds." => "If WebSocket server is not running, a polling mechanism will replace it. Fix the frequency of refresh, in seconds.",
"Main Configurations" => "Main Configurations",
"WebSocket Server provides a server-to-client messaging feature, avoiding regular polling from browsers and sync applications, thus reducing the server load." => "WebSocket Server provides a server-to-client messaging feature, avoiding regular polling from browsers and sync applications, thus reducing the server load.",
"Pydio Upload Server is a unique feature used to delegate file uploads to our dedicated binary, that will send the files directly to the storage." => "Pydio Upload Server is a unique feature used to delegate file uploads to our dedicated binary, that will send the files directly to the storage.",
"Upload Server" => "Upload Server",
"Host" => "Host",
"Port" => "Port",
"SSL" => "SSL",
"Use secure connection" => "Use secure connection",
"NSQ Host" => "NSQ Host",
"NSQ Host for internal messaging. Leave default value if booster is on the same server" => "NSQ Host for internal messaging. Leave default value if booster is on the same server",
"NSQ Port" => "NSQ Port",
"NSQ Port for internal messaging" => "NSQ Port for internal messaging",
"Internal Connection" => "Internal Connection",
"If different from default host/port" => "If different from default host/port",
"Internal Host (if LAN IP is different from outside-world IP)." => "Internal Host (if LAN IP is different from outside-world IP).",
"Internal Host" => "Internal Host",
"Internal Port (if LAN Port is different from outside-world Port)." => "Internal Port (if LAN Port is different from outside-world Port).",
"Server Internal URL" => "Server Internal URL",
"Use this if the booster needs to communicate with the server through a different host/port than the value defined in Application Core Options" => "Use this if the booster needs to communicate with the server through a different host/port than the value defined in Application Core Options",
"Pydio Server internal URL" => "Pydio Server internal URL",
"Pydio Server URL, if LAN IP is different from outside-world IP. Set up full http url here, including http:// or https://" => "Pydio Server URL, if LAN IP is different from outside-world IP. Set up full http url here, including http:// or https://",
"Advanced Configurations" => "Advanced Configurations",
"Websocket Network Configuration" => "Websocket Network Configuration",
"Use custom URL's for websocket feature" => "Use custom URL's for websocket feature",
"WebSocket host" => "WebSocket host",
"WS Hostname" => "WS Hostname",
"WebSocket port" => "WebSocket port",
"WS Port" => "WS Port",
"WebSocket host (internal)" => "WebSocket host (internal)",
"WS Hostname (internal)" => "WS Hostname (internal)",
"WebSocket port (internal)" => "WebSocket port (internal)",
"WS Port (internal)" => "WS Port (internal)",
"WebSocket secure" => "WebSocket secure",
"WebSocket path" => "WebSocket path",
"Uploader Network Configuration" => "Uploader Network Configuration",
"Use custom URL's for uploader feature" => "Use custom URL's for uploader feature",
"Upload Server host" => "Upload Server host",
"Upload Server port" => "Upload Server port",
"Upload Server host (internal)" => "Upload Server host (internal)",
"Upload Server port (internal)" => "Upload Server port (internal)",
"Upload Server secure" => "Upload Server secure",
"Upload Server path" => "Upload Server path",
"Server Internals" => "Server Internals",
"Choose the plugin, sql should be the default value" => "Choose the plugin, sql should be the default value",
"Use Workers (experimental)" => "Use Workers (experimental)",
"Send commands in background to workers waiting to execute them" => "Send commands in background to workers waiting to execute them",
"Worker Status" => "Worker Status",
"Try to detect if the worker is responding" => "Try to detect if the worker is responding",
"Start Worker" => "Start Worker",
"Switch a worker ON" => "Switch a worker ON",
"Stop Worker" => "Stop Worker",
"Switch a worker OFF" => "Switch a worker OFF",
"Administrative API Key" => "Administrative API Key",
"Pydio Booster uses an administrative API key pair to communicate with the server. If you start it manually, use the buttons below to generate or revoke key pairs. If you start using the admin dashboard, you should not need that." => "Pydio Booster uses an administrative API key pair to communicate with the server. If you start it manually, use the buttons below to generate or revoke key pairs. If you start using the admin dashboard, you should not need that.",
"Generate Key" => "Generate Key",
"Generate API Key" => "Generate API Key",
"Revoke existing API Key(s)" => "Revoke existing API Key(s)",
"Revoke Key" => "Revoke Key",
"Customize URL's depending on the features." => "Customize URL's depending on the features.",
"Pydio Booster uses an administrative API key pair to communicate with the server. If you start it manually, use the buttons below to generate or revoke key pairs. If you start using the admin dashboard, you should not need that." => "Pydio Booster uses an administrative API key pair to communicate with the server. If you start it manually, use the buttons below to generate or revoke key pairs. If you start using the admin dashboard, you should not need that.",
"Same as external" => "Same as external",
"Custom" => "Custom",
"Same as application" => "Same as application",
"Same as external" => "Same as external",
"Use main configurations" => "Use main configurations",
"Customize" => "Customize",
);
