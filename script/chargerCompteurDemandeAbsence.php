<?php
require('../config.php');
require('../class/absence.class.php');
require('../lib/absence.lib.php');
global $conf,$user;

if(isset($_REQUEST['user'])) {
		
		$TCompteur = array();
		$PDOdb =new TPDOdb;
		$congePrec =array();
		
		$c=new TRH_Compteur;
        $c->load_by_fkuser($PDOdb, (int)$_REQUEST['user']);
        
        echo json_encode(array(
            'reste'=>round2Virgule($c->acquisExerciceNM1+$c->acquisAncienneteNM1+$c->acquisHorsPeriodeNM1+$c->reportCongesNM1-$c->congesPrisNM1)
            ,'resteN'=>round2Virgule($c->acquisExerciceN+$c->acquisAncienneteN+$c->acquisHorsPeriodeN+$c->reportCongesN-$c->congesPrisN)
            ,'acquisRecuperation'=>round2Virgule($c->acquisRecuperation)
            ,'annuelCumule'=>round2Virgule($c->rttCumuleAcquis+$c->rttCumuleReportNM1-$c->rttCumulePris)
            ,'annuelNonCumule'=>round2Virgule($c->rttNonCumuleAcquis+$c->rttNonCumuleReportNM1-$c->rttNonCumulePris)
        	,'annuelN1Cumule'=>round2Virgule($c->rttCumulePrisN1)
        	,'annuelN1NonCumule'=>round2Virgule($c->rttNonCumulePrisN1)
        	,'link'=> (!empty($user->rights->absence->myactions->modifierCompteur) ? ' - '.$c->getNomUrl(1) : '')
        ));
        
        exit;
		
}
	