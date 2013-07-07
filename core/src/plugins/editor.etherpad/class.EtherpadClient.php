<?php

class EtherpadClient extends AJXP_Plugin{

    var $baseURL = "http://10.211.55.3:9001";

    public function switchAction($actionName, $httpVars, $fileVars){

        if(isSet($httpVars["file"])){

            $repository = ConfService::getRepository();
            if(!$repository->detectStreamWrapper(false)){
                return false;
            }
            $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
            $streamData = $plugin->detectStreamWrapper(true);
            $destStreamURL = $streamData["protocol"]."://".$repository->getId()."/";
            $filename = $destStreamURL. AJXP_Utils::securePath($httpVars["file"]);

            if(!is_file($filename)){
                throw new Exception("Cannot find file!");
            }
        }

        require_once("etherpad-client/etherpad-lite-client.php");
        $client = new EtherpadLiteClient("nl8VJIWXZMHNj7aWj6rWy4CLct1mu97v",$this->baseURL."/api");

        if($actionName == "etherpad_create"){

            if(isSet($httpVars["pad_name"])){

                $padID = $httpVars["pad_name"];
                $startContent = "";

            }else if(isSet($httpVars["file"])){

                $startContent = file_get_contents($filename);
                $padID = AJXP_Utils::slugify($httpVars["file"]);

            }

            $userName = AuthService::getLoggedUser()->getId();

            $res = $client->createAuthorIfNotExistsFor($userName, $userName);
            $authorID = $res->authorID;

            $res2 = $client->createGroupIfNotExistsFor("ajaxplorer");
            $groupID = $res2->groupID;

            $resP = $client->listPads($res2->groupID);

            $pads = $resP->padIDs;
            if(!in_array($groupID.'$'.$padID, $pads)){
                $res3 = $client->createGroupPad($groupID, $padID, $startContent);
            }

            $res4 = $client->createSession($groupID, $authorID, time()+14400);
            $sessionID = $res4->sessionID;

            setcookie('sessionID', $sessionID, null, "/");

            $padID = $groupID.'$'.$padID;

            $data = array(
                "url" => $this->baseURL."/p/".$padID,
                "padID" => $padID,
                "sessionID" => $sessionID,
            );

            HTMLWriter::charsetHeader('application/json');
            echo(json_encode($data));

        }else if ($actionName == "etherpad_save"){

            $padID = $httpVars["pad_id"];
            $res = $client->getText($padID);
            file_put_contents($filename, $res->text);

        }else if ($actionName == "etherpad_close"){

            // WE SHOULD DETECT IF THERE IS NOBODY CONNECTED ANYMORE, AND DELETE THE PAD.
            $sessionID = $httpVars["session_id"];
            $i = $client->getSessionInfo($sessionID);

        }

    }


}