#!/usr/bin/php
<?php
/*
 * SCRIPT à exécuter avant "reglesCongesFinCloture.php"
 * Gère UNIQUEMENT l'acquisition mensuelle
 * 
 * $_REQUEST['force_http'] => permet de lancer le script via navigateur
 * $_REQUEST['forceCompteur'] => permet de forcer l'acquisition mensuelle
 * $_REQUEST['force_rollback'] => permet de faire un rollback au lieu d'un commit (attention la table rh_compteur doit être en innoDB)
 */

if (!isset($_REQUEST['force_http']))
{
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

	$PDOdb=new TPDOdb;
	$PDOdb->beginTransaction();
	/////chaque mois, les congés année N sont incrémentés de 2,08
	$jour=date("d");
	if($jour=='01' || isset($_REQUEST['forceCompteur'])){
		$k=0;
		$sqlReqUser="SELECT fk_user, nombreCongesAcquisMensuel FROM ".MAIN_DB_PREFIX."rh_compteur";
		$PDOdb->Execute($sqlReqUser);
		$Tab=array();
		while($PDOdb->Get_line()) {
					$Tab[$PDOdb->Get_field('fk_user')] = $PDOdb->Get_field('nombreCongesAcquisMensuel');
		}

		foreach($Tab as $idUser => $nombreConges )
		{
		    //on incrémente chaque mois les jours de congés
			
			$c=new TRH_Compteur;
			if($c->load_by_fkuser($PDOdb, $idUser)) {
				echo '* Compteur id = '.$c->getId().' nombreCongesAcquisMensuel = '.$c->nombreCongesAcquisMensuel."<br />\n";
				$c->acquisExerciceN+=$c->nombreCongesAcquisMensuel;
				$c->save($PDOdb);
				
			}
			else{
				print $langs->trans('ErrImpossibleLoadCounter') . ' ' . $idUser . '\n';
			}

		}
		
	} else {
		echo 'ce n est pas un jour 1';
	}

if (isset($_REQUEST['force_rollback'])) $PDOdb->rollBack();
else $PDOdb->commit();

$PDOdb->close();
	
