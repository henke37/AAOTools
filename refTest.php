<?php

require dirname(__FILE__).'/lib.php';

$parser=new AAParser();
$parser->deassembleFile('data.txt');

echo '<tt><pre>';

$parser->deassembleReferences();



var_dump($parser);

echo '</pre></tt>';