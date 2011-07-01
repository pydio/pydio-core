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
$doc->addField(Zend_Search_Lucene_Field::text("filename", "mon document3.png"));
$testIndex->addDocument($doc);
$testIndex->commit();
*/
$testIndex->setDefaultSearchField("filename");

print($testIndex->numDocs());
$testIndex->optimize();
$hits = $testIndex->find("document3.png");
foreach($hits as $hit){
    print($hit->filename."<br>");
}

print("Everything seems to be ok!");

