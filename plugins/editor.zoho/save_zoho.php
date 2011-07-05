<?php

$filezoho = $_FILES['content']["tmp_name"];
$target = $_POST['id'];

if (!move_uploaded_file($filezoho, $target)) {
    echo "File not saved!";
}


?>
