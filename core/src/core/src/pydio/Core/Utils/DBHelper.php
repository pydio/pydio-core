<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 */
namespace Pydio\Core\Utils;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Generic SQL utils
 * @package Pydio\Core\Utils
 */
class DBHelper
{

    /**
     * @param $p
     * @param $file
     * @return string
     */
    public static function runCreateTablesQuery($p, $file)
    {

        switch ($p["driver"]) {
            case "sqlite":
            case "sqlite3":
                if (!file_exists(dirname($p["database"]))) {
                    @mkdir(dirname($p["database"]), 0755, true);
                }
                $ext = ".sqlite";
                break;
            case "mysql":
                $ext = ".mysql";
                break;
            case "postgre":
                $ext = ".pgsql";
                break;
            default:
                return "ERROR!, DB driver " . $p["driver"] . " not supported yet in __FUNCTION__";
        }

        $result = array();
        $file = dirname($file) . "/" . str_replace(".sql", $ext, basename($file));
        $sql = file_get_contents($file);
        $separators = explode("/** SEPARATOR **/", $sql);

        $allParts = array();

        foreach ($separators as $sep) {
            $explode = explode("\n", trim($sep));
            $firstLine = array_shift($explode);
            if ($firstLine == "/** BLOCK **/") {
                $allParts[] = $sep;
            } else {
                $parts = explode(";", $sep);
                $remove = array();
                $count = count($parts);
                for ($i = 0; $i < $count; $i++) {
                    $part = $parts[$i];
                    if (strpos($part, "BEGIN") && isSet($parts[$i + 1])) {
                        $parts[$i] .= ';' . $parts[$i + 1];
                        $remove[] = $i + 1;
                    }
                }
                foreach ($remove as $rk) unset($parts[$rk]);
                $allParts = array_merge($allParts, $parts);
            }
        }
        \dibi::connect($p);
        \dibi::begin();
        foreach ($allParts as $createPart) {
            $sqlPart = trim($createPart);
            if (empty($sqlPart)) continue;
            try {
                \dibi::nativeQuery($sqlPart);
                $resKey = str_replace("\n", "", substr($sqlPart, 0, 50)) . "...";
                $result[] = "OK: $resKey executed successfully";
            } catch (\DibiException $e) {
                $result[] = "ERROR! $sqlPart failed";
            }
        }
        \dibi::commit();
        \dibi::disconnect();
        $message = implode("\n", $result);
        if (strpos($message, "ERROR!")) return $message;
        else return "SUCCESS:" . $message;
    }
}