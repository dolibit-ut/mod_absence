#!/usr/bin/php
<?php
/*
 * SCRIPT 3 à exécuter
 * 
 */
	if(!isset($_REQUEST['force_for_test'])) {

	$sapi_type = php_sapi_name();
        $script_file = basename(__FILE__);
        $path=dirname(__FILE__).'/';
        // Test if batch mode
        if (substr($sapi_type, 0, 3) != 'cli') {
            echo "Error: ".$script_file." you must use PHP for CLI mode.\n";
                exit(-1);
        }

	}
	
 	define('INC_FROM_CRON_SCRIPT', true);
	
	chdir(__DIR__);
	
	require('../../config.php');
	require('../../class/absence.class.php');

	$ATMdb=new TPDOdb;
	$ATMdb->db->debug=true;

	$o=new TRH_Compteur;
	$o->init_db_by_vars($ATMdb);
	
	
	//on récupère la date de fin de cloture des RTT
	$k=0;
	$sqlReqCloture="SELECT fk_user, date_rttCloture, rttAcquisAnnuelCumuleInit, rttAcquisAnnuelNonCumuleInit FROM ".MAIN_DB_PREFIX."rh_compteur";
	$ATMdb->Execute($sqlReqCloture);
	$Tab=array();
	while($ATMdb->Get_line()) {
			$Tab[$ATMdb->Get_field('fk_user')]['date_rttCloture'] = $ATMdb->Get_field('date_rttCloture');
			$Tab[$ATMdb->Get_field('fk_user')]['rttAcquisAnnuelCumuleInit'] = $ATMdb->Get_field('rttAcquisAnnuelCumuleInit');
			$Tab[$ATMdb->Get_field('fk_user')]['rttAcquisAnnuelNonCumuleInit'] = $ATMdb->Get_field('rttAcquisAnnuelNonCumuleInit');
			$Tab[$ATMdb->Get_field('fk_user')]['fk_user'] = $ATMdb->Get_field('fk_user');
	}
	
	$mars=date("dm");

	foreach($Tab as $idUser=>$TabRtt )
	{
	    $date=strtotime($TabRtt['date_rttCloture']);
		$date=strtotime('+1day',$date);
		
		$dateMD=date("dm",$date);
		
		echo $idUser." ".dol_print_date($date,'day').' '.$dateMD. " == ".$mars." : ";
		
		if($mars==$dateMD || isset($_REQUEST['force_for_test'])){
			
			echo 'ok';
			
			$c=new TRH_Compteur;
			if($c->load_by_fkuser($ATMdb, $idUser)) {
				
				if($c->reportRtt==1) {
					$c->rttNonCumuleReportNM1=$c->rttNonCumuleTotal;
					$c->rttCumuleReportNM1=$c->rttCumuleTotal;
				}
				
				if($c->reportRtt!=1 && $c->rttTypeAcquisition == 'Mensuel') {
					$c->rttCumulePris = $c->rttCumulePrisN1;
					$c->rttNonCumulePris= $c->rttNonCumulePrisN1;
					
					$c->rttCumuleAcquis = 0;
					$c->rttNonCumuleAcquis= 0;
				}
				
				if($c->rttTypeAcquisition == 'Annuel') {
					$c->rttCumulePris = $c->rttCumulePrisN1;
					$c->rttNonCumulePris= $c->rttNonCumulePrisN1;
					
					$c->rttCumuleAcquis=$c->rttAcquisAnnuelCumuleInit;
					$c->rttNonCumuleAcquis=$c->rttAcquisAnnuelNonCumuleInit;
					
					$c->rttCumuleTotal=$c->rttCumuleAcquis+$c->rttCumuleReportNM1-$c->rttCumulePris;
					$c->rttNonCumuleTotal=$c->rttNonCumuleAcquis+$c->rttNonCumuleReportNM1-$c->rttNonCumulePris;
					
				}
				
				$c->save($ATMdb);
				
			}
			else{
				print $langs->trans('ErrImpossibleLoadCounter') . ' ' . $idUser . '\n';
			}
		}
		else {
			echo 'ko';
		}
		 
		echo '<br />';
	}
		
	
	/////chaque mois, les rtt sont incrémentés de 1 pour ceux qui les accumulent par mois
	$jour=date("d");
	if($jour=="01"){
		$sqlMois="SELECT fk_user, rttAcquisMensuelInit 
		FROM ".MAIN_DB_PREFIX."rh_compteur 
		WHERE rttTypeAcquisition='Mensuel'";
		$ATMdb->Execute($sqlMois);
		$Tab=array();
		while($ATMdb->Get_line()) {
				$Tab[$ATMdb->Get_field('fk_user')]['rttAcquisMensuelInit'] = $ATMdb->Get_field('rttAcquisMensuelInit');
				$Tab[$ATMdb->Get_field('fk_user')]['fk_user'] = $ATMdb->Get_field('fk_user');
		}

		foreach($Tab as $idUser=>$TabMois){
			
			$c=new TRH_Compteur;
			if($c->load_by_fkuser($ATMdb, $idUser)) {
				
				if($c->rttTypeAcquisition == 'Mensuel') {
				
					$c->rttCumuleAcquis+=$c->rttAcquisMensuelInit;
					$c->save($ATMdb);
					
				}
				
			}
			else{
				print $langs->trans('ErrImpossibleLoadCounter') . ' ' . $idUser . '\n';
			}
			
		}
		
	}


	//on incrémente les années
	$annee=date("dm");
	if($annee=="0101"){
		//on transfère les jours N-1 non pris vers jours report
		$sqlAnnee="UPDATE ".MAIN_DB_PREFIX."rh_compteur SET rttannee=rttannee+1";
		$ATMdb->Execute($sqlAnnee);
	}
	
	$ATMdb->close();
