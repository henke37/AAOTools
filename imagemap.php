<?php

require dirname(__FILE__).'/lib.php';

$parser=new AAParser();

if(isset($_GET['file'])) {
	$parser->deassembleFile($_GET['file']);
} else {
	$parser->deassembleFile('data.txt');
}

class ImageMap {

	private $document,$head,$body,$html;
	private $parser;

	function __construct($parser) {
		$this->document=new DOMDocument();
		$this->parser=$parser;
	}

	function createMaps() {

		$this->html=$this->document->createElement('html');
		$this->document->appendChild($this->html);
		$this->head=$this->document->createElement('head');
		$this->html->appendChild($this->head);
		$this->body=$this->document->createElement('body');
		$this->html->appendChild($this->body);

		foreach($this->parser->text as $frame) {
			if($frame['tableau_action']['nom_action']=='PointerImage') {
				$this->addMapForFrame($frame);
			}
		}
	}

	function buildHTML() {
		$this->document->formatOutput=true;
		return $this->document->saveHTML();
	}

	function addMapForFrame($frame) {

		$action=$frame['tableau_action'];
		$parms=$action['param_action'];

		assert($action['nom_action']=='PointerImage');

		$map=$this->document->createElement('map');
		$map->setAttribute('name','frame'.$frame['id']);

		foreach($parms[1] as $key=>$topleft) {
			$area=$this->document->createElement('area');
			$area->setAttribute('shape','rect');
			$area->setAttribute('href','#'.$parms[6][$key]);
			$coords=$topleft;
			$coords.=','.$parms[2][$key];
			$coords.=','.$parms[3][$key];
			$coords.=','.$parms[4][$key];
			$area->setAttribute('coords',$coords);
			$map->appendChild($area);
		}

		$default=$this->document->createElement('area');
		$default->setAttribute('shape','default');
		$default->setAttribute('href','#'.$parms[5]);
		$map->appendChild($default);

		$this->head->appendChild($map);

		$img=$this->document->createElement('img');
		$img->setAttribute('src',$parms[0]);
		$img->setAttribute('usemap','#frame'.$frame['id']);
		$img->setAttribute('border','0');

		$this->body->appendChild($img);

	}

	function importMaps($html) {
		$this->document->loadHTML($html);
	}
}

$map=new ImageMap($parser);
$map->createMaps();

echo $map->buildHTML();
