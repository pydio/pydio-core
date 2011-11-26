<?php
  
  $vars = array_merge($_GET, $_POST);
  
  if(!isSet($vars["ajxp_action"]) && isset($vars["id"]) && isset($vars["format"])){
    $filezoho = $_FILES['content']["tmp_name"];
    $cleanId = str_replace(array("..", "/"), "", $vars["id"]);
    move_uploaded_file($filezoho, "files/".$cleanId.".".$vars["format"]);
  }else if($vars["ajxp_action"] == "get_file" && isSet($vars["name"])){
    if(file_exists("files/".$vars["name"])){
      readfile("files/".$vars["name"]);
      unlink("files/".$vars["name"]);
    }
  }
  
  
?>