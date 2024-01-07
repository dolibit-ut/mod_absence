<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2007-2014 ATM Consulting <contact@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *    \file		/admin/admin.php
 *	\ingroup    mymodule othermodule1 othermodule2
 *	\brief      This file is an example of a php page
 *	
 *	\author		Put author name here
 *	\remarks	Put here some comments
 */

// Change this following line to use the correct relative path (../, ../../, etc)
include '../config.php';
dol_include_once('/core/lib/admin.lib.php');
dol_include_once('/core/lib/ajax.lib.php');
dol_include_once('/core/class/html.form.class.php');
// Change this following line to use the correct relative path from htdocs (do not remove DOL_DOCUMENT_ROOT)

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}


$action=__get('action','');

if($action=='save') {
	
	foreach($_REQUEST['TConst'] as $name=>$param) {
		
		dolibarr_set_const($db, $name, $param, 'chaine', 0, '', $conf->entity);
		
		// Traitement supplémentaire sue enregistrement de cette conf à "oui"
		if($name == 'RH_ADD_ACTIONCOMM_ON_ABSENCE_VALIDATE' && $param == 1) {
			$resql = $db->query('SELECT MAX(id) as max_rowid FROM '.MAIN_DB_PREFIX.'c_actioncomm');
			$res = $db->fetch_object($resql);
			$db->query('INSERT INTO '.MAIN_DB_PREFIX.'c_actioncomm(id, code, type, libelle, active) VALUES('.($res->max_rowid + 1).', "AC_ABSENCE", "user", "Absence/Présence", 1)');
		}
		
	}
	
}


/***************************************************
* PAGE
*
* Put here all code to build page
****************************************************/



llxHeader('', $langs->trans('FundManagementAbout'),'');

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans('AbsenceManagement'), $linkback, 'setup');

$form=new TFormCore;
$doliform = new Form($db);

showParameters($form, $doliform);

function showParameters(&$form, &$doliform) {
	global $db,$conf,$langs;
	
	
	$form=new TFormCore;
	$TConst=array(
			'RH_DOL_ADMIN_USER'
			,'RH_USER_MAIL_SENDER'
			,'RH_DATE_RTT_CLOTURE'
			,'RH_DATE_CONGES_CLOTURE'
			,'RH_JOURS_NON_TRAVAILLE'
			,'RH_MONTANT_TICKET_RESTO'
			,'RH_PART_PATRON_TICKET_RESTO'
			,'RH_NDF_TICKET_RESTO'
			,'RH_CODEPRODUIT_TICKET_RESTO'
			,'RH_CODECLIENT_TICKET_RESTO'
			,'TIMESHEET_WORKING_HOUR_PER_DAY'
			,'RH_USER_MAIL_OVERWRITE'
	        ,'RH_NMOIN1_LABEL'
	        ,'RH_N_LABEL'
	    
	);
	
	?><form action="<?php echo $_SERVER['PHP_SELF'] ?>" name="load-<?php echo $typeDoc ?>" method="POST" enctype="multipart/form-data">
		<input type="hidden" name="action" value="save" />
	<table width="100%" class="noborder" style="background-color: #fff;">
		<tr class="liste_titre">
			<td colspan="2"><?php echo $langs->trans('Parameters') ?></td>
		</tr>
		<?php
		
		foreach($TConst as $key) {
			
		?><tr>
			<td><?php echo $langs->trans($key) ?></td><td><?php echo $form->texte('', 'TConst['.$key.']', $conf->global->$key,50,255)  ?></td>				
		</tr><?php
		
		}
		print '<tr>';
		print '<td>';
		print $langs->trans('absenceTicketRestoOnUserCreate');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('ABSENCE_USER_CREATE_TR_ON');
		//print ajax_constantonoff('RH_ADD_ACTIONCOMM_ON_ABSENCE_VALIDATE');
		print '</td>';
		print '</tr>';

		print '<tr>';
		print '<td>';
		print $langs->trans('ABSENCE_TICKETSRESTO_COUNT_ABSENCE_AVALIDER');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('ABSENCE_TICKETSRESTO_COUNT_ABSENCE_AVALIDER');
		print '</td>';
		print '</tr>';
		
		print '<tr>';
		print '<td>';
		print $langs->trans('absenceGreaterThanCongesRestantsForbidden');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('ABSENCE_GREATER_THAN_CONGES_RESTANTS_FORBIDDEN');
		print '</td>';
		print '</tr>';
		
		
		print '<tr>';
		print '<td>';
		print $langs->trans('ABSENCE_ALERT_OTHER_VALIDEUR');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('ABSENCE_ALERT_OTHER_VALIDEUR');
		print '</td>';
		print '</tr>';
		
		print '<tr>';
		print '<td>';
		print $langs->trans('absenceAddInvitationToAcceptNofication');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('ABSENCE_ADD_INVITATION_TO_ACCEPT_MAIL');
		print '</td>';
		print '</tr>';
		
		print '<tr>';
		print '<td>';
		print $langs->trans('absenceAddActionComm');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('RH_ADD_ACTIONCOMM_ON_ABSENCE_VALIDATE');
		//print ajax_constantonoff('RH_ADD_ACTIONCOMM_ON_ABSENCE_VALIDATE');
		print '</td>';
		print '</tr>';
		
		
		print '<tr>';
		print '<td>';
		print $langs->trans('ABSENCE_REPORT_CONGE');
		print '</td>';
		print '<td>';
		print ajax_constantonoff('ABSENCE_REPORT_CONGE');
		//print ajax_constantonoff('RH_ADD_ACTIONCOMM_ON_ABSENCE_VALIDATE');
		print '</td>';
		print '</tr>';
		
		if(!empty($conf->multicompany->enabled) && ! empty($conf->multicompany->transverse_mode)) {
			print '<tr>';
			print '<td>';
			print $langs->trans('RH_COMPTEUR_BY_ENTITY_IN_TRANSVERSE_MODE');
			print '</td>';
			print '<td>';
			print ajax_constantonoff('RH_COMPTEUR_BY_ENTITY_IN_TRANSVERSE_MODE');
			//print ajax_constantonoff('RH_ADD_ACTIONCOMM_ON_ABSENCE_VALIDATE');
			print '</td>';
			print '</tr>';
		}
		
		print '<tr>';
		print '<td>';
		print $langs->trans('absenceExportDecoupeAbsenceMappingUsed')
				.$doliform->textwithtooltip('Kiwi', 'EV,Utilisateur,Code nature,Date début,Heure début,Date fin,Heure fin,Nombre,Zone réservée,Indicateur,Zone réservée,Code motif absence,Zone réservée,Date création,Heure création,Code user,Après-midi,Matin,Date validité', 2, 1, '<img src="'.dol_buildpath('/theme/eldy/img/info.png', 1).'" />')
				.$doliform->textwithtooltip('Banane', '=Export std Cegid', 2, 1, '<img src="'.dol_buildpath('/theme/eldy/img/info.png', 1).'" />');
		print '</td>';
		print '<td>';
		print $doliform->selectarray('TConst[RH_EXPORT_ABSENCE_DECOUPE_USED_MAPPING]', array(''=>'', 'CPRO'=>"Kiwi", 'VALRIM'=>'Banane'), $conf->global->RH_EXPORT_ABSENCE_DECOUPE_USED_MAPPING);
		if($conf->global->RH_EXPORT_ABSENCE_DECOUPE_USED_MAPPING === 'VALRIM') {
			print $form->texte('', 'TConst[RH_EXPORT_ABSENCE_DECOUPE_USED_NUM_DOSSIER]', $conf->global->RH_EXPORT_ABSENCE_DECOUPE_USED_NUM_DOSSIER,6,255);
		}
		print '</td>';
		print '</tr>';
		
		print '<tr>';
		print '<td>';
		print $langs->trans('absenceRecupAcuisitionRules');
		print '</td>';
		print '<td>';
		print $doliform->selectarray('TConst[RH_RECUP_RULES]', array(''=>$langs->trans('None'), 'DECLARE'=>$langs->trans('recupRulesDeclare'), 'AUTO'=>$langs->trans('recupRulesAuto')), $conf->global->RH_RECUP_RULES);
		print '</td>';
		print '</tr>';
	?>	
	</table>
	<p align="right">
		
		<input type="submit" name="bt_save" value="<?php echo $langs->trans('Save') ?>" /> 
		
	</p>
	
	</form>
	
	
	<br /><br />
	<?php
}
?>

<table width="100%" class="noborder">
	<tr class="liste_titre">
		<td><?php echo $langs->trans('AboutAbsence'); ?></td>
		<td align="center">&nbsp;</td>
		</tr>
		<tr class="impair">
			<td valign="top"><?php echo $langs->trans('ModuleBy'); ?></td>
			<td align="center">
				<a href="http://www.atm-consulting.fr/" target="_blank">ATM Consulting</a>
			</td>
		</td>
	</tr>
</table>
<?php

// Put here content of your page
// ...

/***************************************************
* LINKED OBJECT BLOCK
*
* Put here code to view linked object
****************************************************/
//$somethingshown=$asset->showLinkedObjectBlock();

// End of page
$db->close();
llxFooter();
