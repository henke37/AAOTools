<?php

class Combination {
	var $pose;
	var $char;
	var $bg;
	var $id;
	var $path;

	var $reason;

	function __construct($id) {
		$this->id=$id;
		$this->path=array(0=>$id);
	}

	function toString() {
		return sprintf('Frame %u, bg %s, char %s, pose %s',$this->id,$this->bg,$this->char,$this->pose);
	}


	function isComplete() {
		if($this->char==-3) {
			return false;
		}
		if($this->char==-2) {
			return (bool)$this->bg;
		}
		if($this->char===0) {
			return $this->bg && $this->pose;
		}
		return $this->char && $this->bg && $this->pose;
	}

	static function combinationsEqual($c1,$c2) {

		//order does matter for all checks
		if($c1->bg!=$c2->bg) {
			return false;
		}

		if($c1->char!=$c2->char) {
			return false;
		}

		if($c1->char==-2) {
			return true;
		} else {
			return $c1->pose==$c2->pose;
		}
	}

	public function combinationHasChar() {
		if(is_null($this->pose) || $this->pose==0) {
			return false;
		}
		if($this->char===null) {
			return false;
		}
		if($this->char<0) {
			return false;
		}

		return true;
	}

	public function combinationHasBg() {
		if(!$this->bg) {
			return false;
		}
		if($this->bg=='no') {
			return false;
		}
		return true;
	}

	public function mergeNeeded($c2) {

		$cOut=clone $this;

		$setChar=false;

		if($this->char!==0 && !$this->char) {
			$setChar=true;
		}

		if(! $this->pose) {
			$setChar=true;
		}

		if($setChar) {
			$cOut->pose=$c2->pose;
			$cOut->char=$c2->char;
		}

		if(!$this->bg) {
			$cOut->bg=$c2->bg;
		}

		$cOut->path[]=$c2->id;

		return $cOut;
	}
}

class posechecker {

	var $breaker;
	var $parser;

	var $bgs,$chars;

	var $baseCombinations;

	function __construct($breaker,$chars,$bgs) {
		$this->resolvedFramePaths=array();
		$this->breaker=$breaker;
		$this->parser=$breaker->parser;
		$this->chars=$chars;
		$this->bgs=$bgs;

		$this->baseCombinations=array();
	}

	function getBadCombinations() {
		$badCombinations=array();
		foreach($this->parser->text as $frame) {

			$combinations=$this->getCombinationsForFrame($frame['id']);

			//echo 'path for frame'. $frame['id'];
			//var_dump($path);

			//echo 'combinations for frame '. $frame['id'];
			//var_dump($combinations);

			foreach($combinations as $combination) {

				$reason=$this->validate($combination);
				if($reason!='') {
					$combination->reason=$reason;
					$badCombinations[]=$combination;
				}
			}

		}
		return $badCombinations;
	}

	function validate($combination) {

		if(!$combination->combinationHasChar()) {
			return '';
		}

		if($combination->char===0) {
			$charType='judge';
		} else {
			$charID=$combination->char;
			$profile=$this->parser->profiles[$charID];
			$chardata=$this->chars[$profile['base']];
			if(isset($chardata[$combination->pose])) {
				$charType=$chardata[$combination->pose];
			} else {
				$charType=$chardata['all'];
			}

		}

		if($combination->pose=='external') {
			return '';
		}

		if(!$combination->combinationHasBg()) {
			$ok=$charType=='longfront';
			if(!$ok) {
				return 'Only longfront poses may be placed on nothing.';
			} else {
				return '';
			}
		}

		if(stripos($combination->bg,'//')!==false) {
			return '';
		}

		if($combination->bg==$charType) {
			return '';
		}

		$bgType=$this->bgs[$combination->bg];

		if($bgType=='effect') {
			return 'No characters are allowed on effect backgrounds.';
		}

		if($bgType==$charType) {
			return '';
		}

		if($bgType=='shortfront' && $charType=='longfront') {
			return '';
		}

		return 'Pose and bg are not compatible.';
	}


	function getBaseCombinationForFrame($id) {
		$id=(int)$id;
		assert($id!=0);

		if(isset($this->baseCombinations[$id])) {
			return $this->baseCombinations[$id];
		}

		$combination=new Combination($id);


		$frame=$this->parser->text[$id];

		assert(is_array($frame));

		$bg=$frame['fond']['chemin_fond'];
		if($bg) {
			$combination->bg=$bg;
		}

		$pose=$frame['perso']['chemin_perso'];
		if($frame['perso']['perso_externe']) {
			$combination->pose='external';
		}	elseif($pose) {
			$combination->pose=$pose;
		}

		$char=$frame['infos_auteur']['id_auteur'];
		if(($char && $char!=-3) || $char===0) {
			$combination->char=$char;
		}

		$this->baseCombinations[$id]=$combination;

		return $combination;
	}

	function getCombinationsForFrame($id) {

		$id=(int)$id;
		assert($id!=0);

		$baseCombination=$this->getBaseCombinationForFrame($id);

		if($baseCombination->isComplete()) {
			return array(0=>$baseCombination);
		}

		$finishedCombinations=array();

		$unFinishedCombinations=array(0=>$baseCombination);

		do {
			$halfFinishedCombination=array_pop($unFinishedCombinations);

			$paths=$this->getPathsForFrame($halfFinishedCombination->id);

			if(count($paths)==0) {
				$finishedCombinations[]=$halfFinishedCombination;
				continue;
			}

			foreach($paths as $path) {

				if(in_array($path,$halfFinishedCombination->path)) {
					continue;//going around in circles is bad for you
				}

				$baseCombination=$this->getBaseCombinationForFrame($path);

				$c2=$halfFinishedCombination->mergeNeeded($baseCombination);

				if($c2->isComplete()) {
					$finishedCombinations[]=$c2;
				} else {
					$unFinishedCombinations[]=$c2;
				}
			}

		} while(count($unFinishedCombinations)>0);

		return $finishedCombinations;
	}

	function getPathsForFrame($id) {
		$id=(int)$id;
		assert($id!=0);

		$paths=array();

		$block=$this->breaker->getBlock($id);

		if(!$block) {
			return array();
		}

		if($id>$block->begin) {//we can just seek backwards
			if($id>0) {
				$paths[]=$id-1;
			}
		} else {
			foreach($block->from as $jump) {
				switch($jump['type']) {
					case 'hide':
					break;

					default:
						$paths[]=$jump['from'];
						//var_dump( $from );
					break;
				}#end of switch type
			}#end of foreach from
		}#end of if first in block

		return $paths;
	}


}

require dirname(__FILE__).'/lib.php';
require dirname(__FILE__).'/blockbreaker.php';
require dirname(__FILE__).'/posedb.php';

$parser=new AAParser();

if(isset($_GET['file'])) {
	$parser->deassembleFile($_GET['file']);
} else {
	$parser->deassembleFile('data.txt');
}

$breaker=new blockBreaker($parser);

$checker=new poseChecker($breaker,$chars,$bgs);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Pose validator</title>

<style>

body {
   margin:0 10px 10px 10px;
   background:#F0F0F0;
   font:medium sans-serif;
}

h1 {
   margin:0 0 5px 0;
   padding:5px;
   background:white;
   border-right:thin solid lightgrey;
   border-bottom:thin solid lightgrey;
   border-left:thin solid lightgrey;
   border-radius:0 0 4px 4px;
   box-shadow:inset 0 0 5px lightgrey;
   font:xx-large Georgia, serif;
   text-align:center;
   color:#782201;

   -moz-border-radius:0 0 4px 4px;
   -moz-box-shadow:inset 0 0 5px lightgrey; -webkit-box-shadow:inset 0 0 5px lightgrey;
}

table {
   width:100%;
   border:thin solid lightgrey;
   border-spacing:0;
   border-radius:4px;

   -moz-border-radius:4px;
}

th, td { padding:5px; }

thead th {
   background:#F9F9F9;
   font:bold large Georgia, serif;
   color:#782201;
   text-align:start;

   background:-moz-linear-gradient(top, rgb(255,255,255), rgb(240,240,240));
}

tbody th {
   background:#F9F9F9;
   font:medium Georgia, serif;
   color:#782201;

   background:-moz-linear-gradient(top, rgb(255,255,255), rgb(240,240,240));
}

tbody td { background:white; }
tbody tr:nth-of-type(odd) td { background:#F4ECE3; }

ul { display:inline; padding:0; }
li { display:inline; }
li + li::before { content:" ? "; }

</style>
</head>

<body>
<h1>Pose validator</h1>

<table>
   <col style="width:8em;" />
   <col style="width:266px;" />
   <col style="width:80px;" />
   <col style="width:266px;" />
   <col />
   <col />
   <thead>
     <tr>
       <th>Background sprite</th>
       <th>Character</th>
       <th>Character sprite</th>
       <th>Problem description</th>
       <th>Path to problem</th>
     </tr>
   </thead>
	 <tbody><?php

function printPath($paths) {
	echo '<ul>';
	foreach($paths as $path) {
		echo '<li>';
		//print_r($path);

		printf ('<div>%s</div>',$path);
		//printPaths($path->paths);

		echo '</li>';
	}
	echo '</ul>';
}

foreach($checker->getBadCombinations() as $comb) {
	printf(
		'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>',
		htmlentities($comb->bg),
		htmlentities($comb->char),
		htmlentities($comb->pose),
		htmlentities($comb->reason)
	);

	printPath($comb->path);

	echo "</td></tr>\n";
}

/*
foreach($badCombinations as $comb) {
	var_dump($comb);
	echo sprintf('%s,%s,%s,%s',$comb['bg'],$comb['char'],$comb['pose'],$comb['reason']);
	assert($comb['reason']!='');
	assert(is_array($comb['paths']));
	foreach($comb['paths'] as $path) {
		echo ','.$path->id;
	}
	echo '<br/>';
}*/
?>
   </tbody>
</table>
</body>
</html>