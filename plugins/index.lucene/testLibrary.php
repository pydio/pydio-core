<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Charles
 * Date: 01/07/11
 * Time: 15:30
 * To change this template use File | Settings | File Templates.
 */
 
set_include_path(get_include_path().";".dirname(__FILE__));

require_once("Zend/Search/Lucene.php");
if(is_dir('./an-index')){
    $testIndex = Zend_Search_Lucene::open("./an-index");
}else{
    $testIndex = Zend_Search_Lucene::create("./an-index");
}
/*
$doc = new Zend_Search_Lucene_Document();
$doc->addField(Zend_Search_Lucene_Field::Keyword("node_path", "/path/to/zobi.xls"));
$doc->addField(Zend_Search_Lucene_Field::Keyword("basename", "zobi.xls"));
$testIndex->addDocument($doc);
$testIndex->commit();
*/
$testIndex->setDefaultSearchField("basename");
print($testIndex->numDocs());
//$testIndex->optimize();
if(isSet($_GET["q"])){
	$hits = $testIndex->find($_GET["q"]);
	print(count($hits)." results for ".$_GET["q"]);
	print("<ul>");
	foreach($hits as $hit){
		print("<li>");
	    print($hit->basename);
	    print("<br>".$hit->node_path);
	    //print("<br>".$hit->id."<br>");
	    echo "\tScore: ".sprintf('%.2f', $hit->score);
	    print("</li>");
	}
}