<?php

class AAParser {

	var $text;
	var $profiles;
	var $evidence;
	var $locations;
	var $definition;

	function cleanLineEnds($x) {
		return trim($x);
	}

	function deassembleFile($file) {
		$this->deassembleArray(file($file, FILE_IGNORE_NEW_LINES));
	}

	function splitLegacy(&$argument) {
		//if(strpos($argument,'_')!==false && strpos($argument,'http')===false) {
		//echo $argument.' ';
		$argument=explode('_',$argument);
		//var_dump($argument);
		//echo '<br>';
		//die();
	}

	function joinLegacy(&$argument) {
		$argument=join('_',$argument);
	}

	function deassembleString($lines) {
		$lines=explode("\n",$lines);

		$lines=array_map(array($this,'cleanLineEnds'),$lines);
		$this->deassembleArray($lines);
	}

	function deassembleArray($lines) {
		$this->definition = substr($lines[0], 14);

		if(!in_array($this->definition, array('Def4', 'Def5'))) {
		//	echo $lines[0];
			throw new Exception("Not a legal trial");
		}

		$delimiters=array_keys($lines,'!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!');

		$profilelines=array_slice($lines,1,$delimiters[0]-1);
		$this->profiles=array();
		foreach($profilelines as $line) {
			$line=unserialize($line);
			$this->profiles[$line['id']]=$line;
		}
		//print_r($profilelines);


		$evidencelines=array_slice($lines,$delimiters[0]+1,$delimiters[1]-$delimiters[0]-1);
		$this->evidence=array();
		foreach($evidencelines as $line) {
			$line=unserialize($line);
			$this->evidence[$line['id']]=$line;
		}

		//print_r($evidencelines);

		$oldtextlines=array_slice($lines,$delimiters[1]+1);
		$this->text=array();
		$this->locations=array();

		foreach($oldtextlines as $n=>$l) {
			$l=trim($l);
			if($l=='') continue;
			$line=unserialize($l);
			assert($line);
			$line['id']=$n+1;//set the right id


			//fix up some legacy action arguments
			//and other per action tweaks
			//REMEMBER TO UPDATE THE UNTWEAKING LATER IN THE FILE <-------------------------------!
			switch($line['tableau_action']['nom_action']) {

				case 'CreerLieu':
					$this->locations[$line['tableau_action']['param_action'][0]]=$line['id'];
				break;

				case 'LancerCI':
					$this->splitLegacy($line['tableau_action']['param_action'][0]);
					$this->splitLegacy($line['tableau_action']['param_action'][1]);
					$this->splitLegacy($line['tableau_action']['param_action'][2]);
					$this->splitLegacy($line['tableau_action']['param_action'][3]);
				break;

				case 'DiscussionEnquete':
					foreach($line['tableau_action']['param_action'] as &$parm) {
						$this->splitLegacy($parm);
					}
				break;

				case 'SeDeplacer':
					foreach($line['tableau_action']['param_action'] as &$parm) {
						$this->splitLegacy($parm);
					}
				break;

				case 'DevoilerVerrousLieu':
				case 'MasquerVerrousLieu':
					$this->splitLegacy($line['tableau_action']['param_action'][0]);
				break;
			}
			//print_r($line['tableau_action']);echo '<br>';

			if(isset($line['id_auteur'])) {
				$line['infos_auteur']['id_auteur']=$line['id_auteur'];
				unset($line['id_auteur']);
			}

			$this->text[$line['id']]=$line;
		}
	}

	function assembleString() {
		$lines=array('//Definition//'.$this->definition);
		foreach($this->profiles as $profile) {
			$lines[]=serialize($profile);
		}
		$lines[]='!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!';
		foreach($this->evidence as $evidence) {
			$lines[]=serialize($evidence);
		}
		$lines[]='!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!-!';
		foreach($this->text as $line) {

			//fix up some legacy action arguments
			//var_dump($line['tableau_action']);echo '<br>';
			switch($line['tableau_action']['nom_action']) {
				case 'LancerCI':
					$this->joinLegacy($line['tableau_action']['param_action'][0]);
					$this->joinLegacy($line['tableau_action']['param_action'][1]);
					$this->joinLegacy($line['tableau_action']['param_action'][2]);
					$this->joinLegacy($line['tableau_action']['param_action'][3]);
				break;

				case 'DiscussionEnquete':
					foreach($line['tableau_action']['param_action'] as &$parm) {
						$this->joinLegacy($parm);
					}
				break;

				case 'SeDeplacer':
					foreach($line['tableau_action']['param_action'] as &$parm) {
						$this->joinLegacy($parm);
					}
				break;

				case 'DevoilerVerrousLieu':
				case 'MasquerVerrousLieu':
					$this->joinLegacy($line['tableau_action']['param_action'][0]);
				break;
			}

			unset($line['id']);

			$lines[]=serialize($line);
		}

		//$lines[]='!-!-!-!-!-!-!-!-INTEGRITE-CONFIRMEE-!-!-!-!-!-!-!-!-!';

		return join("\n",$lines);
	}

	function id2text(&$param) {
		//var_dump($param);
		$param=$this->text[$param];
	}

	function id2evidence(&$param,$type) {
		if($type=='preuve') {
			$param=$this->evidence[$param];
		} else {
			$param=$this->profile[$param];
		}
	}

	function deassembleReferences() {

		foreach($this->text as &$line) {
			//echo $line['id'].' '.$line['tableau_action']['nom_action'];
			switch($line['tableau_action']['nom_action']) {

				case 'AfficherElement':
					$this->id2evidence($line['tableau_action']['param_action'][1],$line['tableau_action']['param_action'][0]);
				break;

				case 'AllerCI':
				case 'AllerMessage':
				case 'AjouterCI':
					$this->id2text($line['tableau_action']['param_action'][0]);
				break;

				case 'CreerLieu':
					$this->id2text($line['tableau_action']['param_action'][3]);
				break;

				case 'ChoixEntre4':
					for($i=4;$i<8;$i++) {
						//echo $i;
						$this->id2text($line['tableau_action']['param_action'][$i]);
					}
				break;

				case 'ChoixEntre2':
					for($i=2;$i<4;$i++) {

						$this->id2text($line['tableau_action']['param_action'][$i]);
					}
				break;

				case 'DemanderPreuve':
					$this->id2text($line['tableau_action']['param_action'][2]);
					foreach($line['tableau_action']['param_action'][0] as $key=>$type) {
						$this->id2evidence($line['tableau_action']['param_action'][1][$key],$type);
						$this->id2text($line['tableau_action']['param_action'][3][$key]);
					}
				break;

			}
		}
		unset($line);


	}

}