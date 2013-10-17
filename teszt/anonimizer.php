<?php
/* 
 * Anonimizer bemutató 
 */

include "../anonim.class.php";

$aliases = array(
	'Nagy','Sipos','Fábián','Kovács','Lukács','Szücs','Tóth','Gulyás','Bodnár',
	'Szabó','Biró','Halász','Horváth','Király','Hajdu','Varga','Katona','Kozma',
	'Kiss','László','Máté','Molnár','Jakab','Pásztor','Németh','Fazekas','Jónás',
	'Farkas','Sándor','Gáspár','Balogh','Boros','Székely','Papp','Bogdán','Bakos',
	'Takács','Balog','Major','Juhász','Kelemen','Dudás','Lakatos','Somogyi',
	'Virág','Mészáros','Antal','Hegedüs','Oláh','Orosz','Orbán','Simon',
	'Vincze','Novák','Rácz','Fülöp','Soós','Fekete','Veres','Barna',
	'Szilágyi','Váradi','Nemes','Török','Hegedűs','Tamás','Fehér','Deák',
	'Pataki','Gál','Pap','Faragó','Balázs','Budai','Borbély','Kis',
	'Bálint','Kerekes','Szűcs','Illés','Szekeres','Kocsis','Pál','Balla',
	'Pintér','Vörös','Barta','Fodor','Bognár','Csonka','Orsós','Vass',
	'Dobos','Szalai','Szőke','Péter','Magyar','Lengyel','Végh',
);

$initials = array(
	'A.','B.','C.','D.','E.','F.','G.','H.','I.','J.','K.',
	'L.','M.','N.','O.','Ö.','Ü.','P.','R.','S.','T.','Z.','V.'
);

$csillag = array_fill(0,100,'***');

$szoveg = $_POST['sz'];
$modszer = $_POST['m'];
$random = $_POST['r'];

if ($random) {
	shuffle($aliases);
	shuffle($initials);
}

try {
	$a = new Anonim($szoveg);
	if ($modszer == 'A') $a->stromanize($aliases);
	elseif ($modszer == 'B') $a->stromanize($initials);
	elseif ($modszer == 'H') $a->stromanize($csillag);
	$result = $a->anonimize($szoveg);

} catch (AnonimException $e) {
	$result = 'Hiba:'. $e->getMessage();
}
?>
<div class="eredmeny">
<?=nl2br($result)?>
</div>
<pre class="debug">
DEBUG:
<?=Anonim::showDebug()?>
</pre>
<?php
# vim: set ts=4 sw=4 si :
