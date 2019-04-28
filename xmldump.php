<?php

require dirname(__FILE__).'/lib.php';

$parser=new AAParser();
$parser->deassembleFile('data.txt');

function dumpXML($arr,$parent='',$level=0) {

	foreach($arr as $key=>$val) {
		$padding=str_repeat("\t",$level);

		if(is_int($key)) {
			$key=$parent;
		} elseif(is_array($val) && count($val)>0) {
			$parent=$key;
			$key=$key.'_list';
		}

		assert(!empty($key));
		assert(!empty($parent));


		printf('%s<%s>',$padding,htmlentities($key));
		if(is_array($val)) {
			echo "\r\n";
			dumpXML($val, $parent,$level+1);
			echo $padding;
		} else {
			echo htmlentities($val);
		}
		printf("</%s>\r\n",htmlentities($key));
	}
}

header('Content-type: text/xml');
echo '<'.'?xml version="1.0"?'.'>';
dumpXML(
	array(
		'trial'=>array(
			'profile'=>$parser->profiles,
			'evidence'=>$parser->evidence,
			'text'=>$parser->text
		)
	)
);