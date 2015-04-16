<?php
/**
 * Created by PhpStorm.
 * User: charles
 * Date: 15/04/2015
 * Time: 14:52
 */

abstract class AbstractSearchEngineIndexer extends AJXP_AbstractMetaSource {

    /**
     * @param DOMNode $contribNode
     */
    public function parseSpecificContributions(&$contribNode){
        parent::parseSpecificContributions($contribNode);
        if($this->getFilteredOption("HIDE_MYSHARES_SECTION") !== true) return;
        if($contribNode->nodeName != "client_configs") return ;
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $nodeList = $actionXpath->query('component_config[@className="AjxpPane::navigation_scroller"]', $contribNode);
        if(!$nodeList->length) return ;
        $contribNode->removeChild($nodeList->item(0));
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return null|string
     */
    protected function extractIndexableContent($ajxpNode){

        $ext = strtolower(pathinfo($ajxpNode->getLabel(), PATHINFO_EXTENSION));
        if (in_array($ext, explode(",",$this->getFilteredOption("PARSE_CONTENT_TXT")))) {
            return file_get_contents($ajxpNode->getUrl());
        }
        $unoconv = $this->getFilteredOption("UNOCONV");
        $pipe = false;
        if (!empty($unoconv) && in_array($ext, array("doc", "odt", "xls", "ods"))) {
            $targetExt = "txt";
            if (in_array($ext, array("xls", "ods"))) {
                $targetExt = "csv";
            } else if (in_array($ext, array("odp", "ppt"))) {
                $targetExt = "pdf";
                $pipe = true;
            }
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            $unoconv = "HOME=".AJXP_Utils::getAjxpTmpDir()." ".$unoconv." --stdout -f $targetExt ".escapeshellarg($realFile);
            if ($pipe) {
                $newTarget = str_replace(".$ext", ".pdf", $realFile);
                $unoconv.= " > $newTarget";
                register_shutdown_function("unlink", $newTarget);
            }
            $output = array();
            exec($unoconv, $output, $return);
            if (!$pipe) {
                $out = implode("\n", $output);
                $enc = 'ISO-8859-1';
                $asciiString = iconv($enc, 'ASCII//TRANSLIT//IGNORE', $out);
                return $asciiString;
            } else {
                $ext = "pdf";
            }
        }
        $pdftotext = $this->getFilteredOption("PDFTOTEXT");
        if (!empty($pdftotext) && in_array($ext, array("pdf"))) {
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            if ($pipe && isset($newTarget) && is_file($newTarget)) {
                $realFile = $newTarget;
            }
            $cmd = $pdftotext." ".escapeshellarg($realFile)." -";
            $output = array();
            exec($cmd, $output, $return);
            $out = implode("\n", $output);
            $enc = 'UTF8';
            $asciiString = iconv($enc, 'ASCII//TRANSLIT//IGNORE', $out);
            return $asciiString;
        }
        return null;
    }

    /**
     * @param String $query
     * @return String mixed
     */
    protected function filterSearchRangesKeywords($query)
    {
        if (strpos($query, "AJXP_SEARCH_RANGE_TODAY") !== false) {
            $t1 = date("Ymd");
            $t2 = date("Ymd");
            $query = str_replace("AJXP_SEARCH_RANGE_TODAY", "[$t1 TO  $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_YESTERDAY") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m'), date('d')-1, date('Y')));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d')-1, date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_YESTERDAY", "[$t1 TO $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_LAST_WEEK") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m'), date('d')-7, date('Y')));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_LAST_WEEK", "[$t1 TO $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_LAST_MONTH") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m')-1, date('d'), date('Y')));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_LAST_MONTH", "[$t1 TO $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_LAST_YEAR") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')-1));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_LAST_YEAR", "[$t1 TO $t2]", $query);
        }

        $split = array_map("trim", explode("AND", $query));
        foreach($split as $s){
            list($k, $v) = explode(":", $s, 2);
            if($k == "ajxp_bytesize"){
                //list($from, $to) = sscanf($v, "[%s TO %s]");
                preg_match('/\[(.*) TO (.*)\]/', $v, $matches);
                $oldSize = $s;
                $newSize = "ajxp_bytesize:[".intval(AJXP_Utils::convertBytes($matches[1]))." TO ".intval(AJXP_Utils::convertBytes($matches[2]))."]";
            }
        }
        if(isSet($newSize) && isSet($oldSize)){
            $query = str_replace($oldSize, $newSize, $query);
        }

        return $query;
    }

    /**
     * @param String $repositoryId
     * @param String $userId
     * @return string
     */
    protected function buildSpecificId($repositoryId, $userId = null){
        $specificId = "";
        $specKey = $this->getFilteredOption("repository_specific_keywords");
        if (!empty($specKey)) {
            $specificId = "-".str_replace(array(",", "/"), array("-", "__"), AJXP_VarsFilter::filter($specKey, $userId));
        }
        return $repositoryId.$specificId;
    }

} 