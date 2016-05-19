<?php

	require('../config.php');
	
	ini_set('display_errors',1);
	$ATMdb2=new TPDOdb;
	
	$ATMdb=new TPDOdb;
	
	$ATMdb->Execute("SELECT rowid, fk_user FROM llx_rh_compteur WHERE fk_user>0 ");
	
	while($obj = $ATMdb->Get_line()) {
		
		$nb_conges = _get_conges($ATMdb2, $obj->fk_user,"'conges','cppartiel'",'2016-05-13');
		$nb_rtt = _get_conges($ATMdb2, $obj->fk_user,"'rttcumule'",'2016-05-13');
		$nb_rttnon = _get_conges($ATMdb2, $obj->fk_user,"'rttnoncumule'",'2016-05-13');
		
if($nb_conges + $nb_rtt+ $nb_rttnon == 0) continue;

		$sql=" UPDATE ".MAIN_DB_PREFIX."rh_compteur 
				SET congesPrisNM1 = congesPrisNM1 +  $nb_conges, rttCumulePris=rttCumulePris+$nb_rtt, rttNonCumulePris=rttNonCumulePris+$nb_rttnon
				WHERE fk_user=".$obj->fk_user.";
		";
	
		print $sql.'<br />';
		
	}
function _get_conges(&$ATMdb, $fk_user, $type="'conges','cppartiel'", $date= '2014-06-01') {
	$sql="SELECT SUM(duree) as nb FROM llx_rh_absence WHERE fk_user=$fk_user AND type IN ($type) AND date_cre>='$date' AND etat!='Refusee' ";
	$ATMdb->Execute($sql);
	$ATMdb->Get_line();
	return (float)$ATMdb->Get_field('nb');
}
