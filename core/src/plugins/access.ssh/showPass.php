#!/usr/bin/php
<?php 
$pass = $_ENV["SSH_PASSWORD"];
// Don't let the environment leak
putenv("SSH_PASSWORD");
echo $pass; ?>
