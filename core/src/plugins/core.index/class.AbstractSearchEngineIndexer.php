<?php
/**
 * Created by PhpStorm.
 * User: charles
 * Date: 15/04/2015
 * Time: 14:52
 */

abstract class AbstractSearchEngineIndexer extends AJXP_AbstractMetaSource {

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

} 