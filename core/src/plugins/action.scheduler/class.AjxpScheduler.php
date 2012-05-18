<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
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

defined('AJXP_EXEC') or die( 'Access not allowed');

class AjxpScheduler extends AJXP_Plugin{



    function switchAction($action, $httpVars, $postProcessData){

        switch($action){

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "run_scheduler":

                $mess = ConfService::getMessages();
                $timeArray = array();
                $timeArray['minutes'] = '/2';
                $timeArray['hours'] = '*';
                $timeArray['days'] = '*';
                $timeArray['dayWeek'] = '1-6';
                $timeArray['months'] = '*';
                $masterInterval = 1;
                $now = time();
                $lastExec = time()-60*$masterInterval;
                $res = $this->getNextExecutionTimeForScript(time()-60*$masterInterval, $timeArray);
                if($res >= $lastExec && $res < $now){
                    echo "RUN NOW " .  date($mess["date_format"], $res) . " last exec was ". date($mess["date_format"], $lastExec);
                }else{
                    echo "NOTHING TO DO";
                }

            break;

            case "ls":

                $captured = $postProcessData["ob_output"];
                $node = '<tree text="Scheduler" icon="susehelpcenter.png" filename="/admin/scheduler"/>';
                print str_replace("</tree>", "$node</tree>", $captured);

            break;

            default:
            break;
        }

    }

    function listTasks($action, $httpVars, $postProcessData){

        AJXP_XMLWriter::renderHeaderNode("tree", "Scheduler", false);
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="list"  template_name="action.scheduler_list">
     			<column messageId="Task Label" attributeName="ajxp_label" sortType="String"/>
     			<column messageId="Next Schedule" attributeName="task_schedule" sortType="String"/>
        </columns>');

        $timeArray = array();
        $timeArray['minutes'] = '/3';
        $timeArray['hours'] = '*';
        $timeArray['days'] = '*';
        $timeArray['dayWeek'] = '1-6';
        $timeArray['months'] = '*';
        $res = $this->getNextExecutionTimeForScript(time(), $timeArray);
        $mess =ConfService::getMessages();
        AJXP_XMLWriter::renderNode("/admin/scheduler/task1", "Task 1", true, array("task_schedule" => date($mess["date_format"], $res) ));
        AJXP_XMLWriter::close();

    }

    function getNextExecutionTimeForScript($referenceTime, $timeArray)
    {
        $a=null; $m=null; $j=null; $h=null; $min=null;

        $aNow = date("Y", $referenceTime);
        $mNow = date("m", $referenceTime);
        $jNow = date("d", $referenceTime);
        $hNow = date("H", $referenceTime);
        $minNow = date("i", $referenceTime)+1;

        $a = $aNow;
        $m = $mNow - 1;

        while($this->nextMonth($timeArray, $a, $m, $j, $h, $min) != -1)			/* on parcourt tous les mois de l'intervalle demandé */
        {							/* jusqu'à trouver une réponse convanable */
            if ($m != $mNow || $a != $aNow)			/*si ce n'est pas ce mois ci */
            {
                $j = 0;
                if ($this->nextDay($timeArray, $a, $m, $j, $h, $min) == -1)	/* le premier jour trouvé sera le bon. */
                {					/*  -1 si l'intersection entre jour de semaine */
                    /* et jour du mois est nulle */
                    continue;			/* ...auquel cas on passe au mois suivant */
                }else{					/* s'il y a un jour */
                    $h=-1;
                    $this->nextHour($timeArray, $a, $m, $j, $h, $min);	/* la première heure et la première minute conviendront*/
                    $min = -1;
                    $this->nextMinute($timeArray, $a, $m, $j, $h, $min);
                    return mktime($h, $min, 0, $m, $j, $a);
                }
            }else{						/* c'est ce mois ci */
                $j = $jNow-1;
                while($this->nextDay($timeArray, $a, $m, $j, $h, $min) != -1)	/* on cherche un jour à partir d'aujourd'hui compris */
                {
                    if ($j > $jNow)			/* si ce n'est pas aujourd'hui */
                    {				/* on prend les premiers résultats */
                        $h=-1;
                        $this->nextHour($timeArray, $a, $m, $j, $h, $min);
                        $min = -1;
                        $this->nextMinute($timeArray, $a, $m, $j, $h, $min);
                        return mktime($h, $min, 0, $m, $j, $a);
                    }
                    if ($j == $jNow)		/* même algo pour les heures et les minutes */
                    {
                        $h = $hNow - 1;
                        while($this->nextHour($timeArray, $a, $m, $j, $h, $min) != -1)
                        {
                            if ($h > $hNow)
                            {
                                $min = -1;
                                $this->nextMinute($timeArray, $a, $m, $j, $h, $min);
                                return mktime($h, $min, 0, $m, $j, $a);
                            }
                            if ($h == $hNow)
                            {
                                $min = $minNow - 1;
                                while($this->nextMinute($timeArray, $a, $m, $j, $h, $min) != -1)
                                {
                                    if ($min > $minNow) { return mktime($h, $min, 0, $m, $j, $a); }

                                    /* si c'est maintenant, on l'éxécute directement */
                                    if ($min == $minNow)
                                    {
                                        return $referenceTime;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    function parseFormat($min, $max, $intervalle)
    {
    	$retour = Array();

    	if ($intervalle == '*')
    	{
    		for($i=$min; $i<=$max; $i++) $retour[$i] = TRUE;
    		return $retour;
    	}else{
    		for($i=$min; $i<=$max; $i++) $retour[$i] = FALSE;
    	}
        if($intervalle[0] == "/"){
            // Transform Repeat pattern into range
            $repeat = intval(ltrim($intervalle, "/"));
            $values= array();
            for($i=$min;$i<=$max;$i++){
                if(($i % $repeat) == 0) $values[] = $i;
            }
            $intervalle = implode(",", $values);
        }

    	$intervalle = array_map("trim", explode(',', $intervalle));
    	foreach ($intervalle as $val)
    	{
    		$val = array_map("trim", explode('-', $val));
    		if (isset($val[0]) && isset($val[1]))
    		{
    			if ($val[0] <= $val[1])
    			{
    				for($i=$val[0]; $i<=$val[1]; $i++) $retour[$i] = TRUE;	/* ex : 9-12 = 9, 10, 11, 12 */
    			}else{
    				for($i=$val[0]; $i<=$max; $i++) $retour[$i] = TRUE;	/* ex : 10-4 = 10, 11, 12... */
    				for($i=$min; $i<=$val[1]; $i++) $retour[$i] = TRUE;	/* ...et 1, 2, 3, 4 */
    			}
    		}else{
    			$retour[$val[0]] = TRUE;
    		}
    	}
    	return $retour;
    }

    function nextMonth($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(1, 12, $timeArray['months']);
        do
        {
            $m++;
            if ($m == 13)
            {
                $m=1;
                $a++;		/*si on a fait le tour, on réessaye l'année suivante */
            }
        }while($valeurs[$m] != TRUE);
    }
    function nextDay($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(1, 31, $timeArray['days']);
        $valeurSemaine = $this->parseFormat(0, 6, $timeArray['dayWeek']);

        do
        {
            $j++;

            /* si $j est égal au nombre de jours du mois + 1 */
            if ($j == date('t', mktime(0, 0, 0, $m, 1, $a))+1) { return -1; }

            $js = date('w', mktime(0, 0, 0, $m, $j, $a));
        }while($valeurs[$j] != TRUE || $valeurSemaine[$js] != TRUE);
    }
    function nextHour($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(0, 23, $timeArray['hours']);

        do
        {
            $h++;
            if ($h == 24) { return -1; }
        }while($valeurs[$h] != TRUE);
    }

    function nextMinute($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(0, 59, $timeArray['minutes']);

        do
        {
            $min++;
            if ($min == 60) { return -1; }
        }while($valeurs[$min] != TRUE);
    }
}