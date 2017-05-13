<?php
/*
 * Copyright 2007-2017 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

include_once 'phing/Task.php';

  /**
   * Selector that filters files based on whether they contain a
   * particular string.
   *
   * @author Hans Lellelid <hans@xmpl.org> (Phing)
   * @author Bruce Atherton <bruce@callenish.com> (Ant)
   * @package phing.types.selectors
   */
  class AjxpPluginDiscriminate extends Task
  {
      private $ext;
      private $all;

      public function setExt($ext)
      {
          $this->ext = $ext;
      }

      public function setAll($all)
      {
          $this->all = $all;
      }


      public function main()
      {
          $results = glob($this->all."/*");
          foreach ($results as $pluginDir) {
              if (!$this->isCore($pluginDir)) {
                  $this->log("Moving ".$pluginDir." to the external plugins", Project::MSG_INFO);
                  rename($pluginDir, $this->ext."/".basename($pluginDir));
              }
          }
      }

      public function isCore($file)
      {
          if(!is_dir($file)) return true;
          if(!file_exists($file."/manifest.xml")) return true;
          $content = file_get_contents($file."/manifest.xml");
          $dom = new DOMDocument();
          $dom->loadXML($content);
          $xpath = new DOMXPath($dom);
          $nodes = $xpath->query("plugin_info/core_relation");
          if ($nodes->length > 0) {
              $att = $nodes->item(0)->attributes->getNamedItem("packaged")->nodeValue;
              if($att == "false") return false;
          }
          return true;


      }

  }
