<?php

require('../config.php');
dol_include_once("/absence/class/absence.class.php");
dol_include_once("/valideur/class/valideur.class.php");
dol_include_once("/rhlibrary/wdCalendar/php/functions.php");
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';

$PDOdb=new TPDOdb;

$method = GETPOST('action');
switch ($method) {
    case "afficher":
	       	__out(listCalendarByRange($PDOdb, GETPOST('start'), GETPOST('end'), GETPOST('idUtilisateur'), GETPOST('groupe'), GETPOST('typeAbsence')),'json');

        break; 

}

function listCalendarByRange(&$PDOdb, $date_start, $date_end, $idUser=0, $idGroupe=0, $typeAbsence = 'Tous')
{
	global $conf;
	
	$TEvent = getJourFerie($PDOdb, $date_start, $date_end); 
	$TEvent = array_merge($TEvent, getEventsAbs($PDOdb, $date_start, $date_end, $idUser, $idGroupe, $typeAbsence));
	
	if ($conf->agenda->enabled && $_REQUEST['withAgenda'] == 1)
	{
		$TEvent = array_merge($TEvent, getAgendaEvent($PDOdb, $date_start, $date_end));
		//$ret['events'] = $TAgenda;
	}
	
  	return $TEvent;
}

function getEventsAbs(&$PDOdb, $date_start, $date_end, $idUser, $idGroupe, $typeAbsence)
{
	global $conf, $user, $langs, $db;
	
	$TUserTmp = array();
	$TGoupListByUserId = array();
//	$TValidationLevelByGroupId = TRH_valideur_groupe::getTLevelValidation($PDOdb, $user, 'Conges');
	
	$sql = TRH_valideur_groupe::getSqlListObject('Conges', array(
			'ajax' => true
			, 'fk_user' => $idUser
			, 'fk_ursergroup' => $idGroupe
			, 'date_start' => $date_start
			, 'date_end' => $date_end
			, 'typeAbsence' => $typeAbsence
	));

	$TRow = $PDOdb->ExecuteAsArray($sql);

	foreach ($TRow as $row)
	{
//		$idAbs[] = $row->rowid;

		if ($row->etat == 'Refusee' && !$user->rights->absence->myactions->voirAbsenceRefusee)
		{
			continue;
		}

		if (!empty($TUserTmp[$row->fk_user])) $userAbs = $TUserTmp[$row->fk_user];
		else
		{
			$userAbs = new User($db);
			$userAbs->fetch($row->fk_user);
			$TUserTmp[$row->fk_user] = $userAbs;
		}

		if (!empty($TGoupListByUserId[$userAbs->id])) $groupslist = $TGoupListByUserId[$userAbs->id];
		else
		{
			$groupslist = customListGroupsForUser($userAbs->id);
			$TGoupListByUserId[$userAbs->id] = $groupslist;
		}

		if ($row->isPresence == 1)
		{
			$time_debut_jour = strtotime($row->date_debut);
			$time_fin_jour = strtotime($row->date_fin);

			$moreOneDay = (int) ($row->date_debut < $row->date_fin);

			$t_current = $time_debut_jour;
			while ($t_current <= $time_fin_jour)
			{
				$timeDebut = strtotime(date('Y-m-d', $t_current).' '.substr($row->date_hourStart, 11));
				$timeFin = strtotime(date('Y-m-d', $t_current).' '.substr($row->date_hourEnd, 11));

				$url = "presence.php?id=".$row->rowid."&action=view"; //$row->location,
				$attends = 'presence'; //$attends
				// TODO remplacer l'appel à isValideur par un test plus opti avec la variable $TValidationLevelByGroupId
				if ($user->id != $row->fk_user && !TRH_valideur_groupe::isValideur($PDOdb, $user->id, $groupslist))
				{
					$label = $row->lastname.' '.$row->firstname;
				}
				else
				{
					$label = $row->lastname.' '.$row->firstname.' : '.$row->libelle;
				}


				if ($moreOneDay)
				{
					$label .= ' du '.dol_print_date($timeDebut).' au '.dol_print_date($timeFin);
				}

				if (mb_detect_encoding($label, 'UTF-8', true) === false)
					$label = utf8_encode($label);

				if (empty($row->colorId)) $color = '#66ff66';
				else $color = TRH_TypeAbsence::getColor($row->colorId);

				$TEvent[] = array(
					'id' => $row->rowid
					, 'title' => $label
					, 'allDay' => 0
					, 'start' => (empty($timeDebut) ? '' : date('Y-m-d H:i:s', (int) $timeDebut))
					, 'end' => (empty($timeFin) ? '' : date('Y-m-d H:i:s', (int) $timeFin))
					, 'url' => $url
					, 'editable' => 0
					, 'color' => '#66ff66'
					, 'isDarkColor' => 0
					, 'colors' => ''
					, 'note' => ''
					, 'statut' => ''
					, 'fk_soc' => 0
					, 'fk_contact' => 0
					, 'fk_user' => $row->fk_user
					, 'fk_project' => 0
					, 'societe' => ''
					, 'contact' => ''
					, 'user' => $userAbs->getFullName($langs)
					, 'project' => ''
					, 'more' => ''
				);

				$t_current = strtotime('+1day', $t_current);
			}
		}
		else
		{
			switch ($row->etat) {
				case 'Avalider' :
					$color = '#668cd9';
					break;
				case 'Refusee':
					$color = '#ff4444';
					break;
				default:

					if (empty($row->colorId)) $color = '#65ad89';
					else $color = TRH_TypeAbsence::getColor($row->colorId);

					break;
			}

			$timeDebut = strtotime($row->date_debut);
			$timeFin = strtotime($row->date_fin) + 86399; // par défaut 23:59:59

			$allDay = 1;

			if ($row->ddMoment == 'apresmidi')
			{
				$timeDebut += (3600 * 12);
				$allDay = 0;
			}//+12h
			if ($row->dfMoment == 'matin')
			{
				$timeFin -= (3600 * 12);
				$allDay = 0;
			}//-12h



//			$allDayEvent = (int) ($row->ddMoment == 'matin' && $row->dfMoment == 'apresmidi' || $row->date_debut < $row->date_fin);
			$moreOneDay = (int) ($row->date_debut < $row->date_fin);
			$url = "absence.php?id=".$row->rowid."&action=view"; //$row->location,
			$attends = 'absence'; //$attends
			// TODO remplacer l'appel à isValideur par un test plus opti avec la variable $TValidationLevelByGroupId
			if ($user->id != $row->fk_user && !TRH_valideur_groupe::isValideur($PDOdb, $user->id, $groupslist))
			{
				$label = $row->lastname.' '.$row->firstname;
				$url = '#';
			}
			else
			{
				$label = $row->lastname.' '.$row->firstname.' : '.html_entity_decode($row->libelle);
			}

			if (mb_detect_encoding($label, 'UTF-8', true) === false) $label = utf8_encode($label);

//	var_dump($label, $user->id,$row->fk_user,TRH_valideur_groupe::isValideur($PDOdb, $row->fk_user), '<br>');        
//	        $label = utf8_encode($row->lastname.' '.$row->firstname).' : '.$row->libelle;
			if ($moreOneDay)
			{
				$label .= ' du '._justDate($timeDebut, 'd/m').' au '._justDate($timeFin, 'd/m/Y');
			}

			$TEvent[] = array(
				'id' => $row->rowid
				, 'title' => $label
				, 'allDay' => $allDay
				, 'start' => (empty($timeDebut) ? '' : date('Y-m-d H:i:s', (int) $timeDebut))
				, 'end' => (empty($timeFin) ? '' : date('Y-m-d H:i:s', (int) $timeFin))
				, 'url' => $url
				, 'editable' => 0
				, 'color' => $color
				, 'isDarkColor' => 0
				, 'colors' => ''
				, 'note' => ''
				, 'statut' => ''
				, 'fk_soc' => 0
				, 'fk_contact' => 0
				, 'fk_user' => $row->fk_user
				, 'fk_project' => 0
				, 'societe' => ''
				, 'contact' => ''
				, 'user' => $userAbs->getFullName($langs)
				, 'project' => ''
				, 'more' => ''
			);
		}
	}

	return $TEvent;
}

function customListGroupsForUser($fk_user)
{
	global $conf,$user,$db;
	
	$sql = "SELECT g.rowid, ug.entity as usergroup_entity";
	$sql.= " FROM ".MAIN_DB_PREFIX."usergroup as g,";
	$sql.= " ".MAIN_DB_PREFIX."usergroup_user as ug";
	$sql.= " WHERE ug.fk_usergroup = g.rowid";
	$sql.= " AND ug.fk_user = ".$fk_user;
	if(! empty($conf->multicompany->enabled) && $conf->entity == 1 && $user->admin && ! $user->entity)
	{
		$sql.= " AND g.entity IS NOT NULL";
	}
	else
	{
		$sql.= " AND g.entity IN (0,".$conf->entity.")";
	}
	
	$result = $db->query($sql);
	if ($result)
	{
		while ($obj = $db->fetch_object($result))
		{
			$ret[$obj->rowid] = $obj->rowid;
		}

		$db->free($result);

		return $ret;
	}
	else
	{
		$error=$db->lasterror();
		var_dump($error);
		exit;
	}
}

function _justDate($date,$frm = 'm/d/Y H:i') {
	if(is_int($date))$time=$date;
	else $time = strtotime($date);
	
	return date($frm, $time);
}

function getJourFerie(&$PDOdb, $date_start, $date_end) {
	global $conf, $langs;	
	
	$TEvent=array();

	$TJF=TRH_JoursFeries::getAll($PDOdb, $date_start, $date_end);
		  //récupération des jours fériés 
	foreach($TJF as $row) {
		
		  $timeOff = strtotime($row->date_jourOff);	
			
		  $allDay = 1;
		  switch($row->moment){
			case 'apresmidi' : 
				$moment= $langs->transnoentities('ClosedTheAfternoon');
				
				$allDay = 0;
				$start = date('Y-m-d 12:00:00', $timeOff);
				$end = date('Y-m-d 23:59:59', $timeOff);
				
				break;
			case 'matin':
				$moment= $langs->transnoentities('ClosedTheMorning');
				
				$allDay = 0;
				$start = date('Y-m-d 00:00:00', $timeOff);
				$end = date('Y-m-d 12:00:00', $timeOff);
				
				break;
			default:
				$moment= $langs->transnoentities('PublicHoliday');
				
				$allDay = 1;
				$start = date('Y-m-d 00:00:00', $timeOff);
				$end = date('Y-m-d 23:59:59', $timeOff);
				
				break;
    		}
		  
		  	$TEvent[]=array(
				'id'=>100000000+$row->rowid
				,'title'=>$moment
				,'allDay'=>$allDay
				,'start'=>$start
				,'end'=>$end
				,'url'=>'#'
				,'editable'=>0
				,'color'=>''
				,'isDarkColor'=>1
				,'colors'=>'#333333'
				,'note'=>$row->commentaire
				,'statut'=>''
				,'fk_soc'=>0
				,'fk_contact'=>0
				,'fk_user'=>0
				,'fk_project'=>0
				,'societe'=>''
				,'contact'=>''
				,'user'=>''
				,'project'=>''
				,'more'=>''
			);
	      
     }
	//var_dump($TEvent);
	return $TEvent;
	
}

function getAgendaEvent(&$PDOdb, $date_start, $date_end) {
global $user, $conf;
		
	
	$filter=GETPOST("filter",'',3);
	$filtera = GETPOST("userasked","int",3)?GETPOST("userasked","int",3):GETPOST("filtera","int",3);
	$filtert = GETPOST("usertodo","int",3)?GETPOST("usertodo","int",3):GETPOST("filtert","int",3);
	$filterd = GETPOST("userdone","int",3)?GETPOST("userdone","int",3):GETPOST("filterd","int",3);
	$showbirthday = empty($conf->use_javascript_ajax)?GETPOST("showbirthday","int"):1;
	$socid = GETPOST("socid","int",1);
	if ($user->societe_id) $socid=$user->societe_id;
	
	if (empty($user->rights->agenda->myactions->lire) && empty($user->rights->agenda->myactions->read)) return array();

	$canedit=1;
	if (! $user->rights->agenda->myactions->read) return array();
	if (! $user->rights->agenda->allactions->read) $canedit=0;
	if (! $user->rights->agenda->allactions->read || $filter =='mine')  // If no permission to see all, we show only affected to me
	{
	    $filtera=$user->id;
	    $filtert=$user->id;
	    $filterd=$user->id;
	}
	
	$action=GETPOST('action','alpha');
	$pid=GETPOST("projectid","int",3);
	$status=GETPOST("status");
	$type=GETPOST("type");
	$actioncode=GETPOST("actioncode","alpha",3)?GETPOST("actioncode","alpha",3):(GETPOST("actioncode")=="0"?'':(empty($conf->global->AGENDA_USE_EVENT_TYPE)?'AC_OTH':''));
		
		
			
	$sql = 'SELECT a.id,a.label,';
	$sql.= ' a.datep,';
	$sql.= ' a.datep2,';
	$sql.= ' a.datea,';
	$sql.= ' a.datea2,';
	$sql.= ' a.percent,';
	$sql.= ' a.fk_user_author,a.fk_user_action,a.fk_user_done,';
	$sql.= ' a.priority, a.fulldayevent, a.location,';
	$sql.= ' a.fk_soc, a.fk_contact,';
	$sql.= ' ca.code';
	$sql.= ' FROM ('.MAIN_DB_PREFIX.'c_actioncomm as ca,';
	$sql.= " ".MAIN_DB_PREFIX.'user as u,';
	$sql.= " ".MAIN_DB_PREFIX."actioncomm as a)";
	if (! $user->rights->societe->client->voir && ! $socid) $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON a.fk_soc = sc.fk_soc";
	$sql.= ' WHERE a.fk_action = ca.id';
	$sql.= ' AND a.fk_user_author = u.rowid';
	$sql.= ' AND a.entity IN ('.getEntity().')';
	if ($actioncode) $sql.=" AND ca.code=".$PDOdb->quote($actioncode);
	if ($pid) $sql.=" AND a.fk_project=".$PDOdb->quote($pid);
	if (! $user->rights->societe->client->voir && ! $socid) $sql.= " AND (a.fk_soc IS NULL OR sc.fk_user = " .$user->id . ")";
	if ($user->societe_id) $sql.= ' AND a.fk_soc = '.$user->societe_id; // To limit to external user company
	
	
    $sql.= " AND (";
    $sql.= " (datep BETWEEN '".$date_start."'";
    $sql.= " AND '".$date_end."')";
    $sql.= " OR ";
    $sql.= " (datep2 BETWEEN '".$date_start."'";
    $sql.= " AND '".$date_end."')";
    $sql.= " OR ";
    $sql.= " (datep < '".$date_start."'";
    $sql.= " AND datep2 > '".$date_end."')";
    $sql.= ')';

    if ($type) $sql.= " AND ca.id = ".$type;
	if ($status == 'done') { $sql.= " AND (a.percent = 100 OR (a.percent = -1 AND a.datep2 <= '".$db->idate($now)."'))"; }
	if ($status == 'todo') { $sql.= " AND ((a.percent >= 0 AND a.percent < 100) OR (a.percent = -1 AND a.datep2 > '".$db->idate($now)."'))"; }
	if ($filtera > 0 || $filtert > 0 || $filterd > 0)
	{
	    $sql.= " AND (";
	    if ($filtera > 0) $sql.= " a.fk_user_author = ".$filtera;
	    if ($filtert > 0) $sql.= ($filtera>0?" OR ":"")." a.fk_user_action = ".$filtert;
	    if ($filterd > 0) $sql.= ($filtera>0||$filtert>0?" OR ":"")." a.fk_user_done = ".$filterd;
	    $sql.= ")";
	}
	// Sort on date
	$sql.= ' ORDER BY datep';
	
	
	$PDOdb->Execute($sql);
	$Tab = $PDOdb->Get_All();
	
	$TEvent=array();
	
	foreach($Tab as $row) {
		
		 if(empty($row->datep2)) $row->datep2 = date('Y-m-d H:i:s', strtotime($row->datep) + (60 * 60) ) ; // 1h
		
		
		 if($row->code=='AC_OTH_AUTO')$color=5;
		 else $color = -1; 
			
			
		  $TEvent[]=array(
				'id'=>200000000 + $row->rowid
				,'title'=>utf8_encode( $row->label )
				,'allDay'=>$row->fulldayevent
				,'start'=>$row->datep
				,'end'=> $row->datep2
				,'url'=>dol_buildpath('/comm/action/fiche.php?id='.$row->id,1)
				,'editable'=>0
				,'color'=>$color
				,'isDarkColor'=>0
				,'colors'=>''
				,'note'=>''
				,'statut'=>''
				,'fk_soc'=>0
				,'fk_contact'=>0
				,'fk_user'=>$row->fk_user
				,'fk_project'=>0
				,'societe'=>''
				,'contact'=>''
				,'user'=> '' // $userAbs->getFullName($langs) // TODO à corriger, ici la variable n'est même instancié et il faudrait ce baser sur le fk_user de l'event
				,'project'=>''
				,'more'=>''
			);
		
	  	
		
	}
	
	return $TEvent;
}
