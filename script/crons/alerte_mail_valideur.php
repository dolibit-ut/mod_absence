#!/usr/bin/php
<?php
/*
 * Script envoyant un mail au validateur chaque jour si besoin pour le notifier des notes de frais à valider
 * 
 */
 	define('INC_FROM_CRON_SCRIPT', true);
	
	chdir(__DIR__);
	
	require('../config.php');
	require('../class/absence.class.php');
	require('../lib/absence.lib.php');
	
	$PDOdb=new TPDOdb;
	$langs->load('mails');
	
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."rh_absence WHERE etat like 'Avalider'
	";
	$PDOdb->Execute($sql);
	$TAbsences = array();
	while($PDOdb->Get_line()) {
		$TAbsences[]=$PDOdb->Get_field('rowid');
	}
	
	foreach($TAbsences as $id){
		$absence->load($PDOdb, $id);
		mailCongesValideur($PDOdb,$absence);
	}
	
	
	return 1;
	
	
