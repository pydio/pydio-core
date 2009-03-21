<?php
// (C) Cyril Russo (AJXP)
// At first, list folder in the tests subfolder
chdir('./server/tests');
$files = glob('*.php'); 

$outputArray = array();
foreach($files as $file)
{
    require_once($file);
    // Then create the test class
    $testName = str_replace(".php", "", substr($file, 5));
    $class = new $testName();
    
    $outputArray[] = array("name"=>$class->name, "result"=>$class->doTest(), "level"=>$class->failedLevel, "info"=>$class->failedInfo); 
}

// Done, let's output the test result on a "nice" html 3.0 table
echo "<html><body><table border=1><tr><td>Name</td><td>Result</td><td>Level</td><td>Info</td></tr>"; 
foreach($outputArray as $item)
{
    // A test is output only if it hasn't succeeded (doText returned FALSE)
    $result = $item["result"] ? "passed" : ($item["level"] == "info" ? "dump" : "failed");
    $success = $result == "passed";
    echo "<tr><td>".$item["name"]."</td><td>".$result."</td><td>".(!$success ? $item["level"] : "")."</td><td>".(!$success ? $item["info"] : "")."</td></tr>";
}
echo "</table></body></html>";

?>
