<?php

require dirname(__FILE__).'/lib.php';
require dirname(__FILE__).'/blockbreaker.php';



function orderBlock($x,$y) {
	return $x->begin-$y->begin;
}

function orderEdge($x,$y) {
	if($x['to']!=$y['to']) {
		return $x['to']-$y['to'];
	} else {
		return $x['from']-$y['from'];
	}
}


function atts($atts) {
	if(count($atts)==0) {
		return '';
	}

	$o=' [';

	$hadFirst=false;
	foreach($atts as $key=>$val) {
		if($hadFirst) {
			$o.=',';
		} else {
			$hadFirst=true;
		}
		$o.=sprintf('%s="%s"',$key,str_replace('"','\\"',$val));
	}
	$o.=']';
	return $o;
}

function buildTree($parser,$output) {

	ob_start();

	$breaker=new blockBreaker($parser);

	//sort so that clustering will work
	//oh, and it's nice for the dump too
	uasort($breaker->blocks,'orderBlock');

	foreach($breaker->blocks as &$block) {
		uasort($block->from,'orderEdge');
	}
	unset($block);

	echo '<tt><pre>';
	print_r($breaker->blocks);
	echo '</pre></tt>';

	$fp=tmpfile();
	flock($fp,LOCK_EX);

	fwrite($fp,"digraph Trial {\n");
	//fwrite($fp,"concentrate=true\n");
	//fwrite($fp,"node [style=filled];\n");


	$clusterEnd=0;

	foreach($breaker->blocks as $block) {

		$firstText=$parser->text[$block->begin];

		$line='';

		$line.=$block->begin;

		$atts=array();

		if($block->begin!=$block->end) {
			$atts['label']=$block->begin.'-'.$block->end;
		}



		if($firstText['cache']) {
			//$atts['fillcolor']='#DDDDDD';
			$atts['style']='dashed';
			$atts['color']='blue';
		}

		switch($firstText['tableau_action']['nom_action']) {
			case 'CreerLieu':
				$atts['color']='green';
				//$atts['label'].='\\n'.$firstText['tableau_action']['param_action'][1];
			break;

			case 'LancerVerrous':
				$atts['color']='purple';
			break;

			case 'LancerCI':
				$atts['color']='#FF8000';
			break;

			case 'DiscussionEnqueteV2':
				$atts['shape']='Mrecord';
				$label='{'.$block->begin.'|{';

				$first=true;
				foreach($firstText['tableau_action']['param_action'][0] as $key=>$jump) {

					if($first) {
						$first=false;
					} else {
						$label.='|';
					}
					$label.='<'.$key.'>'.$key;

					$jumpline=$block->begin.':'.$key.' -> '.$jump;
					$jumpatts=array();
					$jumpatts['color']='#FF8000';
					$jumpatts['tailport']='s';
					$jumpline.=atts($jumpatts);
					$jumpline.=";\n";
					fwrite($fp,$jumpline);

				}
				if($firstText['tableau_action']['param_action'][3]) {
					if($first) {
						$first=false;
					} else {
						$label.='|';
					}
					$label.='<PL>PL';

					$jumpline=$block->begin.':PL -> '.$firstText['tableau_action']['param_action'][3];
					$jumpatts=array();
					$jumpatts['color']='purple';
					$jumpatts['tailport']='s';
					$jumpline.=atts($jumpatts);
					$jumpline.=";\n";
					fwrite($fp,$jumpline);
				}

				$label.='}}';
				//echo $label;
				$atts['label']=$label;

			break;

		}

		if(!isset($parser->text[$block->end+1]) || $parser->text[$block->end]['tableau_action']['nom_action']=='FinDuJeu') {
			$atts['color']='red';
		}

		$line.=atts($atts);
		$line.=";\n";
		fwrite($fp,$line);





		foreach($block->from as $jump) {

			//find the block that contains the jump origin
			$fromBlock=$breaker->getBlock($jump['from']);
			//$fromBlock=$breaker->blocks[$jump['from']];
			$jumpline=$fromBlock->begin.' -> '.$jump['to'];
			$jumpatts=array();
			$skip=false;
			switch($jump['type']) {
				case 'return':
					$jumpatts['style']='dotted';
				break;

				case 'exit':
					$jumpatts['color']='#666666';
					$jumpatts['style']='dotted';
				break;

				case 'press':
					$jumpatts['color']='#666666';
				break;

				case 'wrong':
				case 'condFail':
					$jumpatts['color']='red';
				break;

				case 'condOk':
					$jumpatts['color']='green';
				break;

				case 'contradiction':
					$jumpatts['style']='bold';
				break;

				case 'move':
					$jumpatts['color']='#9C5A3C';
					//$jumpatts['constraint']='false';//wide with very long lines vs webby with long lines,
				break;

				case 'lock':
					$jumpatts['color']='purple';
					$skip=true;
				break;

				case 'hidden':
					$jumpatts['color']='blue';
					$jumpatts['style']='dashed';
				break;

				case 'hide':
					$jumpatts['color']='yellow';
					$jumpatts['constraint']='false';
				break;

				case 'talk':
					$jumpatts['color']='#FF8000';
					$skip=true;
				break;

				case 'gameOver':
					$jumpatts['color']='red';
					$jumpatts['style']='dashed';
				break;

				case 'jump':
					$jumpatts['arrowtail']='oinv';
				break;
			}

			$jumpline.=atts($jumpatts);

			$jumpline.=";\n";
			if(!$skip) {
				fwrite($fp,$jumpline);
			}
		}#end of foreach links


	}#end of foreach blocks

	fwrite($fp,"}\n");

	rewind($fp);

	if($output=='debug') {
		ob_end_flush();
	} else {
		ob_end_clean();
	}

	if($output=='dot') {
		header('Content-type: text/plain');
		header('Content-Disposition: attachment; filename="trial.dotty"');
		fpassthru($fp);
		fclose($fp);
	} elseif($output=='png') {
		$cmd='c:\\"program files"\\Graphviz2.24\\bin\\dot.exe -Tpng';
		//echo $cmd;
		$proc=proc_open($cmd,array(0=>$fp,1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,null,array('binary_pipes'));

		fclose($fp);

		//echo $res;
		header('Content-type: image/png');
		//header('Content-Disposition: attachment; filename="trial.png"');
		fpassthru($pipes[1]);
		fclose($pipes[1]);

		fpassthru($pipes[2]);
		fclose($pipes[2]);

		$res=proc_close($proc);
		//printf('%u',$res);

	} else {
		fclose($fp);
	}


}

$parser=new AAParser();
$parser->deassembleFile('data.txt');

if(isset($_GET['output'])) {
	buildTree($parser,$_GET['output']);
} else {
	buildTree($parser,'png');
}