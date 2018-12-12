<?php
require('../config.php');
require('../lib/absence.lib.php');
require('../class/absence.class.php');
dol_include_once('valideur/class/absence.class.php');


if(isset($_REQUEST['idUser'])) {
		
		$ATMdb =new TPDOdb;
		global $conf;
		$sql="SELECT DATE_FORMAT(a.date_debut, '".$langs->trans("FormatDateShort")."') as 'dateD',  ta.isPresence, 
		DATE_FORMAT(a.date_fin, '".$langs->trans("FormatDateShort")."')  as 'dateF', a.libelle, a.libelleEtat,a.etat, a.duree ,a.rowid,a.type
		FROM `".MAIN_DB_PREFIX."rh_absence` a 
        LEFT JOIN ".MAIN_DB_PREFIX."rh_type_absence as ta ON (ta.typeAbsence = a.type)
		WHERE fk_user=".$_REQUEST['idUser']." 
		ORDER BY date_debut DESC LIMIT 0,10";

		$ATMdb->Execute($sql);
		$TRecap=array();
		$k=0;
		while($ATMdb->Get_line()) {		
			$TRecap[$k]['date_debut']=$ATMdb->Get_field('dateD');
			$TRecap[$k]['date_fin']=$ATMdb->Get_field('dateF');
			
			$TRecap[$k]['libelle']=TRH_TypeAbsence::_getName($user, $ATMdb->Get_field('isPresence'), $ATMdb->Get_field('libelle'), $_REQUEST['idUser']);
			
			$TRecap[$k]['libelleEtat']=$ATMdb->Get_field('libelleEtat');
			
			$duree  =$ATMdb->Get_field('duree');
			$congesAvant = getHistoryCompteurForUser($_REQUEST['idUser'],$ATMdb->Get_field('rowid'), $duree, $ATMdb->Get_field('type'), $ATMdb->Get_field('etat') );
			
			$TRecap[$k]['duree']=($duree>0) ? round($duree,2) : '';
			
			$TRecap[$k]['congesAvant']=($duree>0 && ($ATMdb->Get_field('type')=='conges' || $ATMdb->Get_field('type')=='cppartiel' )) ? round($congesAvant,2) : '';
			
			$k++;
		}
		
		echo json_encode($TRecap);
		
		exit();
}
	