<?php

define('ROOTRELPATH','_rels/.rels');
define('PACKAGENS','http://schemas.openxmlformats.org/package/2006/');
define('ODNS','http://schemas.openxmlformats.org/officeDocument/2006/');

require dirname(__FILE__).'/lib.php';

class Wordfile {

	private $parser;
	private $zip;
	private $documentFileName;
	private $footFileName;
	private $lang;

	private $stockColors;

	public $foot;

	protected static function getNodeByName($parent,$names) {
		if(is_array($names)) {
			$localname=array_shift($names);
		} else {
			$localname=$names;
		}

		$found=false;

		foreach($parent->childNodes as $node) {
			if($node->localName==$localname) {
				$found=true;
				break;
			}
			//echo $docNode->nodeName;
		}
		if(!$found) {
			return null;
		}

		if(is_array($names) && count($names)>0) {
			return self::getNodeByName($node,$names);
		} else {
			return $node;
		}
	}

	public function __construct($parser) {

		$this->parser=$parser;

		//$zipFile='trial.docx';

		$this->zip=new ZipArchive();

		$this->stockColors=array('red'=>'FF0000','lime'=>'00FF00','skyblue'=>'0000FF','white'=>'FFFFFF','black'=>'000000');
	}

	public function import($zipFile) {
		$openResult=$this->zip->open($zipFile);

		$this->loadRootRelFile();
		$this->loadDocumentRelFile();
		$this->importDocument();
		if(isset($this->footFileName)) {
			$this->importFoot();
		}
		$this->zip->close();
	}

	public function export($zipFile,$lang='') {

		$this->lang=$lang;

		if(file_exists($zipFile)) {
			unlink($zipFile);
		}
		$openResult=$this->zip->open($zipFile,ZIPARCHIVE::OVERWRITE|ZIPARCHIVE::CREATE|ZIPARCHIVE::EXCL);

		$this->writeRootRelFile();
		$this->writeTypesFile();
		$this->writeDocRelFile();
		$this->writeStylesFile();
		$this->writeDocument();
		if($this->foot) {
			$this->writeFootFile();
		}
		$this->zip->close();
	}

	private function loadRootRelFile() {

		$rootRelsXml=new DOMDocument();

		$rootRelsString=$this->zip->getFromName(ROOTRELPATH);
		assert($rootRelsString!==FALSE);
		assert(strlen(trim($rootRelsString))>0);
		//echo htmlentities($rootRelsString);
		$rootRelsXml->loadXML($rootRelsString);
		//echo htmlentities($rootRelsXml->saveXML());

		$relsList=$rootRelsXml->getElementsByTagName('Relationship');

		assert($relsList->length>0);

		for($i=0;$i<$relsList->length;++$i) {
			$node=$relsList->item($i);
			$type=$node->getAttribute('Type');
			//echo $type;
			if($type==ODNS.'relationships/officeDocument') {
				$this->documentFileName=$node->getAttribute('Target');
				break;
			}
		}
		assert(isset($this->documentFileName));

	}

	private function loadDocumentRelFile() {
		$documentRelsXml=new DOMDocument();
		$dir=dirname($this->documentFileName).'/_rels';
		$path=$dir.'/'.basename($this->documentFileName).'.rels';

		$relsString=$this->zip->getFromName($path);

		if(!$relsString) {
			return;
		}

		$documentRelsXml->loadXML($relsString);

		$relsList=$documentRelsXml->getElementsByTagName('Relationship');

		if($relsList->length==0) {
			return;
		}

		for($i=0;$i<$relsList->length;++$i) {
			$node=$relsList->item($i);
			$type=$node->getAttribute('Type');
			//echo $type;
			if($type==ODNS.'relationships/footer') {
				$this->footFileName=$node->getAttribute('Target');
				break;
			}
		}
	}

	private function writeRootRelFile() {
		$rootRelsXml=new DOMDocument();


		$this->documentFileName='word/trial.xml';

		$rootRelsRootObj=$rootRelsXml->createElementNS(PACKAGENS.'relationships','Relationships');
		$rootRelsXml->appendChild($rootRelsRootObj);

		$rootRelsDocObj=$rootRelsXml->createElement('Relationship');
		$rootRelsDocObj->setAttribute('Type',ODNS.'relationships/officeDocument');
		$rootRelsDocObj->setAttribute('Target',$this->documentFileName);
		$rootRelsDocObj->setAttribute('Id','rId1');
		$rootRelsRootObj->appendChild($rootRelsDocObj);

		$rootRelsString=$rootRelsXml->saveXML();

		$writeResult=$this->zip->addFromString(ROOTRELPATH,$rootRelsString);
		assert($writeResult);
	}

	private function writeTypesFile() {
		$typesXml=new DOMDocument();

		$typesObj=$typesXml->createElementNS(PACKAGENS.'content-types','Types');
		$typesXml->appendChild($typesObj);

		$def=$typesXml->createElement('Default');
		$def->setAttribute('Extension','rels');
		$def->setAttribute('ContentType','application/vnd.openxmlformats-package.relationships+xml');
		$typesObj->appendChild($def);

		$def=$typesXml->createElement('Default');
		$def->setAttribute('Extension','xml');
		$def->setAttribute('ContentType','application/xml');
		$typesObj->appendChild($def);

		$def=$typesXml->createElement('Override');
		$def->setAttribute('PartName','/'.$this->documentFileName);
		$def->setAttribute('ContentType','application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');
		$typesObj->appendChild($def);

		$def=$typesXml->createElement('Override');
		$def->setAttribute('PartName','/'.dirname($this->documentFileName).'/styles.xml');
		$def->setAttribute('ContentType','application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml');
		$typesObj->appendChild($def);

		if($this->foot) {
			$def=$typesXml->createElement('Override');
			$def->setAttribute('PartName','/'.dirname($this->documentFileName).'/feet.xml');
			$def->setAttribute('ContentType','application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml');
			$typesObj->appendChild($def);
		}

		$this->zip->addFromString('[Content_Types].xml',$typesXml->saveXML());
	}

	private function writeDocRelFile() {
		$docRelsXml=new DomDocument();
		$docRelsRootObj=$docRelsXml->createElementNS(PACKAGENS.'relationships','Relationships');
		$docRelsXml->appendChild($docRelsRootObj);

		$rel=$docRelsXml->createElement('Relationship');
		$rel->setAttribute('Type',ODNS.'relationships/styles');
		$rel->setAttribute('Target','styles.xml');
		$rel->setAttribute('Id','rId1');
		$docRelsRootObj->appendChild($rel);

		if($this->foot) {
			$rel=$docRelsXml->createElement('Relationship');
			$rel->setAttribute('Type',ODNS.'relationships/footer');
			$rel->setAttribute('Target','feet.xml');
			$rel->setAttribute('Id','rId2');
			$docRelsRootObj->appendChild($rel);
		}

		$dir=dirname($this->documentFileName).'/_rels';
		$path=$dir.'/'.basename($this->documentFileName).'.rels';
		$this->zip->addFromString($path,$docRelsXml->saveXML());
	}

	private function writeStylesFile() {
		$stylesXml=new DomDocument();

		//setup the style for the font
		$stylesObj=$stylesXml->createElement('w:styles');
		$stylesObj->setAttribute('xmlns:w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
		$stylesXml->appendChild($stylesObj);


		$styleObj=$stylesXml->createElement('w:style');
		$styleObj->setAttribute('w:styleId','message');
		$styleObj->setAttribute('w:type','paragraph');
		$styleObj->setAttribute('w:customStyle','1');
		$stylesObj->appendChild($styleObj);

		$styleObj->appendChild($stylesXml->createElement('w:qFormat'));

		$nameObj=$stylesXml->createElement('w:name');
		$nameObj->setAttribute('w:val','Message');
		$styleObj->appendChild($nameObj);

		$basedOn=$stylesXml->createElement('w:basedOn');
		$basedOn->setAttribute('w:val','Normal');
		$styleObj->appendChild($basedOn);

		$pPr=$stylesXml->createElement('w:pPr');
		$styleObj->appendChild($pPr);

		$keepLinesObj=$stylesXml->createElement('w:keepLines');
		$pPr->appendChild($keepLinesObj);

		$link=$stylesXml->createElement('w:link');
		$link->setAttribute('w:val','messageChar');
		$styleObj->appendChild($link);

		$rPr=$stylesXml->createElement('w:rPr');
		$styleObj->appendChild($rPr);

		$fonts=$stylesXml->createElement('w:rFonts');
		$fonts->setAttribute('w:ascii','PW Extended');
		$fonts->setAttribute('w:hAscii','PW Extended');
		$fonts->setAttribute('w:hint','ascii');
		$rPr->appendChild($fonts);

		$sz=$stylesXml->createElement('w:sz');
		$sz->setAttribute('w:val','20');
		$rPr->appendChild($sz);

		$sz=$stylesXml->createElement('w:szCs');
		$sz->setAttribute('w:val','20');
		$rPr->appendChild($sz);



		$styleObj=$stylesXml->createElement('w:style');
		$styleObj->setAttribute('w:styleId','messageChar');
		$styleObj->setAttribute('w:type','character');
		$styleObj->setAttribute('w:customStyle','1');

		$rPr=$stylesXml->createElement('w:rPr');
		$styleObj->appendChild($rPr);

		$fonts=$stylesXml->createElement('w:rFonts');
		$fonts->setAttribute('w:ascii','PW Extended');
		$fonts->setAttribute('w:hAscii','PW Extended');
		$fonts->setAttribute('w:hint','ascii');
		$rPr->appendChild($fonts);

		$sz=$stylesXml->createElement('w:sz');
		$sz->setAttribute('w:val','20');//double the points size
		$rPr->appendChild($sz);

		$sz=$stylesXml->createElement('w:szCs');
		$sz->setAttribute('w:val','20');//double the points size
		$rPr->appendChild($sz);



		$styleObj=$stylesXml->createElement('w:style');
		$styleObj->setAttribute('w:styleId','heading 1');
		$styleObj->setAttribute('w:type','paragraph');
		$stylesObj->appendChild($styleObj);

		$styleObj->appendChild($stylesXml->createElement('w:qFormat'));

		$nameObj=$stylesXml->createElement('w:name');
		$nameObj->setAttribute('w:val','Heading 1');
		$styleObj->appendChild($nameObj);

		$basedOn=$stylesXml->createElement('w:basedOn');
		$basedOn->setAttribute('w:val','Normal');
		//$styleObj->appendChild($basedOn);

		$pPr=$stylesXml->createElement('w:pPr');
		$styleObj->appendChild($pPr);

		$keepLinesObj=$stylesXml->createElement('w:keepLines');
		$pPr->appendChild($keepLinesObj);

		$rPr=$stylesXml->createElement('w:rPr');
		$styleObj->appendChild($rPr);

		$sz=$stylesXml->createElement('w:sz');
		$sz->setAttribute('w:val','50');//double the points size
		$rPr->appendChild($sz);



		$styleObj=$stylesXml->createElement('w:style');
		$styleObj->setAttribute('w:styleId','heading 2');
		$styleObj->setAttribute('w:type','paragraph');
		$stylesObj->appendChild($styleObj);

		$styleObj->appendChild($stylesXml->createElement('w:qFormat'));

		$nameObj=$stylesXml->createElement('w:name');
		$nameObj->setAttribute('w:val','Heading 2');
		$styleObj->appendChild($nameObj);

		$basedOn=$stylesXml->createElement('w:basedOn');
		$basedOn->setAttribute('w:val','heading 1');
		//$styleObj->appendChild($basedOn);

		$pPr=$stylesXml->createElement('w:pPr');
		$styleObj->appendChild($pPr);

		$keepLinesObj=$stylesXml->createElement('w:keepLines');
		$pPr->appendChild($keepLinesObj);

		$rPr=$stylesXml->createElement('w:rPr');
		$styleObj->appendChild($rPr);

		$sz=$stylesXml->createElement('w:sz');
		$sz->setAttribute('w:val','38');//double the points size
		$rPr->appendChild($sz);


		$this->zip->addFromString(dirname($this->documentFileName).'/styles.xml',$stylesXml->saveXML());
	}

	private function writeFootFile() {
		$footXml=new DomDocument();

		$footRootObj=$footXml->createElement('w:ftr');
		$footXml->appendChild($footRootObj);

		//define the prefixes
		$footRootObj->setAttribute('xmlns:w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');

		$p=$footXml->createElement('w:p');
		$footRootObj->appendChild($p);

		$r=$footXml->createElement('w:r');
		$p->appendChild($r);

		$rPr=$footXml->createElement('w:rPr');
		$r->appendChild($rPr);

		if($this->lang!='') {
			$langObj=$footXml->createElement('w:lang');
			$langObj->setAttribute('w:val',$this->lang);
			$rPr->appendChild($langObj);
		}

		$t=$footXml->createElement('w:t');
		$t->appendChild($footXml->createTextNode($this->foot));
		$r->appendChild($t);

		$feetXmlString=$footXml->saveXML();

		$writeResult=$this->zip->addFromString(dirname($this->documentFileName).'/feet.xml',$feetXmlString);

	}

	private function writeDocument() {
		$this->docXml=new DOMDocument();
		$this->docXml->encoding='utf-8';
		$this->docXml->formatOutput=true;

		$docRootObj=$this->docXml->createElement('w:document');
		$this->docXml->appendChild($docRootObj);

		//define the prefixes
		$docRootObj->setAttribute('xmlns:ve','http://schemas.openxmlformats.org/markup-compatibility/2006');
		$docRootObj->setAttribute('xmlns:o','urn:schemas-microsoft-com:office:office');
		$docRootObj->setAttribute('xmlns:w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
		$docRootObj->setAttribute('xmlns:r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');

		$docBodyObj=$this->docXml->createElement('w:body');
		$docRootObj->appendChild($docBodyObj);

		$docBodyObj->appendChild($this->makeHeader('Trial data',1));

		$docBodyObj->appendChild($this->makeHeader('Profiles',2));

		$docBodyObj->appendChild($this->makeProfileTable());

		$docBodyObj->appendChild($this->makeHeader('Evidence',2,true));

		$docBodyObj->appendChild($this->makeEvidenceTable());

		$docBodyObj->appendChild($this->makeHeader('Script',2,true));

		$docBodyObj->appendChild($this->makeTextTable());

		$sectPr=$this->docXml->createElement('w:sectPr');
		$docBodyObj->appendChild($sectPr);

		if($this->foot) {
			$footerRef=$this->docXml->createElement('w:footerReference');
			$footerRef->setAttribute('r:id','rId2');
			$footerRef->setAttribute('w:type','default');
			$sectPr->appendchild($footerRef);
		}

		$docXmlString=$this->docXml->saveXML();

		$writeResult=$this->zip->addFromString($this->documentFileName,$docXmlString);

	}

	private function makeHeader($content,$level=2,$pageBreak=false) {
		$p=$this->docXml->createElement('w:p');

		$pPr=$this->docXml->createElement('w:pPr');
		$p->appendChild($pPr);

		$pStyle=$this->docXml->createElement('w:pStyle');
		$pStyle->setAttribute('w:val','heading '.$level);
		$pPr->appendChild($pStyle);

		if($pageBreak) {
			$pageBreakBefore=$this->docXml->createElement('w:pageBreakBefore');
			$pageBreakBefore->setAttribute('w:val','on');
			$pPr->appendChild($pageBreakBefore);
		}


		$r=$this->docXml->createElement('w:r');
		$p->appendChild($r);
		$this->setRunLang($r);

		$t=$this->docXml->createElement('w:t');
		$t->appendChild($this->docXml->createTextNode($content));
		$r->appendChild($t);

		return $p;
	}

	private function setRunLang($r) {
		if(!$rPr=self::getNodeByName($r,'rPr')) {
			$rPr=$this->docXml->createElement('w:rPr');
			$r->appendChild($rPr);
		}

		if($this->lang!='') {
			$langObj=$this->docXml->createElement('w:lang');
			$langObj->setAttribute('w:val',$this->lang);
			$langObj->setAttribute('w:eastAsia',$this->lang);
			$rPr->appendChild($langObj);
		}
	}

	private function colorToHex($val) {
		if(preg_match('#rgb\\(([0-9]+), *([0-9]+), *([0-9]+) *\\)#i',$val,$matches)) {
			$localColor=vsprintf('%1$02x%2$02x%3$02x',$matches);
		} elseif($val[0]=='#') {
			$localColor=substr($val,1);
		} else {
			assert(isset($this->stockColors[$val]));
			$localColor=$this->stockColors[$val];
		}
		return $localColor;
	}

	private function makeTextTable() {

		$tbl=$this->makeBaseTable(2);

		//var_dump($this->parser->profiles);

		foreach($this->parser->text as $line) {

			if($line['donnees_texte']['texte']=='') {
				continue;//we don't output lines that are not visible, I think
			}

			$row=$this->docXml->createElement('w:tr');
			$tbl->appendChild($row);


			//id of the row
			$idCell=$this->makeSimpleTableCell($line['id']);
			$row->appendChild($idCell);


			//Spoken text
			$textCell=$this->docXml->createElement('w:tc');
			$row->appendChild($textCell);

			$p=$this->docXml->createElement('w:p');
			$textCell->appendChild($p);

			$pPr=$this->docXml->createElement('w:pPr');
			$p->appendChild($pPr);

			$pStyle=$this->docXml->createElement('w:pStyle');
			$pStyle->setAttribute('w:val','message');
			$pPr->appendChild($pStyle);



			$text=$line['donnees_texte']['texte'];
			//echo htmlentities($text).'<br>';
			$textXml=new DOMDocument();
			$textXml->encoding='utf-8';
			$textXml->loadHTML($text);
			if(error_get_last()!=null) {
				die($text);
			}
			$baseColor=$this->colorToHex( $line['donnees_texte']['couleur'] );

			$node=self::getNodeByName($textXml,array('html','body'));
			assert(!is_null($node));
			$pnode=self::getNodeByName($textXml,array('html','body','p'));
			if(!is_null($pnode)) {
				//if there is no p node, there was a span tag around the full text
				$node=$pnode;
			}

			//echo htmlentities($textXml->saveXML());
			foreach($node->childNodes as $textNode) {

				if($textNode->localName=='span') {
					$localStyle=$textNode->getAttribute('style');
					if(trim($localStyle)=='') continue;
					foreach(explode(';',$localStyle) as $style) {
						if(trim($style)=='') continue;
						list($key,$val)=explode(':',$style);
						$key=trim($key);
						$val=trim($val);

						if($key!='color') {
							continue;
						}

						//echo "$key,$val";
						$localColor=$this->colorToHex($val);

					}
				} elseif(substr($textNode->localName,0,1)=='#') {
					$localColor=substr($textNode->localName,1,6);
				} else {
					$localColor=$baseColor;
				}

				$r=$this->docXml->createElement('w:r');
				$p->appendChild($r);

				$rPr=$this->docXml->createElement('w:rPr');
				$r->appendChild($rPr);

				$this->setRunLang($r);

				if($localColor!='FFFFFF') {//no white text on white papers
					$colorObj=$this->docXml->createElement('w:color');
					//echo "$localColor<br>\n";
					$colorObj->setAttribute('w:val',$localColor);
					$rPr->appendChild($colorObj);
				}

				//echo $text."\n<br>";
				$lines=explode('\\n',$textNode->textContent);
				//var_dump($lines);

				$t=$this->docXml->createElement('w:t');
				$t->setAttribute('xml:space','preserve');
				$r->appendChild($t);
				$t->appendChild($this->docXml->createTextNode($lines[0]));

				//note loop start position, it's not 0
				//we skip the first line since it's already handled
				for($i=1;$i<count($lines);++$i) {

					$br=$this->docXml->createElement('w:br');
					$r->appendChild($br);

					$t=$this->docXml->createElement('w:t');
					$t->setAttribute('xml:space','preserve');
					$r->appendChild($t);
					$t->appendChild($this->docXml->createTextNode($lines[$i]));
				}

			}

			//var_dump($line);

		}

		return $tbl;
	}

	private function makeProfileTable() {
		$tbl=$this->makeBaseTable(4);

		foreach($this->parser->profiles as $profile) {

			$row=$this->docXml->createElement('w:tr');
			$tbl->appendChild($row);

			$cell=$this->makeSimpleTableCell($profile['id']);
			$row->appendChild($cell);

			$cell=$this->makeSimpleTableCell($profile['nomlong']);
			$row->appendChild($cell);

			$cell=$this->makeSimpleTableCell($profile['nomcourt']);
			$row->appendChild($cell);

			$cell=$this->docXml->createElement('w:tc');
			$row->appendChild($cell);

			$cell->appendChild($this->description2P($profile['description']));

		}#end of foreach profiles

		return $tbl;
	}#end of method makeProfileTable

	private function makeEvidenceTable() {
		$tbl=$this->makeBaseTable(3);

		foreach($this->parser->evidence as $evidence) {

			$row=$this->docXml->createElement('w:tr');
			$tbl->appendChild($row);

			$cell=$this->makeSimpleTableCell($evidence['id']);
			$row->appendChild($cell);

			$cell=$this->makeSimpleTableCell($evidence['nom']);
			$row->appendChild($cell);

			$cell=$this->docXml->createElement('w:tc');
			$row->appendChild($cell);

			$desc=$this->description2P($evidence['description']);

			$cell->appendChild($desc);

			if(count($evidence['media_verifier'])) {
				$tbl2=$this->makeBaseTable(3);
				foreach($evidence['media_verifier'] as $id2=>$check) {
					$type=substr($check,0,3);
					$data=substr($check,3);

					$tr2=$this->docXml->createElement('w:tr');
					$tbl2->appendChild($tr2);

					$tr2->appendChild($this->makeSimpleTableCell($id2));
					$tr2->appendChild($this->makeSimpleTableCell($type));

					if($type!='txt') {
						$tr2->appendChild($this->makeSimpleTableCell($data));
					} else {
						$tc2=$this->docXml->createElement('w:tc');

						$tc2->appendChild($this->description2P($data));

						$tr2->appendChild($tc2);
					}
				}
				$cell->appendChild($tbl2);
				$cell->appendChild($this->docXml->createElement('w:p'));
			}





		}#end of foreach evidence

		return $tbl;
	}#end of method makeEvidenceTable

	private function description2P($desc) {

		$p=$this->docXml->createElement('w:p');

		$r=$this->docXml->createElement('w:r');
		$p->appendChild($r);

		$this->setRunLang($r);

		$lines=explode('\\n',$desc);

		$didFirstLine=false;

		foreach($lines as $line) {
			$line=html_entity_decode($line,ENT_QUOTES,'UTF-8');
			if($didFirstLine) {
				$br=$this->docXml->createElement('w:br');
				$r->appendChild($br);
			}
			$didFirstLine=true;

			$t=$this->docXml->createElement('w:t');
			$t->setAttribute('xml:space','preserve');
			$r->appendChild($t);

			$t->appendChild($this->docXml->createTextNode($line));
		}#end of foreach $lines

		return $p;
	}

	private function makeSimpleTableCell($content) {
		$cell=$this->docXml->createElement('w:tc');

		$p=$this->docXml->createElement('w:p');
		$cell->appendChild($p);

		$r=$this->docXml->createElement('w:r');
		$p->appendChild($r);

		$this->setRunLang($r);

		$t=$this->docXml->createElement('w:t');
		$r->appendChild($t);

		$t->appendChild($this->docXml->createTextNode($content));

		return $cell;
	}

	private function makeBaseTable($fieldCount) {
		$tbl=$this->docXml->createElement('w:tbl');


		$tblPr=$this->docXml->createElement('w:tblPr');
		$tbl->appendChild($tblPr);

		$tblBorders=$this->docXml->createElement('w:tblBorders');
		$tblPr->appendChild($tblBorders);

		foreach(array('top','left','bottom','right','insideH','insideV') as $name) {
			$border=$this->docXml->createElement('w:'.$name);
			$border->setAttribute('w:val','single');
			$tblBorders->appendChild($border);
		}

		$tblGrid=$this->docXml->createElement('w:tblGrid');
		$tbl->appendChild($tblGrid);

		for($i=0;$i<$fieldCount;++$i) {
			$tblCol=$this->docXml->createElement('w:tblCol');
			$tblGrid->appendChild($tblCol);
		}

		return $tbl;
	}

	private function importDocument() {

		$this->docXml=new DOMDocument();
		$this->docXml->encoding='utf-8';

		$docXmlString=$this->zip->getFromName($this->documentFileName);
		assert($docXmlString!==FALSE);
		assert(strlen(trim($docXmlString))>0);
		//echo htmlentities($rootRelsString);
		$this->docXml->loadXML($docXmlString);

		$bodyobj=self::getNodeByName($this->docXml->firstChild,'body');

		foreach($bodyobj->childNodes as $node) {

			switch($node->localName) {

				case 'p':
					$section=self::pToString($node);
				break;

				case 'tbl':

					switch($section) {

						case 'Script':
							$this->importTextTable($node);
						break;

						case 'Profiles':
							$this->importProfileTable($node);
						break;

						case 'Evidence':
							$this->importEvidenceTable($node);
						break;

					}
				break;

			}

		}

	}

	private static function pToString($p) {
		$s='';
		foreach($p->childNodes as $run) {
			if($run->localName!='r') continue;
			foreach($run->childNodes as $runNode) {
				if($runNode->localName=='t') {
					$s.=$runNode->textContent;
				}
			}
		}
		return $s;
	}

	private function importTextTable($tbl) {
		//echo '<ul>';
		foreach($tbl->childNodes as $row) {
			if($row->localName!='tr') {
				continue;
			}
			$cells=array();
			foreach($row->childNodes as $cell) {
				if($cell->localName!='tc') {
					continue;
				}
				$cells[]=$cell;
			}
			assert(count($cells)==2);

			$id=self::pToString(self::getNodeByName($cells[0],'p'));

			//echo '<li>';var_dump($id); echo '</li>';
			//var_dump($speaker->textContent);

			$text='';
			$p=self::getNodeByName($cells[1],'p');

			$globalColor='white';
			$color=$globalColor;
			$colorState='closed';

			foreach($p->childNodes as $run) {
				if($run->localName!='r') {
					continue;
				}

				foreach($run->childNodes as $runNode) {
					switch($runNode->localName) {
						case 't':
							if($colorState=='opening') {
								$colorState='open';

								$text.='<span style="color: '.$color.'">';
							}
							$text.=htmlentities($runNode->textContent,ENT_NOQUOTES,'utf-8');
						break;

						case 'rPr':

							$colorObj=self::getNodeByName($runNode,'color');

							if(!$colorObj) {
								continue;//word is going to add boring runproperties, i bet on it
							}

							$colorVal=$colorObj->getAttribute('w:val');

							$stockColor=array_search($colorVal,$this->stockColors);
							if($stockColor) {
								if($stockColor=='black') {
									$colorVal='white';
								} else {
									$colorVal=$stockColor;
								}
							} else {
								$colorVal='#'.$color;
							}

							if($colorVal==$color) {
								continue;//and i bet it will set duplicate colors too
							}
							$color=$colorVal;
							if($text=='' && $stockColor) {
								$globalColor=$colorVal;
								continue;
							}
							if($colorState=='open') {
								$text.='</span>';
								$colorState='closed';
							}
							if($color==$globalColor) {
								continue;//no need to open a tag if it's the global color
							}

							$colorState='opening';
						break;

						case 'br':
							$text.='\\n';
						break;
					}#end switch runNode->localname
				}#end foreach runnodes

				if($colorState=='open') {
					$text.='</span>';
					$colorState='closed';
				}
				$color=$globalColor;

			}#end foreach p nodes

			$this->parser->text[$id]['donnees_texte']['texte']=$text;
			$this->parser->text[$id]['donnees_texte']['couleur']=$globalColor;
		}#end foreach table nodes

		//echo '</ul>';
	}#end of method importTextTable

	private function importProfileTable($tbl) {
		foreach($tbl->childNodes as $row) {
			if($row->localName!='tr') {
				continue;
			}
			$cells=array();
			foreach($row->childNodes as $cell) {
				if($cell->localName!='tc') {
					continue;
				}
				$cells[]=$cell;
			}
			assert(count($cells)==4);

			$id=self::pToString(self::getNodeByName($cells[0],'p'));
			$name=self::pToString(self::getNodeByName($cells[1],'p'));
			$cname=self::pToString(self::getNodeByName($cells[2],'p'));
			$desc=self::P2Description(self::getNodeByName($cells[3],'p'));

			$profile=&$this->parser->profiles[$id];
			$profile['nomlong']=$name;
			$profile['nomcourt']=$cname;
			$profile['description']=$desc;
		}
	}

	private function p2Description($p) {
		$o='';
		foreach($p->childNodes as $run) {

			if($run->localName!='r') continue;

			foreach($run->childNodes as $node) {

				if($node->localName=='br') {
					$o.='\\n';
				} elseif($node->localName=='t') {
					$o.=htmlentities($node->textContent,ENT_NOQUOTES,'UTF-8');
				}
			}
		}
		return $o;
	}
	private function importEvidenceTable($tbl) {
		foreach($tbl->childNodes as $row) {
			if($row->localName!='tr') {
				continue;
			}
			$cells=array();
			foreach($row->childNodes as $cell) {
				if($cell->localName!='tc') {
					continue;
				}
				$cells[]=$cell;
			}
			assert(count($cells)==3);

			$id=self::pToString(self::getNodeByName($cells[0],'p'));

			$evidence=&$this->parser->evidence[$id];

			$name=self::pToString(self::getNodeByName($cells[1],'p'));
			$desc=self::P2Description(self::getNodeByName($cells[2],'p'));

			$check=self::getNodeByName($cells[2],'tbl');

			if($check) {
				foreach($check->childNodes as $row2) {
					if($row2->localName!='tr') continue;

					$cells2=array();
					foreach($row2->childNodes as $cell2) {
						if($cell2->localName!='tc') {
							continue;
						}
						$cells2[]=$cell2;
					}

					assert(count($cells2)==3);

					$id2=self::pToString(self::getNodeByName($cells2[0],'p'));
					$type=self::pToString(self::getNodeByName($cells2[1],'p'));

					if($type!='txt') {
						$data=self::pToString(self::getNodeByName($cells2[2],'p'));
					} else {
						$data=self::P2Description(self::getNodeByName($cells2[2],'p'));
					}

					$evidence['media_verifier'][$id2]=$type.$data;


				}
			}


			$evidence['nom']=$name;
			$evidence['description']=$desc;
		}


	}
	private function importFoot() {
		$footXml=new DOMDocument();

		assert(!empty($this->footFileName));

		$footerData=$this->zip->getFromName(dirname($this->documentFileName).'/'.$this->footFileName);

		assert(!empty($footerData));

		$footXml->loadXml($footerData);

		$p=self::getNodeByName($footXml,array('ftr','p'));

		$this->foot='';

		foreach($p->childNodes as $node) {
			if($node->localName!='r') {
				continue;
			}

			foreach($node->childNodes as $t) {
				if($t->localName!='t') {
					continue;
				}
				$this->foot.=$t->textContent;
			}
		}

	}
}#end of class WordFile

if(isset($_REQUEST['mode'])) {
	$mode=$_REQUEST['mode'];
} else {
	$mode='importform';
}

$parser= new AAParser();
$parser->deassembleFile('data.txt');

/*
$wf=new WordFile($parser);
$wf->import('trial.docx');
file_put_contents('assembled.txt',$parser->assembleString());
exit;

$mode='export';*/

if($mode=='export') {

	$wfName=tempnam('','AATrial');

	//die($wfName);

	$wf=new WordFile($parser);
	$wf->foot='AATrial dump';
	$wf->export($wfName,'en-US');

	header('Content-type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header('Content-Disposition: attachment; filename="trial.docx"');
	readfile($wfName);

	unlink($wfName);
} elseif($mode=='import') {

	$wf=new WordFile($parser);
	$wf->import($_FILES['docx']['tmp_name']);

	file_put_contents('assembled.txt',$parser->assembleString());

	echo 'OK';

	if($wf->foot) {
		echo $wf->foot;
	}

} elseif($mode=='importform') {

?><form method="post"
enctype="multipart/form-data" action="?">
<input type="file" name="docx">
<input type="hidden" name="mode" value="import">
<button type="subbmit">Import</button>
</form>
<a href="?mode=export">Export</a>
<?php
}