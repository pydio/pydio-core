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
defined('AJXP_EXEC') or die( 'Access not allowed');

class AJXP_ProgressBarCLI {
    private $strProgress = "";
    private $strLength = 25; // 50 '='
    private $strName   = "";
    private $strNameLengthMax = 20;
    private $total       = 0;
    private $isFinish     = false;
    private $lastPercent = -1;
    private $barSign     = "=";
    private $startPoint ;
    private $lastsecond = 0;

    public function init($currentValue = 0, $total, $name){
        if(!(php_sapi_name() == "cli")) return;
        $this->total        = $total;
        $this->strName      = $name;
        $this->startPoint   = time();
        $this->barSign = "=";
        //echo "\nobjects: ". $this->total."\n";
        $str = '';
        $str .= $this->convertNumToStrBlank($this->strNameLengthMax)."[==";
        $str .= "Total: ".$total." objects";
        $str .= $this->convertNumToStr($this->strLength - strlen($str) + 21);
        $str .= "]";
        echo "\n";
        echo $str;
        echo "\n";
    }

    public function update($currentValue = 0){
        if(!(php_sapi_name() == "cli")) return;
        if($this->isFinish) return;
        $percentage = $this->convertToPercent($currentValue, $this->total);
        if (($percentage != $this->lastPercent) || ((time() - $this->lastsecond) > 0.9)){
            $this->lastPercent = $percentage;
            $this->lastsecond = time();
        }
        else{
            return;
        }
        $strDone = $this->convertNumToStr((int) $percentage/4);
        $this->strProgress .= $strDone;
        $this->strProgress .= $this->convertNumToStrBlank($this->strLength - strlen($strDone));
        $this->addStartString();
        $this->strProgress = $this->formatName($this->strName).$this->strProgress;
        $this->addEndString();
        $this->strProgress .= " ";

        if($percentage < 10){
            $this->strProgress .= "  ";
        }elseif($percentage > 10 && $percentage < 100){
            $this->strProgress .= " ";
        }
        $this->strProgress .= "".$percentage." %  " . $this->ago($this->startPoint);
        $this->clearLine(strlen($this->strProgress) + 2);
        echo $this->strProgress;
        $this->strProgress = "";
        if($percentage == 100){
            echo "\n";
            $this->isFinish = true;
            return ;
        }
    }

    private function addStartString(){
        $this->strProgress = "[".$this->strProgress;
    }

    private function addEndString(){
        $this->strProgress = $this->strProgress."]";
    }

    private function convertToPercent($currentValue = 0, $maxValue){
        if($maxValue > 0 && $currentValue > 0){
            $percent = ( int ) ((100*$currentValue) / $maxValue);
            return $percent;
        }
        elseif($maxValue === $currentValue){
            return 100;
        }
        return 0;
    }

    private function convertNumToStr($num){
        if ($num >= 0 && $num <= $this->strLength){
            $str = "";
            for($i = 0; $i < $num; $i++){
                $str .= $this->barSign;
            }
            return $str;
        }else{
            return "";
        }
    }

    private function formatName($name){
        if(strlen($name) > $this->strNameLengthMax){
            return substr($name, 0, $this->strNameLengthMax);
        }else{
            $str = $name . $this->convertNumToStrBlank($this->strNameLengthMax - strlen($name));
            return $str;
        }
    }

    public function convertNumToStrBlank($num){
        if($num > 0){
            $str = "";
            for($i = 0; $i < $num; $i++){
                $str .= " ";
            }
            return $str;
        }
        return "";
    }

    public function clearLine($lineLength = 80){
        for($i = 0; $i < $lineLength ;$i ++){
            echo "\010";
        }
    }

    public function setName($name){
        $this->strName = $name;
    }

    public function ago($time) {
        $timediff=time()-$time;

        $days=intval($timediff/86400);
        $remain=$timediff%86400;
        $hours=intval($remain/3600);
        $remain=$remain%3600;
        $mins=intval($remain/60);
        $secs=$remain%60;

        if ($secs>=0) $timestring = "0m".$secs."s";
        if ($mins>0) $timestring = $mins."m".$secs."s";
        if ($hours>0) $timestring = $hours."u".$mins."m";
        if ($days>0) $timestring = $days."d".$hours."u";

        return $timestring;
    }
}