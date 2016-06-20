<?php

	require('../config.php');
	
	ini_set('display_errors',1);
	$ATMdb2=new TPDOdb;
	
	$ATMdb=new TPDOdb;
$ATMdb2=new TPDOdb;
	
	$ATMdb->Execute("SELECT rowid, fk_user FROM llx_rh_compteur WHERE fk_user>0 ");
	
	while($obj = $ATMdb->Get_line()) {
		
		$nb_conges_n = _get_conges($ATMdb2, $obj->fk_user,"'conges','cppartiel'",'2017-06-01',null,'congesPrisN','date_fin');
		$nb_conges_nm1 = _get_conges($ATMdb2, $obj->fk_user,"'conges','cppartiel'",'2016-06-01',null,'congesPrisN');
		$nb_rtt = _get_conges($ATMdb2, $obj->fk_user,"'rttcumule'",'2016-01-01');
		$nb_rttnon = _get_conges($ATMdb2, $obj->fk_user,"'rttnoncumule'",'2016-01-01');
		
		if($nb_conges_n + $nb_conges_nm1 + $nb_rtt+ $nb_rttnon == 0) continue;

		$sql2="SELECT count(*) as nb  FROM  ".MAIN_DB_PREFIX."rh_compteur
                                WHERE  congesPrisNM1 = $nb_conges_nm1 AND congesPrisN = $nb_conges_n AND  rttCumulePris = $nb_rtt AND rttNonCumulePris = $nb_rttnon AND fk_user=".$obj->fk_user;

		$ATMdb2->Execute($sql2);
		$obj2 = $ATMdb2->Get_line();

		if($obj2->nb>0) continue; // pas de changement

		$sql=" UPDATE ".MAIN_DB_PREFIX."rh_compteur 
				SET  congesPrisNM1 = $nb_conges_nm1, congesPrisN = $nb_conges_n,  rttCumulePris = $nb_rtt, rttNonCumulePris = $nb_rttnon
				WHERE fk_user=".$obj->fk_user.";
		";
	
		print $sql.'<br />';
		
	}
function _get_conges(&$ATMdb, $fk_user, $type="'conges','cppartiel'", $date= '2015-06-01', $date_max=null, $field = 'nb', $date_field = 'date_debut') {
	$sql = "SELECT SUM(duree) as nb, SUM(congesPrisN) as congesPrisN FROM llx_rh_absence WHERE fk_user=$fk_user AND type IN ($type)";
	$sql.=" AND ".$date_field.">='$date'";
	if(!empty($date_max))
		$sql.=" AND date_debut<='$date_max'";
	$sql.=" AND etat!='Refusee' ";
	$ATMdb->Execute($sql);
	$ATMdb->Get_line();
	return (float)$ATMdb->Get_field($field);
}
