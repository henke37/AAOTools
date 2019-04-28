<?php

if(isset($_GET['id'])) {

?>
<form action="http://aceattorney.sparklin.org/sauvegarde.php?id_proces=<?php echo (int)$_GET['id']; ?>&langue=en" method="post">
<input type="hidden" name="action_sauve" value="enregistrer" />
<label for="title">Titel: <input name="titre_proces" id="title" /></label>
<label for="public">Pubic trial <input type="checkbox" name="proces_jouable" id="public" value="true" /></label>
<input type="hidden" name="langue_proces" value="en" />
<button type="submit">SAVE RAW</button>
<div title="EOF marker, add at end of file at upload">!-!-!-!-!-!-!-!-INTEGRITE-CONFIRMEE-!-!-!-!-!-!-!-!-!</div>
<textarea name="contenu_fichier_sauvegarde" cols="120" rows="30">
</textarea>
</form>
<?php

} else {

?>
<form action="?" action="get">
<label for="id">Trial id<input name="id" id="id"></label>
<button type="submit">Load form</button>
</form>

<?php

}