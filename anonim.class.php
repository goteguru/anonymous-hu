<?php

/*  Requirements:
 *  hu_HU.UTF-8 locale
 *  hunspell 1.3.2+
 *
 *	Figyelem! A class Magyar local-t állít be.
 *
 *	stemmer() 		Megadja potenciális neveket, a tövet és a ragokat.
 *					névtő => array(eredeti alak => array(ragok))
 *
 *
 *	anonimize($txt)	A szövegben konzisztensen lecserélgeti a detektált neveket.		
 *
 *	©2013 Mészáros Gergely
 */



class AnonimException extends Exception {}

class Anonim {
	static private $debug;
	public $stemming = array();
	public $transformation = array();	// array(szótő => alias)

	public function __construct ($txt) {
		setlocale(LC_ALL, 'hu_HU.UTF-8');

		// potenciális nevek leválogatása
		$namelist = $this->getNames($txt);
		$this->debug('Gyanú:',$namelist);

		if (count($namelist)) {
			$this->stemmer($namelist);
			$this->debug('Stemming:',$this->stemming);

			foreach ($this->stemming as $szoto => $alakok) 
				$this->transformation[$szoto] = substr($szoto,0,1)."."; 
		}
	}

	public static function debug($title,$v){ self::$debug .= "$title: ". print_r($v,true); }
	public static function showDebug(){ return self::$debug; }

	/*
	 * extra karaktereket hagyományos variánsra 
	 * cseréli szövegben vagy szövegtömbben
	 * (ß=ss, ä=a, ȩ=e stb.)
	 *
	 */
	protected function normalize($txt) {
		//strtr sajnos nem megy (UTF-8 spec.chars) 
		$mit	= array("'",'"','@','ă','â','ä','ć','č','ç','ď','ĕ','ě','î','ń','ň','ô','ŏ','ŕ','ř','š','ş','ţ','ý','ÿ','ž','Ă','Â','Ä','Ć','Č','Ç','Ď','Ĕ','Ě','Î','Ń','Ň','Ô','Ŏ','Ŕ','Ř','Š','Ş','Ţ','Ý','Ÿ','Ž');
		$mire	= array('', '', '','a','a','a','c','c','c','d','e','e','i','n','n','ö','o','r','r','s','s','t','y','y','z','A','A','A','C','C','C','D','E','E','I','N','N','Ö','O','R','R','S','S','T','Y','Y','Z');
		if (!is_array($txt)) return str_replace($mit, $mire, $txt);
		
		$ta = array();
		foreach ($txt as $t) $ta[] = $this->normalize($t);
		return $ta;
	}


	/* 
	 * Véletlenszerű stróman neveket választ a névlista minden egyes eleméhez. 
	 *
	 */
	public function stromanize($alias_list) {
		if ( count($alias_list) < count($this->transformation) ) throw new AnonimException('Túl kevés álnév!');
		$ix = 0;
		# shuffle($alias_list);
		foreach ($this->transformation as $tix => $dummy) 
			$this->transformation[$tix] = $alias_list[$ix++];
	}


	/* 
	 * A várhatóan cserélendő szavak kikeresése (nagybetűvel kezdődő)
	 */
	protected function getNames($txt) {
		#preg_match_all('/\p{Lu}\w+/u',$txt,$res);
		preg_match_all('/\p{Lu}\p{L}+/u',$txt,$res);
		return $res[0];
	}


	/* 
	 * Hunspell elemző futtatása egy potenciális névlistán
	 * A visszatérő érték a szótövekkel indexszelt ragozott alakok tömbjei.
	 */
	protected function stemmer($checkme) {

		$stemmer_cmd = 'hunspell -d anonim -m';

		$dspec = array(
		   0 => array("pipe", "r"),  
		   1 => array("pipe", "w"), 
		);

		// figyelem! muszáj beállítani a kódolást és a home könyvtárat
		// mert a php user-nek lehet hogy nincs.
		$process = proc_open($stemmer_cmd, $dspec, $pipes, dirname(__FILE__),array(
			'LANG' =>'hu_HU.utf8', 
			'HOME' =>'/tmp',
			));

		if (!is_resource($process)) throw new AnonimException('Hunspell failure');

		// HunSpell futtatás. Eredmény szavankénti válaszok tömbje.

		fwrite($pipes[0], join("\n", $this->normalize($checkme)));
		fclose($pipes[0]);
		$output = explode("\n\n",stream_get_contents($pipes[1]));
		fclose($pipes[1]);
		array_pop($output); // utolsó üres sor kuka
		proc_close($process);
		if (count($output) != count($checkme) ) {
			$this->debug('hunspell:', $output);
			throw new AnonimException('Hunspell válaszok száma nem egyezik az átadott szavak számával!');
		}

		//
		// Név szótövek kikeresése (hunspell) ahol ez lehetséges.
		//

		$bizonytalan_tovek = array();  // nem egyértelmű tövek (Kováé -> Kova +B vagy Ková +B?)

		foreach ($output as $ix=>$stems) {
			$possible_roots = array();
			foreach (explode("\n",$stems) as $stem) {
				if (preg_match('/^([^ ]+)\s+st:([^ ]+)(\s[ +\p{L}]+)?/u', $stem, $ret)) {
					array_shift($ret); // full match
					array_shift($ret); // eredeti
					$szoto = array_shift($ret);
					$suffixes = trim(array_shift($ret));
					if ($suffixes) $ragok = explode(' ',$suffixes);
					else $ragok = array();
					// 
					$this->stemming[$szoto][$checkme[$ix]] = $ragok;
					$possible_roots[] = $szoto;
				} else {
					if (preg_match('/^[^ ]+$/', $stem)) continue; // ismeretlen szó
					throw new AnonimException('Váratlan hunspell válasz');
				}
			}

			if (count($possible_roots) > 1) $bizonytalan_tovek[] = $possible_roots;
		}

		// 
		// Ha több lehetséges szótő volt valahol, akkor a ritkábbat
		// kidobjuk.
		//
		$this->debug('bizonytalan:', $bizonytalan_tovek) ;

		foreach ($bizonytalan_tovek as $variants) {
			$tlist = array();
			foreach ($variants as $t) 
				if (isset($this->stemming[$t]))
					$tlist[$t] = count($this->stemming[$t]);
			asort($tlist);
			array_pop($tlist); // A leggyakoribb megmarad.
			foreach (array_keys($tlist) as $nemkell) 
					unset ($this->stemming[$nemkell]);
		}

	}

	/*
	 * A kapott szövegben lecseréli a nyilvántartott neveket.
	 */

	public function anonimize($szoveg) {
		$regexp_mit = array();
		$mire = array();
		foreach ($this->transformation as $szoto => $csereto){
			foreach ($this->stemming[$szoto] as $eredeti => $ragok) {
				$mire[] = $this->ragoz($csereto, $ragok);
				$regexp_mit[] = "/(|\b)$eredeti(|\b)/u";
			}
		}
		return preg_replace($regexp_mit, $mire, $szoveg);
	}

	public static function ragoz($nev, $ragok) {
		if (count($ragok) == 0) return ($nev);

		$ragid = array_shift($ragok);
		if ($ragid == 'CS' || $ragid == "FF" || $ragid == "NŐ") return self::ragoz($nev,$ragok); 

		if (substr($nev, -1,1) == '.') $nev .= '-';

		switch ($ragid) {
		case '+BIRTOK':	
			return self::ragoz(self::emel($nev)."é", $ragok);
		case '+CSALÁD':	
			return self::ragoz(self::emel($nev)."ék", $ragok);
		case '+NEJ':		
			return self::ragoz($nev."né", $ragok);
		case '+TÁRGYRAG':	
			return self::ragoz(self::emel($nev)."t", $ragok);
		case '+VKHEZ':		
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."hoz", $ragok);
			case 'MAGAS':	return self::ragoz($enev."hez", $ragok);
			case 'Ö':	return self::ragoz($enev."höz", $ragok);
			default: 	return self::ragoz($enev."-hez", $ragok);
			}
			break;
		case '+VKIG': 
			return self::ragoz(self::emel($nev)."ig", $ragok);
		case '+VKNEK':
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."nak", $ragok);
			case 'MAGAS':
			case 'Ö':	return self::ragoz($enev."nek", $ragok);
			default: 	return self::ragoz($enev."-nek", $ragok);
			}
			break;
		case '+VKNÉL':	
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."nál", $ragok);
			case 'MAGAS':
			case 'Ö':	return self::ragoz($enev."nél", $ragok);
			default: 	return self::ragoz($enev."-nél", $ragok);
			}
			break;

		case '+VKTŐL':	
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."tól", $ragok);
			case 'MAGAS':	
			case 'Ö':	return self::ragoz($enev."től", $ragok);
			default: 	return self::ragoz($enev."-tól", $ragok);
			}
			break;
			
		case '+VKBEN':	
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."ban", $ragok);
			case 'MAGAS':	
			case 'Ö':	return self::ragoz($enev."ben", $ragok);
			default: 	return self::ragoz($enev."-ben", $ragok);
			}
			break;

		case '+VKRŐL':	
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."ról", $ragok);
			case 'MAGAS':	
			case 'Ö':	return self::ragoz($enev."ről", $ragok);
			default: 	return self::ragoz($enev."-ről", $ragok);
			}
			break;

		case '+VKBE':	
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."ba", $ragok);
			case 'MAGAS':	
			case 'Ö':	return self::ragoz($enev."be", $ragok);
			default: 	return self::ragoz($enev."-be", $ragok);
			}
			break;

		case '+VKBŐL':	
			$enev = self::emel($nev);
			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."ból", $ragok);
			case 'MAGAS':	
			case 'Ö':	return self::ragoz($enev."ből", $ragok);
			default: 	return self::ragoz($enev."-ból", $ragok);
			}
			break;

		case '+VKVEL': 
			$enev = self::kettoz(self::emel($nev));
			# ha magánhangzóra végződik akkor kell egy 'v'
			if (preg_match('/[aáeéoóuúöőií]$/',$nev)) $enev.='v';

			switch (self::hangrend($nev)){
			case 'MELY':	return self::ragoz($enev."al", $ragok);
			case 'MAGAS':
			case 'Ö':	return self::ragoz($enev."el", $ragok);
			default: 	return self::ragoz($enev."-el", $ragok);
			}
			break;
		case '+VKÉRT':	
			return self::ragoz(self::emel($nev)."ért", $ragok);
		default: 
			return self::ragoz($nev."???", $ragok);
		}
		throw new AnonimException('Ide hogy\' kerültünk?');
	}
	/*
	 *  hangtani Segédfüggvények
	 *
	 *
	 */

	/*
	 * hangvéződés emelése
	 */
	public static function emel($szo) {
		$veg = substr($szo,-1,1); 
		switch ($veg) {
		case 'e': $uj = substr($szo,0,-1).'é'; break;
		case 'a': $uj = substr($szo,0,-1).'á'; break;
		case 'o': $uj = substr($szo,0,-1).'ó'; break;
		case 'ö': $uj = substr($szo,0,-1).'ő'; break;
		default: $uj = $szo;
		}
		return $uj;
	}
	/*
	 * mássalhangzó kettőzése
	 */
	public static function kettoz($szo) {
		$match = array(
			'/([f])\.-$/iu'	=> 'F.-f',
			'/([l])\.-$/iu'	=> 'L.-l',
			'/([m])\.-$/iu'	=> 'M.-m',
			'/([n])\.-$/iu'	=> 'N.-n',
			'/([r])\.-$/iu'	=> 'R.-r',
			'/([s])\.-$/iu'	=> 'S.-s',
			'/\.-$/u'	=> '.-v',
			'/ccs$/u'	=> 'ccs',
			'/cs$/u'	=> 'ccs',
			'/tty$/u'	=> 'tty',
			'/ty$/u'	=> 'tty',
			'/th$/u'	=> 'tht',
			'/ssz$/u'	=> 'ssz',
			'/sz$/u'	=> 'ssz',
			'/zzs$/u'	=> 'zzs',
			'/zs$/u'	=> 'zzs',
			'/gy$/u'	=> 'ggy',
			'/ggy$/u'	=> 'ggy',
			'/([rtzpsdfghjklcvbnm])\1$/u' => '$1$1',
			'/([rtzpsdfghjklcvbnm])$/u' => '$1$1',
		);
		foreach ($match as $search=>$rep) 
			if  (preg_match($search, $szo)) 
				return preg_replace($search, $rep, $szo);
		return $szo;
	}

	/*
	 * primitív hangrend detekció
	 */
	public static function hangrend ($szo) {
		// monogrammok detektálása
		if (preg_match('/[ahkoóúuqáy]\.-?$/iu',$szo) ) return 'MELY';
		if (preg_match('/[öüőű]\.-?$/iu',$szo) ) return 'Ö';
		if (preg_match('/.\.-?$/iu',$szo) ) return 'MAGAS';

		// ha van benn mély hangzó akkor mély
		if (preg_match('/[aouáóú]/iu',$szo) ) return 'MELY';

		if (preg_match('/^[^aouáóú]*$/iu',$szo) ) {
			if (preg_match('/[öő][qwrtzplkjhgfdsyxcvbnm]*$/iu',$szo) ) return 'Ö';
			return 'MAGAS';
		}
		return 'VEGYES';
	}
}

# vim: set ts=4 sw=4 si :
