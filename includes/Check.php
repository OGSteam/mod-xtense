<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

abstract class Check {

	static function player_status($string) {
		return preg_match('!^[snfdvbiIoph]*$!', $string);//fdvbiIoph en français, snvbiIoph in english
	}
	
	static function player_status_forbidden($string) { //Le status "point d'honneur (ph)" est subjectif
		return preg_match('!^[ph]*$!', $string);
	}
	
	static function coords($string, $exp = 0) {
		global $server_config;
		//if ($string == "unknown") return true; //cas avec une seule planète
		if (!preg_match('!^([0-9]{1,2}):([0-9]{1,3}):([0-9]{1,2})$!Usi', $string, $match)) die("coords : Hack");
		if (($match[1] < 1 || $match[2] < 1 || $match[3] < 1 || $match[1] > $server_config['num_of_galaxies'] || $match[2] > $server_config['num_of_systems'] || ($exp ? ($match[3] != 16) : ($match[3] > 15))) == false) return $string;
	}
	/*
	static function date($d) {
					//date au format anglais 																					//date au format de/fr
		return preg_match('!^[01][0-9]-[0-3][0-9] [0-2][0-9]:[0-6][0-9]:[0-6][0-9]$!', $d) || preg_match('!^[0-3][0-9]-[01][0-9] [0-2][0-9]:[0-6][0-9]:[0-6][0-9]$!', $d);
	}
	
	*/
	static function universe($str) {
		$universe = false;//'http://uni0.ogame.fr';
		//if (preg_match('!([a-z0-9]+.ogame\\.[A-Z.]+)(\\/|$)!Ui', $str, $matches)) $universe = 'http://'.strtolower($matches[1]);
		if (preg_match('!([a-z0-9-]+[A-Z.]+.ogame.gameforge.com)(\\/|$)!Ui', $str, $matches)) $universe = 'http://'.strtolower($matches[1]);
		return $universe;
	}
	
}


?>