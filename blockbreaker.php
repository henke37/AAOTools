<?php

class block {
	var $from;
	var $begin;
	var $end;
	var $parsed;

	function __construct($begin) {
		assert($begin>=1);
		//echo "Created new block at ".$begin."<br> \n";
		$this->from=array();
		$this->begin=$begin;
		$this->parsed=false;
	}
}

class blockBreaker {

	var $parser;
	var $text;

	var $blocks;
	var $unparsedBlocks;

	var $toAdd;

	var $hidden;
	var $crossexams;


	function addBlock($from,$to,$type='normal',$reason='') {
		assert($from>=1);
		assert($to>=1);
		assert(is_string($type));
		assert($type!='');
		assert(is_string($reason));
		$this->toAdd[]=array('from'=>$from,'to'=>$to,'type'=>$type,'reason'=>$reason);
	}

	function getBlock($id) {
		foreach($this->blocks as $block) {
			if($block->begin <= $id && $block->end >= $id) {
				return $block;
			}
		}
		return null;
	}

	function realAddBlock($from,$to,$type,$reason) {

		assert($from>=1);
		assert($to>=1);

		//first scan for an existing block
		$block=null;
		foreach($this->blocks as $candidateBlock) {
			//special logic to deal with blocks not yet parsed(end=null) that should still be merged
			if($to==$candidateBlock->begin || ($to>$candidateBlock->begin  && $to<=$candidateBlock->end)) {
				$block=$candidateBlock;
				break;
			}
		}

		//echo "$from, $to, $type ";

		if($block==null) {
			$block=new block($to);
			$block->from[]=array('from'=>$from,'to'=>$to,'type'=>$type,'reason'=>$reason);
			$this->unparsedBlocks[$to]=$block;
			$this->blocks[$to]=$block;

		} else {
			$block->from[]=array('from'=>$from,'to'=>$to,'type'=>$type,'reason'=>$reason);
			//echo "Reused a block<br>\n";
			if($block->begin!=$to) {
				//split the block
				//echo "Split a block!<br>\n";
				$earlyFrom=array();
				$lateFrom=array();
				foreach($block->from as $jump) {
					if($jump['to']<$to) {
						$earlyFrom[]=$jump;
					} else {
						$lateFrom[]=$jump;
					}
				}
				$lateFrom[]=array('from'=>$to-1,'to'=>$to,'type'=>'normal','reason'=>'split');

				$newBlock=new block($from,$to);
				//I am more or less replacing all the data in the new object, aren't I polite?
				$newBlock->begin=$to;
				$newBlock->from=$lateFrom;
				$newBlock->end=$block->end;
				$newBlock->parsed=$block->parsed;
				if(!$newBlock->parsed) {
					$this->unparsedBlocks[$to]=$newBlock;
				}

				$this->blocks[$to]=$newBlock;

				$block->from=$earlyFrom;
				$block->end=$to-1;
			}
		}
	}


	function followCall($id) {
		assert($id>=1);

		for($endBlock=false;!$endBlock;++$id) {

			assert(!isset($this->parsedLines[$id]));
			$this->parsedLines[$id]=true;

			$line=$this->text[$id];
			$action=$line['tableau_action'];
			$action_name=$action['nom_action'];
			$parms=$action['param_action'];

			switch($action_name) {

				case ''://first for quick stepping in debugger
				break;

				case 'AllerCI':
					$this->addBlock($id,$parms[0],'press');
					$this->addBlock($id,$id+1);
					$endBlock=true;
				break;

				case 'AllerMessage':
					$this->addBlock($id,$parms[0],'jump');
					$endBlock=true;
				break;

				case 'ChoixEntre4':
					$this->addBlock($id,$parms[4+0]);
					$this->addBlock($id,$parms[4+1]);
					$this->addBlock($id,$parms[4+2]);
					$this->addBlock($id,$parms[4+3]);
					$endBlock=true;
				break;

				case 'ChoixEntre2':
					$this->addBlock($id,$parms[2+0]);
					$this->addBlock($id,$parms[2+1]);
					$endBlock=true;
				break;

				case 'DemanderEltVerrous':
					$this->addBlock($id,$parms[3]);
					$this->addBlock($id,$parms[2],'wrong');
					$this->addBlock($id,$parms[4],'exit');
					$endBlock=true;
				break;

				case 'DemanderPreuve':
					foreach($parms[3] as $jump) {
						$this->addBlock($id,$jump);
					}
					$this->addBlock($id,$parms[2],'wrong');
					$endBlock=true;
				break;

				case 'DiscussionEnquete':
					foreach($parms as $args) {
						$this->addBlock($id,$args[0],'talk');
					}
					$endBlock=true;
				break;

				case 'DiscussionEnqueteV2':
					foreach($parms[0] as $jump) {
						$this->addBlock($id,$jump,'talk');
					}
					if($parms[3]) {
						$this->addBlock($id,$parms[3],'lock');
					}
					$endBlock=true;
				break;

				case 'EvaluerCondition':
					$this->addBlock($id,$parms[1],'condOk');
					$this->addBlock($id,$parms[2],'condFail');
					$endBlock=true;
				break;

				case 'FinDuJeu':
					//end of game
					$endBlock=true;
				break;

				case 'FinVerrous':
					$this->addBlock($id,$parms[1],'return');
					$endBlock=true;
				break;

				case 'LancerCI':
					foreach($parms[3] as $key=>$jump) {
						$jmpSource=$parms[0][$key];
						if(!$jmpSource) {
							continue;
						}
						$this->addBlock($jmpSource,(int)$jump,'contradiction');
					}

					//no condition for smart return, since it's no longer used
					if(is_int($parms[4])) {
						$this->addBlock($id,$parms[4],'wrong');
					}
					$this->addBlock($id,$id+1);
					$endBlock=true;
				break;

				case 'TesterVar':
					foreach($parms[2] as $jump) {
						$this->addBlock($id,$jump,'condOk');
					}
					$this->addBlock($id,$parms[3],'condFail');
					$endBlock=true;
				break;

				case 'PointerImage':
					foreach($parms[6] as $jump) {
						$this->addBlock($id,$jump);
					}
					$this->addBlock($id,$parms[5],'wrong');
					$endBlock=true;
				break;

				case 'ReglerGameOver':
					$this->addBlock($id,$parms[0],'gameOver');
					$this->addblock($id,$id+1);
					$endBlock=true;
				break;

				case 'RetourCI':
					$this->addBlock($id,$parms[0],'return');
					$endBlock=true;
				break;

				case 'RepondreQuestion':
					$this->addBlock($id,$parms[3+0]);
					$this->addBlock($id,$parms[3+1]);
					$this->addBlock($id,$parms[3+2]);
					$endBlock=true;
				break;

				case 'SeDeplacer':
					foreach($parms as $args) {

						if(!isset($args[1])) {
							//bug in generator, creates a dud move
							continue;
						}
						assert($args[0]>=1);

						$jump=$this->parser->locations[$args[0]];

						//echo 'z to location '.$args[0].' at message '.$jump.".<br>\n";

						$this->addBlock($id,$jump,'move');
					}
					$endBlock=true;
				break;

				case 'AjouterCI':
				case 'MasquerMessage':
					//break out the hider/revealers
					//action arrows are added later
					$endBlock=true;
					$this->addBlock($id,$id+1);
				break;

				case 'MasquerIntroLieu':
				case 'DevoilerIntroLieu':
					$endBlock=true;
					$this->addBlock($id,$id+1);
					assert($parms[0]>=0);
					$jump=$this->parser->locations[$parms[0]]+1;
					$this->addBlock($id,$jump,'hide');
				break;

				case 'DevoilerLieu':
				case 'MasquerLieu':
					$endBlock=true;
					assert($parms[0]>=0);
					$this->addBlock($id,$this->parser->locations[$parms[0]],'hide');
					$this->addBlock($id,$id+1);
				break;

				case 'DevoilerVerrousLieu':
				case 'MasquerVerrousLieu':
					assert($parms[0][1]);
					$talkAction=$this->text[$parms[0][1]]['tableau_action'];
					assert($talkAction['nom_action']=='DiscussionEnqueteV2');
					$lockID=$talkAction['param_action'][3];
					if($lockID) {//people who forget to create the lock
						$this->addBlock($id,$lockID,'hide');
						$endBlock=true;
						$this->addBlock($id,$id+1);
					}
				break;
			}

			if(!isset($this->text[$id+1])) {
				$endBlock=true;
			} elseif(!$endBlock) {
				switch($this->text[$id+1]['tableau_action']['nom_action']) {
					case 'CreerLieu':
					case 'LancerCI':
					case 'pauseCI':
					case 'AjouterCI':
					case 'MasquerMessage':
					case 'MasquerIntroLieu':
					case 'DevoilerIntroLieu':
					case 'DevoilerLieu':
					case 'MasquerLieu':
					case 'DevoilerVerrousLieu':
					case 'MasquerVerrousLieu':
						$endBlock=true;
						$this->addBlock($id,$id+1,'normal','forcedNew');
					break;

					default:
						if(isset($this->blocks[$id+1])) {
							$endBlock=true;
							$this->addBlock($id,$id+1,'normal','merged');
						}
					break;
				}

			}#end of if

			if(isset($this->hidden[$id])) {
				$this->addBlock($id,$id+1,'hidden');
				foreach($this->hidden[$id] as $hider) {
					$this->addBlock($hider,$id,'hide');
				}
			}

		}#end of for ++$line



		return $id-1;
	}#end of function followCall

	function blockBreaker($parser) {

		assert($parser);
		$this->parser=$parser;
		assert($this->parser->text);
		$this->text=$this->parser->text;

		assert(is_array($this->parser->locations));

		$this->blocks=array();
		$this->crossexams=array();
		$this->parsedLines=array();

		$firstBlock=new block(1);
		$this->blocks[1]=$firstBlock;
		$this->unparsedBlocks[]=$firstBlock;//start at the first line

		//prescan for messages that are revealed or hidden during gameplay
		//we are not going to care about psychelocks, locations or talk subjects being hidden, since that's not needed
		//this is needed only because a hidden frame may be redirecting flow and the flow redirection can be skipped
		//that is used for investigation intros, so it is a common occurence
		$this->hidden=array();
		foreach($this->text as $id=>$line) {
			switch($line['tableau_action']['nom_action']) {
				case 'AjouterCI':
				case 'MasquerMessage':
					if(!isset($this->hidden[ $line['tableau_action']['param_action'][0] ])) {
						$this->hidden[ $line['tableau_action']['param_action'][0] ]=array();
					}
					$this->hidden[ $line['tableau_action']['param_action'][0] ] []=$id;
				break;

				case 'LancerCI':
					$this->crossexams[]=$id;
				break;
			}
		}

		//add crossexam back links
		foreach($this->crossexams as $id) {

			for(;;) {
				$id+=1;
				$frame=$this->text[$id];

				$action=$frame['tableau_action']['nom_action'];

				if($action=='pauseCI') {
					break;
				}

				if($action!='AllerMessage') {
					$this->realAddBlock($id,$id-1,'normal','CEBack');
				}

				if(isset($this->hidden[$id])) {
					$this->realAddBlock($id,$id-1,'hidden','CEBack');
				}
			}
		}




		while(count($this->unparsedBlocks)>0) {

			$this->toAdd=array();//clear the buffer from the last itteration

			$block=array_pop($this->unparsedBlocks);
			assert($block instanceof block);
			assert(is_int($block->begin));
			assert($block->begin>=1);
			assert(!$block->parsed);
			//echo 'parsing block at '.$block->begin."<br>\n";
			$block->parsed=true;
			$block->end=$this->followCall($block->begin);

			foreach($this->toAdd as $add) {
				$this->realAddBlock($add['from'],$add['to'],$add['type'],$add['reason']);
			}
		}




	}#end of function blockBreak;

}#end of class blockBreaker