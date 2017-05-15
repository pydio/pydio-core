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
  class AjxpParseSvnSummarize extends Task
  {
      private $summarizeFile;
      private $sourceDir;
      private $upgradeDir;
      private $prefixPath;
      private $extPluginsFolder;
      private $ignores = array("/.gitignore");

      public function setSummarizeFile($summarizeFile)
      {
          $this->summarizeFile = $summarizeFile;
      }

      public function getSummarizeFile()
      {
          return $this->summarizeFile;
      }

      public function setSourceDir($sourceDir)
      {
          $this->sourceDir = $sourceDir;
      }

      public function setUpgradeDir($upgradeDir)
      {
          $this->upgradeDir = $upgradeDir;
      }

      public function setExtPluginsFolder($extPluginsFolder)
      {
            $this->extPluginsFolder = $extPluginsFolder;
      }

      public function setPrefixPath($prefixPath)
      {
          $this->prefixPath = $prefixPath;
      }

      public function getPrefixPath()
      {
          return $this->prefixPath;
      }

      public function main()
      {
          $summarizeLines = file($this->summarizeFile);
          $toDelete = array();
          if(isSet($this->prefixPath)) $this->sourceDir .= "/" . $this->prefixPath;

          foreach ($summarizeLines as $line) {
              list($letter, $path) = preg_split('/[\s]+/', trim($line), 2);
              if (isSet($this->prefixPath)) {
                  if (strpos($path, $this->prefixPath) !== 0) {
                      $this->log("-- Skipping ".$path, Project::MSG_INFO);
                      continue;
                  }
                  $end = str_replace($this->prefixPath, "", $path);
              } else {
                  $end = str_replace($this->sourceDir, '', $path);
              }
              if (in_array($end, $this->ignores)) {
                  continue;
              }
              //$this->log("-- Parsing ".$line, Project::MSG_INFO);

              if ($letter == "D") {
                  $toDelete[] = $end;
                  continue;
              }
               if (substr($end, 0, strlen("/plugins/"))=="/plugins/") {
                   if (file_exists($this->extPluginsFolder.substr($end, strlen("/plugins")))) {
                    $this->log("-- Skipping ".$line.", it's an external plugin", Project::MSG_INFO);
                       continue;
                   }
               }
              if (is_dir($this->sourceDir.$end)) {
                  if(!is_dir($this->upgradeDir.$end)) mkdir($this->upgradeDir.$end, 0777, true);
              } else if (is_file($this->sourceDir.$end)) {
                  if(!is_dir($this->upgradeDir."/".dirname($end))) mkdir($this->upgradeDir."/".dirname($end), 0777, true);
                  $this->log("-- Copy ".$this->sourceDir.$end ." to ".$this->upgradeDir.$end, Project::MSG_INFO);
                  copy($this->sourceDir.$end, $this->upgradeDir.$end);
              }
          }
          if (count($toDelete)) {
              $this->log("-- Adding CLEAN-FILES list for ".count($toDelete)." items to delete", Project::MSG_INFO);
              file_put_contents($this->upgradeDir."/UPGRADE/CLEAN-FILES", implode("\r\n", str_replace("\\", "/", $toDelete)));
          }
      }

      function copy_r( $path, $dest )
      {
          if ( is_dir($path) ) {
              @mkdir( $dest , 0777);
              $objects = scandir($path);
              if ( sizeof($objects) > 0 ) {
                  foreach ($objects as $file) {
                      if( $file == "." || $file == ".." )
                      continue;
                      // go on
                      if ( is_dir( $path.DIRECTORY_SEPARATOR.$file ) ) {
                          self::copy_r( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                      } else {
                          copy( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                      }
                  }
              }
              return true;
          } elseif ( is_file($path) ) {
              return copy($path, $dest);
          } else {
              return false;
          }
      }

  }
