<?php
header('Content-Type: application/xhtml+xml; charset=utf-8');

echo '<'.'?xml version="1.0" encoding="utf-8"?'.'>';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head><title>AA trial decoder by henke37</title>
<style>
* {
   margin:0px;
   padding:0px;
   border:none;
}

@font-face {
   font-family:"Police PW";
   src:url("PWinternational.ttf");
}

body {
   margin:10px;
   background:#f0f3f4;
   font-family:Arial, Helvetica, sans-serif;
}

table {
   margin-bottom: 1em;
   padding:0;
   width:100%;
   border-collapse:collapse;
   border-top:solid thin #999;
   border-bottom:solid thin #999;
   border-left:solid thin #ccc;
   border-right:solid thin #ccc;
}

/*table + table { margin-top:1em; } */

table caption {
   margin-bottom:2px;
   padding:5px;
   width:auto;
   background:#bec1c2;
   border:solid thin #999;
   font-family:Georgia, serif;
   font-size:12pt;
   font-weight:400;
   text-shadow:2px 2px 0 #999;
   color:white;
	 caption-side:top;
}

table th,
table td {
   padding:2px;
   font-size:14px;
}

table th {
   background:#bec1c2;
   border:solid thin #999;
   text-shadow:2px 2px 0px #999;
}

table tr td {
   border:solid thin;
   border-top-color:#999;
   border-right-color:#ccc;
   border-bottom-color:#999;
   border-left-color:#ccc;
}

td table { margin-top:0.5em; }

td ul, td ol { padding-left:1em; font-size:small; }

.dialogue {
   background:#333;
   font-family:"Police PW", "PW Extended", Verdana, sans-serif;
   font-size:10px;
   color:white;
   line-height:17px;
}
</style></head>
<body>
<?php

require dirname(__FILE__).'/lib.php';

$parser=new AAParser();

if(isset($_GET['file'])) {
	$parser->deassembleFile($_GET['file']);
} else {
	$parser->deassembleFile('data.txt');
}

function check($arr) {
	if(count($arr)==0) return;
	?><table>
	<caption>Check</caption>
	<thead><tr><th>Type</th><th>Data</th></tr></thead>
	<tbody>
	<?php
	foreach($arr as $line) {
		$type=substr($line,0,3);
		$data=substr($line,3);
		printf('<tr><td>%s</td><td>',$type);
		switch($type) {
			case 'txt':
				echo formatText($data);
			break;

			case 'img':
			case 'son':
				echo htmlentities($data);
			break;
		}
		echo '</td></tr>';
	}
	?></tbody></table><?php
}


function formatText($data) {
	$data=str_replace('<BR>','',$data);
	return str_replace('\\n',"\n<br />",$data);
}

function translateEvidence($type) {
	return $type=='preuve'?'evidence':'profile';
}

function chooseLoop($arr,$count) {
	echo '<ul>';
	for($i=0;$i<$count;++$i) {
		printf(
			'<li>"%s" jump to message # %u.</li>',
			$arr[$i],
			$arr[$i+$count]
		);
	}
	echo '</ul>';
}

?>
<table>
<caption>Profile data</caption>
<thead><tr><th>ID</th><th>CR Name</th><th>Talkbox name</th><th>Description</th><th>Show if start after message</th><th>Base</th><th>Icon</th></tr></thead>
<tbody>

<?php
foreach($parser->profiles as $line) {
	?><tr><?php

	printf('<td>%u</td>',$line['id']);
	printf('<td>%s</td>',htmlentities($line['nomlong']));
	printf('<td>%s</td>',htmlentities($line['nomcourt']));
	printf('<td>%s</td>',formatText($line['description']));
	printf('<td>%u</td>',$line['apparition']);
	printf('<td>%s</td>',htmlentities($line['base']));
	printf('<td>%s</td>',htmlentities($line['icone']));

	/*
	echo '<td><pre>';
	print_r($line);
	echo '</pre></td>';
	*/
	?></tr><?php
}

?>
</tbody>
</table>

<table>
<caption>Evidence data</caption>
<thead><tr><th>Id</th><th>Name</th><th>Base</th><th>Show if start after message</th><th>Description</th></tr></thead>
<tbody>
<?php
foreach($parser->evidence as $line) {
	?><tr><?php

	printf('<td>%u</td>',$line['id']);
	printf('<td>%s</td>',htmlentities($line['nom']));
	printf('<td>%s</td>',htmlentities($line['base']));
	printf('<td>%u</td>',$line['apparition']);
	printf('<td>%s',formatText($line['description']));
	check($line['media_verifier']);
	echo '</td>';


	/*echo '<td><pre>';
	print_r($line);
	echo '</pre></td>';*/

	?></tr><?php
}
?>
</tbody></table>

<table>
<caption>Locations</caption>
<thead>
<tr>
<th>ID</th>
<th>Begin</th>
<th>Title</th>
</tr>
</thead>
<tbody>
<?php
foreach($parser->locations as $id=>$begin) {
	printf('<tr><td>%u</td><td>%u</td><td>%s</td></tr>',$id,$begin,htmlentities($parser->text[$begin]['tableau_action']['param_action'][1]));
}
?>
</tbody>
</table>

<table>
<caption>Text data</caption>
<thead>
<tr>
<th>ID</th>
<th>BG</th>
<th>Char id</th>
<th>Stance id</th>
<th>Message</th>
<th>Wait</th>
<th>Hidden</th>
<th>Merge</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php

$actions=array();

foreach($parser->text as $line) {
	if(is_string($line)) {
		echo '<td colspan="8">';
		echo $l;
		echo '</td>';
		continue;
	}

	echo "<tr>";
	echo '<td>'.$line['id'].'</td>';
	echo '<td>'.$line['fond']['chemin_fond'].'</td>';
	echo '<td>'.$line['infos_auteur']['id_auteur'].'</td>';
	echo '<td>'.$line['perso']['chemin_perso'].'</td>';
	echo '<td style="color: '.htmlentities($line['donnees_texte']['couleur']).'" class="dialogue">'.formatText($line['donnees_texte']['texte']).'</td>';
	echo '<td>'.$line['delai'].'</td>';
	echo '<td>'.($line['cache']==1?'Yes':'No').'</td>';
	echo '<td>'.($line['lie_au_suivant']==1?'Yes':'No').'</td>';

	$action=$line['tableau_action'];

	echo '<td>';

	switch($action['nom_action']) {
		case '':
		break;

		case 'AfficherElement':
			echo 'Show ';
			echo translateEvidence($action['param_action'][0]);
			echo ' # '.$action['param_action'][1].'.';
		break;

		case 'afficherVerrous':
			echo 'Show phychelocks';
		break;

		case 'AllerCI':
			echo 'Branch to # '.$action['param_action'][0].' at press.';
			//also make sure that the press button is shown
		break;

		case 'AllerMessage':
			echo 'Jump to message # '.$action['param_action'][0].'.';
		break;

		case 'AjouterCI':
			echo 'Reval message # '.$action['param_action'][0].'.';
		break;

		case 'briserVerrou':
			echo 'Break one phychlock';
		break;

		case 'CreerLieu':
			echo 'Begin location ';
			echo 'ID # '.$action['param_action'][0];
			echo ' "'.htmlentities($action['param_action'][1]).'", ';
			echo $action['param_action'][2]?'hidden':'visible';
			echo ".\r\n<br />";
			echo 'Ends at message # '.$action['param_action'][3].'.';
			//set return pointer to this message
		break;

		case 'ChoixEntre4':
			echo 'Pick one of four options:';
			chooseLoop($action['param_action'],4);
		break;

		case 'ChoixEntre2':
			echo 'Pick one of two options:';
			chooseLoop($action['param_action'],2);
		break;

		case 'DefinirVar':
			printf(
				'Set var "%s" to "%s".',
				$action['param_action'][0],
				htmlentities($action['param_action'][1])
			);;
		break;

		case 'DemanderEltVerrous':
			echo 'Ask for evidence (Psychelock)';
			?><table>
			<thead><tr><th>Type</th><th>id</th></tr></thead>
			<tbody>
			<?php
			foreach($action['param_action'][0] as $key=>$type) {
				printf(
					'<tr><td>%s</td><td>%u</td></tr>',
					translateEvidence($type),
					$action['param_action'][1][$key]
				);
			}
			?></tbody></table>
			<?php
			printf(
				'Goto message # %u if match, # %u if not.',
				$action['param_action'][3],
				$action['param_action'][2]
			);
			echo '<br />';
			printf('Exit to message # %u',$action['param_action'][4]);
		break;

		case 'DemanderPreuve':
			if(isset($action['param_action'][4])) {
				switch ($action['param_action'][4]) {
					case 'preuves':
						echo 'Ask for piece of evidence.<br />';
					break;

					case 'profils':
						echo 'Ask for a profile.<br />';
					break;

					default:
						echo 'Ask for anything in the courtrecord.<br />';
					break;
				}
			} else {
				echo 'Ask for evidence.<br />';
			}
			foreach($action['param_action'][0] as $key=>$type) {
				echo translateEvidence($type);
				echo ' # '.$action['param_action'][1][$key];
				echo ', go to # '.$action['param_action'][3][$key];
				echo '<br />';
			}
			echo 'Else, go to # '.$action['param_action'][2].'.';
		break;



		case 'DevoilerConversation':
			$args=$action['param_action'][1];
			printf(
				'Reveal conversation # %u(%u) at location # %u.',
				$args[0],
				$args[1],
				$action['param_action'][0]
			);
		break;

		case 'DevoilerElement':
			printf(
				'Reveal %s # %u.',
				translateEvidence($action['param_action'][0]),
				$action['param_action'][1]
			);
		break;

		case 'DevoilerElements':
			echo 'Reveal ';
			foreach($action['param_action'][0] as $index=>$type) {

				if($index!=0) {
					if($index==count($action['param_action'][0])-1) {
						echo ' and ';
					} else {
						echo ', ';
					}
				}

				echo translateEvidence($type);
				echo ' # '.$action['param_action'][1][$index];

			}
			echo '.';

		break;

		case 'DevoilerLieu':
			echo 'Reveal location # '.$action['param_action'][0].'.';
		break;

		case 'DevoilerIntroLieu':
			echo 'Enable intro for location # '.$action['param_action'][0].'.';
		break;

		case 'DevoilerVerrousLieu':
			printf(
				'Enable psychelock at location # %u (%u)',
				$action['param_action'][0][0],
				$action['param_action'][0][1]
			);
		break;

		case 'DiscussionEnquete':
			?>
			Show talk topics
			<table>
			<thead><tr><th>Id</th><th>Topic name</th><th>Jump location</th><th>Hidden</th></tr></thead>
			<tbody><?php
			foreach($action['param_action'] as $key=>$args) {
				echo '<tr>';
				echo '<td>'.($key+1).'</td>';
				echo '<td>'.htmlentities($args[1]).'</td>';
				echo '<td>'.$args[0].'</td>';
				echo '<td>'.($args[2]?'Yes':'No').'</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			//var_dump($action['param_action']);
			//oh, and provide a way to return to the action picker
		break;

		case 'DiscussionEnqueteV2':
			//Same thing, new format
			?>
			Show talk topics
			<table>
			<thead><tr><th>Id</th><th>Topic name</th><th>Jump location</th><th>Hidden</th></tr></thead>
			<tbody><?php
				foreach($action['param_action'][0] as $key=>$jump) {
					echo '<tr>';
					printf('<td>%u</td>',$key+1);

					printf('<td>%s</td>',$action['param_action'][1][$key]);
					printf('<td>%u</td>',$jump);
					printf('<td>%s</td>',($action['param_action'][2][$key]?'Yes':'No'));
					echo '</tr>';
				}
			?></tbody></table>
			<?php
				if($action['param_action'][3]) {
					printf('Psychelock at message # %u',$action['param_action'][3]);
					if($action['param_action'][4]) {
						echo ', hidden';
					}
					echo '.';
				}
				//var_dump($action['param_action']);
		break;

		case 'EvaluerCondition':
			echo 'Test expr "'.$action['param_action'][0].'" jump to # ';
			echo $action['param_action'][1].' if true, else jump to # '.$action['param_action'][2].'.';
		break;

		case 'FinDuJeu':
			echo 'End game';
		break;

		case 'FaireClignoterVie':
			echo 'Flash '.$action['param_action'][0].'HP';
		break;

		case 'FinVerrous':
			printf(
				'End Psychelock at location # %u, return to line # %u',
				$action['param_action'][0],
				$action['param_action'][1]
			);
		break;

		case 'InputVar':
			echo 'Prompt for ';
			switch($action['param_action'][1]) {
				case 'chaine':
					echo 'a string';
				break;

				case 'mot':
					echo 'a word';
				break;

				case 'nb':
					echo 'an integer';
				break;

				default:
					echo htmlentities($action['param_action'][1]);
				break;
			}

			if($action['param_action'][2]) {
				echo ' as a password';
			}

			echo ' and save in "'.htmlentities($action['param_action'][0]).'".';
		break;


		case 'LancerCI':
			echo 'Start CE';
			echo '<ul>';
			foreach($action['param_action'][0] as $key=>$statement) {
				echo '<li>Statement # '.$statement.' contradicts ';
				echo translateEvidence($action['param_action'][1][$key]);
				echo ' # '.$action['param_action'][2][$key];
				echo ', jump to # '.$action['param_action'][3][$key].'</li>';
			}
			echo '</ul>';
			if($action['param_action'][4]) {
				echo 'Jump to # '.$action['param_action'][4].' on wrong.';
			}
		break;

		case 'LancerVerrous':
			printf(
				'Begin pschychelock at location # %u, %u locks.',
				$action['param_action'][0],
				$action['param_action'][1]
			);
		break;

		case 'MasquerElement':
			echo 'Hide ';
			echo translateEvidence($action['param_action'][0]);
			echo ' # '.$action['param_action'][1].'.';
		break;

		case 'MasquerElements':
			echo 'Hide ';
			foreach($action['param_action'][0] as $index=>$type) {

				if($index!=0) {
					if($index==count($action['param_action'][0])-1) {
						echo ' and ';
					} else {
						echo ', ';
					}
				}

				echo translateEvidence($type);
				echo ' # '.$action['param_action'][1][$index];

			}
			echo '.';

		break;

		case 'MasquerMessage':
			echo 'Hide message # '.$action['param_action'][0].'.';
		break;

		case 'MasquerLieu':
			echo 'Hide location # '.$action['param_action'][0].'.';
		break;

		case 'MasquerIntroLieu':
			echo 'Disable intro for location # '.$action['param_action'][0].'.';
		break;

		case 'MasquerConversation':
			$args=$action['param_action'][1];
			printf(
				'Hide conversation # %u(%u) at location # %u.',
				$args[0],
				$args[1],
				$action['param_action'][0]
			);
		break;

		case 'MasquerVerrousLieu':
			printf(
				'Hide psychelock at location # %u (%u).',
				$action['param_action'][0][0],
				$action['param_action'][0][1]
			);
		break;

		case 'TesterVar':
			echo 'Test var "'.htmlentities($action['param_action'][0]).'".<br />';
			foreach($action['param_action'][1] as $key=>$test) {
				echo 'Go to # '.$action['param_action'][2][$key].' if '.$test.'.<br />';
			}
			echo 'Else, goto # '.$action['param_action'][3].'.';
		break;


		case 'pauseCI':
			echo 'Hide CE navigation.';
		break;


		case 'PerteVie':
			echo 'Penality, '.$action['param_action'][0].' hp.';
		break;

		case 'PointerImage':
			echo 'Point at <abbr title="'.htmlentities($action['param_action'][0]).'">image</abbr>.';
			echo '<ul>';
			foreach($action['param_action'][1] as $key=>$topleft) {
				echo '<li>';
				echo '('.$topleft.','.$action['param_action'][2][$key].') to ';
				echo '('.$action['param_action'][3][$key].','.$action['param_action'][4][$key].')';
				echo ', jump to message # '.$action['param_action'][6][$key].'.';
				echo '</li>';
			}
			echo '</ul>';
			echo 'Else jump to message # '.$action['param_action'][5].'.';
		break;

		case 'ReglerVie':
			echo 'Set hp to '.$action['param_action'][0].'.';
		break;

		case 'ReglerGameOver':
			echo 'Set gameover script to message # '.$action['param_action'][0].'.';
		break;

		case 'RetourCI':
			echo 'Resume CE at message # '.$action['param_action'][0].'.';
			//note, the number can also be the string "objr"
		break;


		case 'RepondreQuestion':
			echo 'Pick one of three options:';
			chooseLoop($action['param_action'],3);
		break;

		case 'SeDeplacer':
			echo 'Show move list';
			echo '<table>';
			echo '<thead><tr><th>Id</th><th>Location name</th></tr></thead>';
			echo '<tbody>';
			foreach($action['param_action'] as $args) {
				assert(count($args)==2);
				echo '<tr>';
				echo '<td>'.$args[0].'</td>';
				echo '<td>'.$args[1].'</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			//oh, and provide a way to return to the action picker
		break;

		default:
			echo '<tt><pre>';
			print_r($action);
			echo '</pre></tt>';
			$actions[$action['nom_action']]=$action['nom_action'];
		break;
	}
	echo '</td>';

	echo "</tr>\n";


}
echo "</tbody></table>\n";

if(count($actions)) {
echo '<h2>Unknown actions</h2>';
echo '<ul><li>';
echo join($actions,'</li><li>');
echo '</li></ul>';
}

if(isset($_GET['raw'])) {

	echo '<h2>Raw dump</h2>';

	foreach($parser->text as $line) {
		if(is_string($line)) {
			echo $l;
			continue;
		}
		echo '<tt><pre>';
		print_r($line);
		echo '</pre></tt>';
	}
}

?>
</body></html>