<?php

require dirname(__FILE__).'/lib.php';

$parser=new AAParser();
$parser->deassembleFile('data.txt');
//reminder, include the hidden terminating line
$contents=$parser->assembleString();
file_put_contents('assembled.txt',$contents);

?><tt><pre><?php echo htmlentities($contents,ENT_NOQUOTES); ?></pre></tt><?php

if(file_get_contents('data.txt')!=$contents) {
	echo 'Fail';
} else {
	echo 'Pass';
}