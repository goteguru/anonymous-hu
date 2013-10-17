<?php
/*
 * CLI Teszt script a ananim osztályhoz
 */

include "../anonim.class.php";
if (!isset($argv[1])) die('filenév!');
$fn = $argv[1];
$text = file_get_contents($fn);

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


try {
	$a = new Anonim($text);
	$a->stromanize($initials);
	echo $a->anonimize($text);

} catch (AnonimException $e) {

	die ($e->getMessage());

}

/*
foreach ($initials as $a) {
	$ragok =array( '+VKHEZ');
	echo $a. "\t". Anonim::ragoz($a,$ragok) ."\n";
}
 */

exit (0);
