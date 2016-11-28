<?php
	require('config.php');
	dol_include_once('/absence/class/absence.class.php');
	dol_include_once('/absence/lib/absence.lib.php');
	dol_include_once('/valideur/class/valideur.class.php');
	
	$langs->load('absence@absence');
	
	$PDOdb=new TPDOdb;
	$absence=new TRH_Absence;
	$absence->loadTypeAbsencePerTypeUser($PDOdb);
	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'add':
			case 'new':
				$absence->set_values($_REQUEST);
				_fiche($PDOdb, $absence,'edit');	
				break;	

			case 'save':
				//$PDOdb->db->debug=true;
				$absence->load($PDOdb, $_REQUEST['id']);
				$absence->set_values($_REQUEST);
				
				$absence->set_date('date_debut', GETPOST('date_debutday').'/'.GETPOST('date_debutmonth').'/'.GETPOST('date_debutyear') );
				$absence->set_date('date_fin', GETPOST('date_finday').'/'.GETPOST('date_finmonth').'/'.GETPOST('date_finyear') );
				
				$absence->niveauValidation=1;
				$existeDeja=$absence->testExisteDeja($PDOdb, $absence);
				if($existeDeja===false){
					$absence->code=saveCodeTypeAbsence($PDOdb, $absence->type);

					// Test de la cohérence des dates
					if(!$user->rights->absence->myactions->creerAbsenceCollaborateur && !TRH_valideur_groupe::isValideur($PDOdb, $user->id)
					&& !$user->rights->absence->myactions->declarePastAbsence
					&& ($absence->date_debut <= strtotime('midnight') ||$absence->date_fin <= strtotime('midnight') )) {
						
						//Ok le mec n'a pas le droit de créer une absence dans le passé mais est-ce qu'il peut le jour même
						if ($user->rights->absence->myactions->declareToDayAbsence && $absence->date_debut >= strtotime('midnight') && $absence->date_fin >= strtotime('midnight'))
						{
							//RAS il peut créer l'absence le jour même
						}
						else 
						{
							/* 
								Si ce n'est pas un user avec droit, pas le droit de créer des anciennes absences						
							*/
							$mesg = '<div class="error">' . $langs->trans('ErrOnlyUserWithPowerCanCreatePastAbsence') . '</div>';
							_fiche($PDOdb, $absence,'edit');
							break;
						}
						
						
					} 
					
					if($absence->save($PDOdb)) {
						
							if($absence->avertissementInfo) setEventMessage($absence->avertissementInfo, 'warnings');
						
							$absence->load($PDOdb, $_REQUEST['id']);
							
							if(GETPOST('autoValidatedAbsence')>0) {
								$absence->setAcceptee($PDOdb, $user->id);
							}
							else if($absence->fk_user==$user->id){	//on vérifie si l'absence a été créée par l'user avant d'envoyer un mail
							
								mailConges($absence);
								mailCongesValideur($PDOdb,$absence);
							}
							
							$mesg = $langs->trans('RegistedRequest');
							
							_fiche($PDOdb, $absence,'view');
					}
					else{
						$errors='';
						foreach($absence->errors as $err) $errors.=$err.'<br />';
						
						$mesg = $errors;
						setEventMessage($mesg);
						
						_fiche($PDOdb, $absence,'edit');
						
					}
					
					
				}else{
					$popinExisteDeja = '<div class="error">' . $langs->trans('ImpossibleCreation') . ' : ' . $langs->trans('ErrExistingRequestInPeriod', date('d/m/Y', strtotime($existeDeja[0])), date('d/m/Y',  strtotime($existeDeja[1]))) . '</div>';
					_fiche($PDOdb, $absence,'edit');
				}
				break;
				
			case 'view':
				$absence->load($PDOdb, $_REQUEST['id']);
				_fiche($PDOdb, $absence,'view');
				break;

			case 'delete':
				$absence->load($PDOdb, $_REQUEST['id']);
				//$PDOdb->db->debug=true;
				//avant de supprimer, on récredite les heures d'absences qui avaient été décomptées. (que si l'absence n'a pas été refusée, dans quel cas 
				//les heures seraient déjà recréditées)
				$absence->recrediterHeure($PDOdb);
				
				if($absence->fk_user == $user->id) { // Si le collaborateur supprime sa demande d'absence on prévient les valideurs

					$absence->etat = 'deleted';
					mailCongesValideur($PDOdb, $absence);
					
				}
				
				$absence->delete($PDOdb);
				
				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";					
				</script>
				<?php
				break;
				
			case 'accept':
				$absence->load($PDOdb, $_REQUEST['id']);
				$absence->valid($PDOdb);
				
				$absence->load($PDOdb, $_REQUEST['id']);

				
				if ($absence->etat == 'Validee')
				{
					$mesg = $langs->trans('AbsenceRequestAccepted');
					setEventMessage($mesg);
				}
				
				_ficheCommentaire($PDOdb, $absence,'edit');
				break;
				
			case 'niveausuperieur':
				$absence->load($PDOdb, $_REQUEST['id']);
				$sqlEtat="UPDATE `".MAIN_DB_PREFIX."rh_absence` 
					SET niveauValidation=niveauValidation+1 WHERE rowid=".$absence->getId();
				$PDOdb->Execute($sqlEtat);
				$absence->load($PDOdb, $_REQUEST['id']);
				mailConges($absence);
				mailCongesValideur($PDOdb,$absence);
				
				$mesg = $langs->trans('AbsenceRequestSentToSuperior');
				setEventMessage($mesg);
				
				_fiche($PDOdb, $absence,'view');
				break;
				
			case 'refuse':
				$absence->load($PDOdb, $_REQUEST['id']);
				/*$absence->recrediterHeure($PDOdb);
				$absence->load($PDOdb, $_REQUEST['id']);

				$absence->etat='Refusee';
				$absence->commentaireValideur = GETPOST('commentaireValideur');

				$absence->save($PDOdb);
				
				//pre($absence,true);exit;
				
				//$absence->load($PDOdb, $_REQUEST['id']);
				mailConges($absence);*/
				$absence->setRefusee($PDOdb);
				
				$mesg = $langs->trans('DeniedAbsenceRequest');
				setEventMessage($mesg);
				_ficheCommentaire($PDOdb, $absence,'edit');
				break;
				
			case 'saveComment':
				
				$absence->load($PDOdb, $_REQUEST['id']);
				$absence->commentaireValideur=$_REQUEST['commentValid'];
				$absence->save($PDOdb);
				_fiche($PDOdb, $absence,'view');

				break;
			case 'listeValidation' : 
				_valideMultiple($PDOdb);
				_listeValidation($PDOdb, $absence);
				break;
			case 'listeAdmin' : 
			
				_valideMultiple($PDOdb);
			
				_listeAdmin($PDOdb, $absence);
				break;
		}
	}
	elseif(isset($_REQUEST['id'])) {
		
	}
	else {
		//$PDOdb->db->debug=true;
		_liste($PDOdb, $absence);
	}
	
	
	$PDOdb->close();
	
	llxFooter();
	
function _valideMultiple(&$PDOdb) {
	global $user,$langs,$conf,$db;
	if(!empty($user->rights->absence->myactions->creerAbsenceCollaborateur) && GETPOST('bt_accept_all')!='') {
				
		if(empty($_POST['TAbsenceAccept'])) {
			setEventMessage($langs->trans('NoAbsenceChecked'),'warnings');
		}
		else {
			foreach($_POST['TAbsenceAccept'] as $fk_absence) {
				
				$a=new TRH_Absence;
				$a->load($PDOdb, $fk_absence);
				
				$a->setAcceptee($PDOdb, $user->id);
				
				setEventMessage($langs->transnoentities('AbsenceCheckedValidated', $a));
			}	
		}
		
	}
	
}
	
function _liste(&$PDOdb, &$absence) {
	global $langs, $conf, $db, $user;	
	llxHeader('', $langs->trans('ListOfAbsence'));
	print dol_get_fiche_head(absencePrepareHead($absence, '')  , '', $langs->trans('Absence'));

	//getStandartJS();
	
	$r = new TSSRenderControler($absence);

	//LISTE D'ABSENCES DU COLLABORATEUR
	$sql="SELECT a.rowid as 'ID', IF(ta.isPresence = 0, 'absence', 'presence') as isPresence, a.fk_user, a.date_cre as 'DateCre',a.date_debut , a.date_fin, 
			a.libelle,a.duree, a.etat,a.type, 'Compteur', u.login, u.firstname, u.lastname ";
			
	if($conf->multicompany->enabled) $sql.=",e.label as entity";
	
	$sql.=",a.avertissement
			FROM ".MAIN_DB_PREFIX."rh_absence as a
				LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (u.rowid=a.fk_user)
				LEFT JOIN ".MAIN_DB_PREFIX."rh_type_absence as ta ON (ta.typeAbsence = a.type) ";
			
	if($conf->multicompany->enabled) $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."entity as e ON (e.rowid = a.entity) ";
				
	$sql.= "WHERE a.fk_user=".$user->id;
	
	
	$TOrder = array('date_debut'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
				
	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formtranslateList','GET');		
	//print $page;
	//echo $form->hidden('action', 'listeValidation');
	
	//echo $sql;exit;
	
	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'page'=>$page
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'libelle'=>'<a href="@isPresence@.php?id=@ID@&action=view">@val@</a>'
		)
		,'translate'=>array('Statut demande'=>array(
			'Refusée'=>'<b style="color:#A72947">'.$langs->trans('Refused').'</b>',
			'En attente de validation'=>'<b style="color:#5691F9">'.$langs->trans('WaitingValidation').'</b>'
			,'Acceptée'=>'<b style="color:#30B300">'.$langs->trans('Accepted').'</b>')
			,'avertissement'=>array('1'=>'<img src="./img/warning.png" title="'.$langs->trans('DoNotRespectRules').'" />')
			,'etat'=>$absence->TEtat
		)
		,'hide'=>array('isPresence','DateCre', 'fk_user','type', 'ID')
		,'type'=>array('date_debut'=>'date', 'date_fin'=>'date', 'duree'=>'number')
		,'liste'=>array(
			'titre'=>$langs->trans('ListOfAbsence')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=> $langs->trans('MessageNothingAbsence')
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			/*,'picto_search'=>'<img src="../../theme/rh/img/search.png">'*/
			
		)
		,'title'=>array(
			'date_debut' 	 => $langs->trans('StartDate')
			,'date_fin'  	 => $langs->trans('EndDate')
			,'avertissement' => $langs->trans('Rules')
			,'libelle'	 	 => $langs->trans('AbsenceType')
			,'typeAbsence'	 => $langs->trans('AbsenceType')
			,'firstname' 	 => $langs->trans('FirstName')
			,'lastname'	 	 => $langs->trans('Name')
			,'login'	 	 => $langs->trans('Login')
			,'etat'		 	 => $langs->trans('RequestStatus')
			,'duree' 	 	 => $langs->trans('CountedInDaysDuration')
			,'Compteur'		 => $langs->trans('AvailableHolidayBeforeRequest')
			,'entity'		 => $langs->trans('Entity')
		)
		,'search'=>array(
			'date_debut'=>array('recherche'=>'calendar')
			,'date_fin'=>array('recherche'=>'calendar')
			,'typeAbsence'=>$absence->TTypeAbsenceAdmin
			,"firstname"=>true
			,"lastname"=>true
			,"login"=>true
			,'etat'=>$absence->TEtat
		)
		,'eval'=>array(
			'lastname'=>'ucwords(strtolower("@val@"))'
			,'etat'=>'_setColorEtat("@val@")'
			,'Compteur'=>'_historyCompteurInForm(getHistoryCompteurForUser(@fk_user@,@ID@,@duree@,"@type@","@etat@"))'
			
			
		)
		,'orderBy'=>$TOrder
		
	));
	?><div class="tabsAction" >
		<a class="butAction" href="?id=<?php echo $absence->getId(); ?>&action=new"><?php echo $langs->trans('NewRequest'); ?></a>
	</div><div style="clear:both"></div><?php
	$form->end();
	
	
	llxFooter();
}	
function _historyCompteurInForm($duree) {
	
	if($duree>0) return '<div align="right">'.number_format($duree,2,',',' ').'</div>';
	else return ''; 
	
}
function _listeAdmin(&$PDOdb, &$absence) {
	global $langs, $conf, $db, $user;	
	llxHeader('', $langs->trans('ListeAllAbsences'));
	print dol_get_fiche_head(absencePrepareHead($absence, '')  , '', $langs->trans('Absence'));
	//getStandartJS();

	
	$r = new TSSRenderControler($absence);
	
	//droits d'admin : accès à toutes les absences sur la liste

	$sql="SELECT a.rowid as 'ID', IF(ta.isPresence = 0, 'absence', 'presence') as isPresence, a.date_cre as 'DateCre',a.date_debut , a.date_fin, 
		 	a.libelle, ROUND(a.duree ,1) as 'duree', a.fk_user,  a.fk_user, u.login, u.firstname, u.lastname,
		  	a.etat ";
			
	if($conf->multicompany->enabled) $sql.=",e.label as entity";	  	
	
	$sql.= ", a.avertissement,'' as 'action',ta.typeAbsence
			FROM ".MAIN_DB_PREFIX."rh_absence as a
				LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (u.rowid=a.fk_user)
				LEFT JOIN ".MAIN_DB_PREFIX."rh_type_absence as ta ON (ta.typeAbsence = a.type)";
			
	if($conf->multicompany->enabled) $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."entity as e ON (e.rowid = a.entity) ";
				
	$sql.= "WHERE 1 ";
			//LIMIT 1000";
	
	
	$TOrder = array('date_debut'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
				
	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;	
	$form=new TFormCore($_SERVER['PHP_SELF'].'?action=listeAdmin','formtranslateList','post');		
	echo $form->hidden('action', 'listeAdmin');
	
	$THide = array('isPresence','DateCre', 'fk_user', 'ID','typeAbsence');
	if(empty($user->rights->absence->myactions->creerAbsenceCollaborateur)) {
		$THide[] = 'action';
	}
	//echo $sql;exit;
//$PDOdb->debug=true;
	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'page'=>$page
			,'nbLine'=>'30'
			,'global'=>1000
		)
		,'link'=>array(
			'libelle'=>'<a href="@isPresence@.php?id=@ID@&action=view">@val@</a>'
		)
		,'translate'=>array(
			'avertissement'=>array('1'=>'<img src="./img/warning.png" title="' . $langs->trans('DoNotRespectRules') . '"></img>','0'=>'')
			/*,'etat'=>$absence->TEtat*/
		)
		,'hide'=>$THide
		,'type'=>array('date_debut'=>'date', 'date_fin'=>'date')
		,'liste'=>array(
			'titre'=> $langs->trans('ListeAllCollabAbsences')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=> $langs->trans('MessageNothingAbsence')
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
		/*	,'picto_search'=>'<img src="../../theme/rh/img/search.png">'*/
			,'etat'=>$absence->TEtat
			
		)
		,'title'=>array(
			'date_debut'=> $langs->trans('StartDate')
			,'date_fin'=> $langs->trans('EndDate')
			,'avertissement'=> $langs->trans('Rules')
			,'libelle'=> $langs->trans('AbsenceType')
			,'typeAbsence'=>$langs->trans('AbsenceType')
			,'firstname'=> $langs->trans('FirstName')
			,'lastname'=> $langs->trans('Name')
			,'login'=> $langs->trans('Login')
			,'duree'=> $langs->trans('DurationInDays')
			,'etat'=> $langs->trans('RequestStatus')
			,'entity'=> $langs->trans('Entity')
			,'action'=>img_help('','Cocher pour valider en bloc')
		)
		,'search'=>array(
			'date_debut'=>array('recherche'=>'calendar')
			,'date_fin'=>array('recherche'=>'calendar')
			,'typeAbsence'=>$absence->TTypeAbsenceAdmin
			,"firstname"=>true
			,"lastname"=>true
			,"login"=>true
			,'etat'=>$absence->TEtat
		)
		,'eval'=>array(
			'lastname'=>'ucwords(strtolower("@val@"))'
			,'etat'=>'_setColorEtat("@val@")'
			,'login'=>'_linkUser(@fk_user@)'
			,'action'=>'_getCheckbox(@ID@,"@etat@")'
		)
		,'orderBy'=>$TOrder
		
	));
	?><div class="tabsAction" >
		<?php
		if(!empty($user->rights->absence->myactions->creerAbsenceCollaborateur)) {
			
			echo '<div style="float:right">&nbsp;&nbsp;&nbsp;'.$form->btsubmit($langs->trans('AbsenceAcceptAll'), 'bt_accept_all').'</div>';	
			
		}
		?>
		<a class="butAction" href="?id=<?php echo $absence->getId(); ?>&action=new"><?php echo $langs->trans('NewRequest'); ?></a>
	</div>	
	<div style="clear:both"></div><?php
	$form->end();
	
	
	llxFooter();
}	
function _linkUser($fk_user) {
	global $db,$langs;
	
	$u=new User($db);
	$u->fetch($fk_user);
	
	if(method_exists($u, 'getLoginUrl')) return $u->getLoginUrl(1);
	
	else return $u->getNomUrl(1);
	
	
}
function _getCheckbox($fk_absence,$etat) {
	
	$form=new TFormCore;
	return $etat=='Avalider' ? $form->checkbox1('', 'TAbsenceAccept[]', $fk_absence) : '';
	
}

function _setColorEtat($val) {
	global $langs;
	
	$a=new TRH_Absence;
	
	return '<span class="absence '.$val.'">'.$a->TEtat[$val].'</span>';
	
}
	
function _listeValidation(&$PDOdb, &$absence) {
	global $langs, $conf, $db, $user;	
	llxHeader('', $langs->trans('ListOfAbsence'));
	print dol_get_fiche_head(absencePrepareHead($absence, '')  , '', $langs->trans('Absence'));
	//getStandartJS();
 
 	$sql = _getSQLListValidation($user->id);
 
 	if($sql===false) {
		?><div class="error">Vous n'&ecirc;tes pas valideur de cong&eacute;  </div><?php
		
		llxFooter();
		return false;
	}
 
	
		//LISTE DES ABSENCES À VALIDER
		$r = new TSSRenderControler($absence);
		
		$TOrder = array('DateCre'=>'DESC');
		if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
		if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
					
		$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;	
		$form=new TFormCore($_SERVER['PHP_SELF'],'formtranslateList','post');	
		echo $form->hidden('action', 'listeValidation');
		
		$THide = array('date_cre','fk_user','ID', 'DateCre','typeAbsence');
		if(empty($user->rights->absence->myactions->creerAbsenceCollaborateur)) {
			$THide[] = 'action';
		}
		
		//print $page;
		$r->liste($PDOdb, $sql, array(
			'limit'=>array(
				'page'=>$page
				,'nbLine'=>'30'
			)
			,'link'=>array(
				'libelle'=>'<a href="?id=@ID@&action=view&validation=ok">@val@</a>'
			)
			,'translate'=>array(
				'avertissement'=>array('1'=>'<img src="./img/warning.png" title="' . $langs->trans('DoNotRespectRules') . '"></img>','0'=>'')
			)		
			,'hide'=>$THide
			,'type'=>array('date_debut'=>'date','date_fin'=>'date')
			,'liste'=>array(
				'titre'=> $langs->trans('ListeAbsencesWaitingValidation')
				,'image'=>img_picto('','title.png', '', 0)
				,'picto_precedent'=>img_picto('','previous.png', '', 0)
				,'picto_suivant'=>img_picto('','next.png', '', 0)
				,'noheader'=> (int)isset($_REQUEST['socid'])
				,'messageNothing'=> $langs->trans('MessageNothingAbsence')
				,'order_down'=>img_picto('','1downarrow.png', '', 0)
				,'order_up'=>img_picto('','1uparrow.png', '', 0)
				
				/*,'picto_search'=>'<img src="../../theme/rh/img/search.png">'*/
			)
			,'title'=>array(
				'date_debut'=> $langs->trans('StartDate')
				,'date_fin'=> $langs->trans('EndDate')
				,'avertissement'=> $langs->trans('Rules')
				,'firstname'=> $langs->trans('FirstName')
				,'lastname'=> $langs->trans('LastName')
				,'libelle'=>'Type absence'
				,'etat'=> $langs->trans('RequestStatus')
				,'typeAbsence'=>$langs->trans('AbsenceType')
				,'entity'=> $langs->trans('Entity')
				,'action'=>img_help('','Cocher pour valider en bloc')
			)
			,'search'=>array(
				'date_debut'=>array('recherche'=>'calendar')
				,'typeAbsence'=>$absence->TTypeAbsenceAdmin
				,"firstname"=>true
				,"lastname"=>true
			)
			,'eval'=>array(
				'etat'=>'_setColorEtat("@val@")'
				,'action'=>'_getCheckbox(@ID@,"@etat@")'
			)
			
			,'orderBy'=>$TOrder
			
		));
	?><div class="tabsAction" >
		<?php
		if(!empty($user->rights->absence->myactions->creerAbsenceCollaborateur)) {
			
			echo $form->btsubmit($langs->trans('AbsenceAcceptAll'), 'bt_accept_all');	
			
		}
		?>
	</div>	
	<div style="clear:both"></div><?php
	
	llxFooter();
}	

function _fiche(&$PDOdb, &$absence, $mode) {
	global $db,$user,$conf,$langs;
	llxHeader('', $langs->trans('AbsenceRequest'));
	//echo $_REQUEST['validation'];
	
	$form=new TFormCore;
	
	$form_start = $form->begin_form($_SERVER['PHP_SELF'],'form1','POST');
	
	$form->Set_typeaff($mode);
	$form_start.=$form->hidden('id', $absence->getId());
	$form_start.=$form->hidden('action', 'save');
	$form_start.=$form->hidden('userRecapCompteur', isset($_REQUEST['fk_user'])?$_REQUEST['fk_user']:$absence->fk_user);
	$form_start.=$form->hidden('userAbsenceCree', ($absence->fk_user>0 ) ?$absence->fk_user:0);
	
	$anneeCourante=date('Y');
	$anneePrec=$anneeCourante-1;
	//////////////////////récupération des informations des congés courants (N) de l'utilisateur courant :
	// TODO OBJECT !!!!!!!!!!!!!! votre honneur ! 
	$sqlReqUser="SELECT * FROM `".MAIN_DB_PREFIX."rh_compteur` 
				WHERE fk_user=" . ((GETPOST('fk_user')) ? intval(GETPOST('fk_user')) : $user->id);
		
	$PDOdb->Execute($sqlReqUser);
	$congePrec=array();
	$congeCourant=array();
	$rttCourant=array();
		
	while($PDOdb->Get_line()) { // TODO doit être un objet
		$congePrec['id']=$PDOdb->Get_field('rowid');
		$congePrec['acquisEx']=$PDOdb->Get_field('acquisExerciceNM1');
		$congePrec['acquisAnc']=$PDOdb->Get_field('acquisAncienneteNM1');
		$congePrec['acquisHorsPer']=$PDOdb->Get_field('acquisHorsPeriodeNM1');
		$congePrec['reportConges']=$PDOdb->Get_field('reportCongesNM1');
		$congePrec['congesPris']=$PDOdb->Get_field('congesPrisNM1');
		$congePrec['annee']=$PDOdb->Get_field('anneeNM1');
		$congePrec['fk_user']=$PDOdb->Get_field('fk_user');
		

		$congeCourant['id']=$PDOdb->Get_field('rowid');
		$congeCourant['acquisEx']=$PDOdb->Get_field('acquisExerciceN');
		$congeCourant['acquisAnc']=$PDOdb->Get_field('acquisAncienneteN');
		$congeCourant['acquisHorsPer']=$PDOdb->Get_field('acquisHorsPeriodeN');
		$congeCourant['annee']=$PDOdb->Get_field('anneeN');
		$congeCourant['fk_user']=$PDOdb->Get_field('fk_user');
		$congeCourant['recup']=$PDOdb->Get_field('acquisRecuperation');
        $congeCourant['congesPris']=$PDOdb->Get_field('congesPris');
		
		
		$rttCourant['id']=$PDOdb->Get_field('rowid');
		
		/*$rttCourant['cumuleReste']=round2Virgule($PDOdb->Get_field('rttCumuleTotal'));
		$rttCourant['nonCumuleReste']=round2Virgule($PDOdb->Get_field('rttNonCumuleTotal'));
		*/
		$rttCourant['cumuleReste']=round2Virgule($PDOdb->Get_field('cumuleAcquis')+$PDOdb->Get_field('cumuleReport')-$PDOdb->Get_field('cumulePris'));
		
		$rttCourant['nonCumuleReste']=round2Virgule($PDOdb->Get_field('nonCumuleAcquis')+$PDOdb->Get_field('nonCumuleReport')-$PDOdb->Get_field('nonCumulePris'));
		
		$rttCourant['fk_user']=$PDOdb->Get_field('fk_user');



	}
	
    $congePrecTotal=$congePrec['acquisEx']+$congePrec['acquisAnc']+$congePrec['acquisHorsPer']+$congePrec['reportConges'];
    $congePrecReste=$congePrecTotal-$congePrec['congesPris'];
    
    $congeCourantTotal=$congeCourant['acquisEx']+$congeCourant['acquisAnc']+$congeCourant['acquisHorsPer']+$congeCourant['reportConges'];
    $congeCourantReste=$congePrecTotal-$congeCourant['congesPris'];
    
	$userCourant=new User($db);
	if($absence->fk_user!=0){
		$userCourant->fetch($absence->fk_user);
	}
	else{
		$userCourant->fetch($user->id);
	}
	
	
	
	//$estValideur=$absence->estValideur($PDOdb,$user->id);
	if(isset($_REQUEST['validation'])){
		if($_REQUEST['validation']=='ok'){
			$estValideur=1;
		}else $estValideur=0;
	}else $estValideur=0;
	
	if($absence->fk_user==0){
		$regleId=$user->id;
	}else $regleId=$absence->fk_user;
	
	//récupération des règles liées à l'utilisateur 
	//$TRegle=array();
	//$TRegle=$absence->recuperationRegleUser($PDOdb, $regleId);

	$comboAbsence=0;
	//création du tableau des utilisateurs liés au groupe du valideur, pour créer une absence, pointage...
	$TUser = array();
	$sql="SELECT rowid, lastname,  firstname FROM `".MAIN_DB_PREFIX."user` WHERE rowid=".$user->id;
	$PDOdb->Execute($sql);
	if($PDOdb->Get_line()){
		$TUser[$PDOdb->Get_field('rowid')]=ucwords(strtolower($PDOdb->Get_field('lastname')))." ".$PDOdb->Get_field('firstname');
	}
	$typeAbsenceCreable= TRH_TypeAbsence::getTypeAbsence($PDOdb, 'user', 0);

	$droitAdmin=0;

	if($user->rights->absence->myactions->creerAbsenceCollaborateur){
		$sql="SELECT DISTINCT rowid, lastname,  firstname, login FROM `".MAIN_DB_PREFIX."user` WHERE statut=1 AND entity IN (0,".$conf->entity.")";
		$droitsCreation=1;
		$comboAbsence=2;
		$typeAbsenceCreable=TRH_TypeAbsence::getTypeAbsence($PDOdb, 'admin', 0);
		$droitAdmin=1;
//print "admin";
//print_r( $typeAbsenceCreable);
	}else if($user->rights->absence->myactions->creerAbsenceCollaborateurGroupe){
		$sql=" SELECT DISTINCT u.fk_user,s.rowid, s.lastname,  s.firstname ,s.login
			FROM `".MAIN_DB_PREFIX."rh_valideur_groupe` as v INNER JOIN ".MAIN_DB_PREFIX."usergroup_user as u ON (v.fk_usergroup=u.fk_usergroup)
				INNER JOIN ".MAIN_DB_PREFIX."user as s ON (s.rowid=u.fk_user)  
			WHERE v.fk_user=".$user->id."
			AND s.statut=1 
			AND v.type='Conges'";
			$comboAbsence=1;
			//echo $sqlReqUser;exit;
		$droitsCreation=1;
		$typeAbsenceCreable=TRH_TypeAbsence::getTypeAbsence($PDOdb, 'user', 0);
	}
	else if($user->rights->absence->myactions->CanValidPersonalAbsencePresence){
                $sql="SELECT rowid, lastname,  firstname,login 
                FROM `".MAIN_DB_PREFIX."user` 
                WHERE rowid=".$user->id;
                $droitsCreation=1;
                $comboAbsence=2;
                $typeAbsenceCreable=TRH_TypeAbsence::getTypeAbsence($PDOdb, 'admin', 0);
                $droitAdmin=1;
        }
	else $droitsCreation=2; //on n'a pas les droits de création
	
	if($droitsCreation==1){
		$sql.=" ORDER BY lastname";
		$PDOdb->Execute($sql);
		while($obju = $PDOdb->Get_line()) {
//var_dump($obju);					
			$name = $obju->lastname.' '.$obju->firstname;
//			var_dump($name);
			$TUser[$obju->rowid]=empty($name) ? $obju->login : $name;
		}
//var_dump($TUser);

	}
	//Tableau affichant les 10 dernières absences du collaborateur
	$TRecap=array();
	$TRecap=$absence->recuperationDerAbsUser($PDOdb, $regleId);
	
	//on regarde si l'utilisateur a le droit de créer une absence non justifiée (POINTEUR)
	
	$sql="SELECT count(*) as 'nb' FROM `".MAIN_DB_PREFIX."rh_valideur_groupe` WHERE fk_user=".$user->id." AND type='Conges' AND pointeur=1";
	$PDOdb->Execute($sql);
	$PDOdb->Get_line();
	
	$pointeurTest=(int)$PDOdb->Get_field('nb');
	
	if(_debug()) {
		print $sql;
	}

	if($pointeurTest>0 && $droitAdmin==0){
		if(_debug()) print "Utilisateur Pointeur";

		$typeAbsenceCreable=$absence->TTypeAbsencePointeur;
		
		// Ne raffraichis la liste que si pas de droit de pas sinon TUser déjà remplis et plus large
		if(!$user->rights->absence->myactions->creerAbsenceCollaborateur && !$user->rights->absence->myactions->creerAbsenceCollaborateurGroupe) {
			$sql=" SELECT DISTINCT u.fk_user,s.rowid, s.lastname,  s.firstname 
			FROM `".MAIN_DB_PREFIX."rh_valideur_groupe` as v INNER JOIN ".MAIN_DB_PREFIX."usergroup_user as u ON (v.fk_usergroup=u.fk_usergroup)
				INNER JOIN ".MAIN_DB_PREFIX."user as s ON (s.rowid=u.fk_user)  
			WHERE v.fk_user=".$user->id." 
			AND v.type='Conges'
			AND v.pointeur=1
			AND statut=1
			ORDER BY s.lastname
			";
			$PDOdb->Execute($sql);
			while($PDOdb->Get_line()) {
				$TUser[$PDOdb->Get_field('rowid')]=ucwords(strtolower($PDOdb->Get_field('lastname')))." ".$PDOdb->Get_field('firstname');
			}
		if(_debug()) var_dump($TUser);

		}
		
		$droitsCreation=1;
	}
	
	
	
	//on peut supprimer la demande d'absence lorsque temps que la date du jour n'est pas supérieure à datedébut-1
	
	$diff=strtotime('+0day',$absence->date_debut)-time();
	$duree=intval($diff/3600/24);

	if( (int)date('Ymd',$absence->date_debut)> (int) date('Ymd') && $absence->fk_user==$user->id && ($absence->etat!='Validee' || $user->rights->absence->myactions->supprimerMonAbsence)){
		$droitSupprimer=1;
	}
	elseif($user->rights->absence->myactions->creerAbsenceCollaborateur){
		$droitSupprimer=1;
	}

	//var_dump($droitSupprimer, $absence->duree,date('Y-m-d', $absence->date_debut), $absence->fk_user, $absence->etat);

	$userValidation=new User($db);
	$userValidation->fetch($absence->fk_user_valideur);
	//print_r($userValidation);
	
	if(isset($_REQUEST['calcul'])) {
		$absence->duree = $absence->calculDureeAbsenceParAddition($PDOdb);
	}
	
	$formDoli = new Form($db);
	
	$TBS=new TTemplateTBS();
	
	if(GETPOST('popin') == 1) {
		$TUser=array($absence->fk_user=>$userCourant->firstname.' '.$userCourant->lastname);
		//$droitsCreation=2; plus beau mais bug car user courant systématique
		$typeAbsenceCreable=array_merge($typeAbsenceCreable, TRH_TypeAbsence::getTypeAbsence($PDOdb, 'admin', 1));
	}

	$TTypeAbsence = TRH_TypeAbsence::getTypeAbsence($PDOdb, 'admin');

    $TUnsecableId = TRH_TypeAbsence::getUnsecable($PDOdb);
    
    $valideurs = '';
    if(($absence->etat=='Avalider' || isset($_REQUEST['DEBUG'])) && empty($conf->global->RH_HIDE_VALIDEUR_ON_CARD)) {
        $TValideur = TRH_valideur_groupe::getUserValideur($PDOdb, $user, $absence, 'Conges', true, true,false);
        $valideurs = implode(", ", $TValideur);
        if(!empty($valideurs)) $valideurs = ' (à valider par '.$valideurs.')';
        
    }
	
	$userAbsenceVisu = '';
//	var_dump($droitsCreation);
	if($droitsCreation==1) {
		if($form->type_aff == 'edit') $userAbsenceVisu = $form->combo('','fk_user',$TUser,$absence->fk_user);
		else $userAbsenceVisu = $userCourant->getNomUrl(1).$form->hidden('fk_user', $absence->getId()> 0 ? $absence->fk_user : $user->id);
		
	}
	else {
		$userAbsenceVisu = $userCourant->getNomUrl(1).$form->hidden('fk_user', $absence->getId()> 0 ? $absence->fk_user : $user->id);
	}
	
//    var_dump($droitSupprimer);
    print $TBS->render('./tpl/absence.tpl.php'
		,array(
			//'TRegle' =>$TRegle
			'TRecap'=>$TRecap
			,'TUserAccepted'=>_getUserAlreadyAccepted($PDOdb, $db, $absence)
		)
		,array(
			'congesPrec'=>array(
				//texte($pLib,$pName,$pVal,$pTaille,$pTailleMax=0,$plus='',$class="text", $default='')
				'acquisEx'=>$form->texte('','acquisExerciceNM1',$congePrec['acquisEx'],10,50,'')
				,'acquisAnc'=>$form->texte('','acquisAncienneteNM1',$congePre['acquisAnc'],10,50)
				,'acquisHorsPer'=>$form->texte('','acquisHorsPeriodeNM1',$congePrec['acquisHorsPer'],10,50)
				,'reportConges'=>$form->texte('','reportcongesNM1',$congePrec['reportConges'],10,50)
				,'congesPris'=>$form->texte('','congesprisNM1',$congePrec['congesPris'],10,50)
				,'anneePrec'=>$form->texte('','anneeNM1',$anneePrec,10,50)
				,'total'=>$form->texte('','total',$congePrecTotal,10,50)
				,'reste' => round2Virgule($congePrecReste)
				,'idUser'=>$_REQUEST['id']
			)
			,'congesCourant'=>array(
				//texte($pLib,$pName,$pVal,$pTaille,$pTailleMax=0,$plus='',$class="text", $default='')
				'acquisEx'=>$form->texte('','acquisExerciceN',$congeCourant['acquisEx'],10,50)
				,'acquisAnc'=>$form->texte('','acquisAncienneteN',$congeCourant['acquisAnc'],10,50)
				,'acquisHorsPer'=>$form->texte('','acquisHorsPeriodeN',$congeCourant['acquisHorsPer'],10,50)
				,'anneeCourante'=>$form->texte('','anneeN',$anneeCourante,10,50)
				,'recup'=>$congeCourant['recup']
				,'idUser'=>$_REQUEST['id']
				
                
			)
			,'rttCourant'=>array(
				//texte($pLib,$pName,$pVal,$pTaille,$pTailleMax=0,$plus='',$class="text", $default='')
				'acquis'=>$form->texte('','rttAcquis',$rttCourant['acquis'],10,50)
				,'rowid'=>$form->texte('','rowid',$rttCourant['id'],10,50,'')
				//,'id'=>$form->texte('','fk_user',$_REQUEST['id'],10,50,'',$class="text", $default='')
				,'cumuleReste'=>round2Virgule($rttCourant['cumuleReste'])
				,'nonCumuleReste'=>round2Virgule($rttCourant['nonCumuleReste'])
				,'idNum'=>$idRttCourant
			)
			,'listUserAlreadyAccepted'=>array(
				'titre'=>load_fiche_titre($langs->trans('ListUserAlreadyAccepted'),'', 'title.png', 0, '')
			)
			,'absenceCourante'=>array(
				//texte($pLib,$pName,$pVal,$pTaille,$pTailleMax=0,$plus='',$class="text", $default='')
				'id'=>$absence->getId()
				,'commentaire'=>$form->zonetexte('','commentaire',$absence->commentaire, 30,3,'','','-')
				,'date_debut'=> $form->doliCalendar('date_debut', $absence->date_debut) 
				,'ddMoment'=>$form->combo('','ddMoment',$absence->TddMoment,$absence->ddMoment)
				,'date_fin'=> $form->doliCalendar('date_fin', $absence->date_fin)
				,'dfMoment'=>$form->combo('','dfMoment',$absence->TdfMoment,$absence->dfMoment)
				,'idUser'=>$user->id
				,'comboType'=>$form->combo('','type',$typeAbsenceCreable,$absence->type)
				,'etat'=>$absence->etat
				,'libelleEtat'=>$absence->libelleEtat.$valideurs
				,'duree'=>$form->texte('','duree',round2Virgule($absence->duree),5,10)	
				,'dureeHeure'=>$form->texte('','dureeHeure',round2Virgule($absence->dureeHeure),5,10)
				,'dureeHeurePaie'=>$form->texte('','dureeHeurePaie',round2Virgule($absence->dureeHeurePaie),5,10)
				,'avertissement'=>$absence->avertissement==1?'<img src="./img/warning.png" />' . $langs->trans('DoNotRespectRules') . ' : '.$absence->avertissementInfo: $langs->trans('None')
				,'fk_user'=>$absence->fk_user
				,'userAbsence'=>$userAbsenceVisu
				
				,'fk_user_absence'=>$form->hidden('fk_user_absence', $absence->fk_user)
				,'niveauValidation'=>$absence->niveauValidation
				,'commentaireValideur'=>$absence->commentaireValideur
				,'dt_cre'=>$absence->get_dtcre()
				,'time_validation'=>$absence->date_validation
				,'date_validation'=>$absence->get_date('date_validation')
				,'userValidation'=>$userValidation->firstname.' '.$userValidation->lastname
				
				,'titreNvDemande'=>load_fiche_titre($langs->trans('NewAbsenceRequest'),'', 'title.png', 0, '')
				,'titreRecapAbsence'=>load_fiche_titre($langs->trans('AbsenceRequestSummary'),'', 'title.png', 0, '')
				,'titreJourRestant'=>load_fiche_titre($langs->trans('RemainingDays').'<span id="link-to-counter"></span>','', 'title.png', 0, '')
				,'titreDerAbsence'=>load_fiche_titre($langs->trans('LastAbsencePresence'),'', 'title.png', 0, '')
				,'titreRegle'=>load_fiche_titre($langs->trans('RelevantRules'),'', 'title.png', 0, '')
				
				,'droitSupprimer'=>$droitSupprimer
				,'lib_date_debut' => $langs->trans('StartDate')
				,'lib_date_fin' => $langs->trans('EndDate')
				,'lib_type_absence' => $langs->trans('AbsenceType')
				,'lib_duree_decompte' => $langs->trans('CountedDuration')
				,'lib_conges_dispo_avant' => $langs->trans('AvailableHolidayBefore')
				,'lib_etat' => $langs->trans('State')
				
				,'unsecableIds'=>'"'.implode('","',$TUnsecableId).'"'
			)	
			,'userCourant'=>array(
				'id'=>$userCourant->id
				,'lastname'=>$userCourant->lastname
				,'firstname'=>$userCourant->firstname
				,'link'=>$userCourant->getNomUrl(1)
				,'valideurConges'=>($user->rights->absence->myactions->creerAbsenceCollaborateur==1 && ($absence->fk_user!=$user->id || $user->rights->absence->myactions->CanValidPersonalAbsencePresence==1))?1:$user->rights->absence->myactions->valideurConges&&$estValideur
				//,'valideurConges'=>$user->rights->absence->myactions->valideurConges
				,'droitCreationAbsenceCollaborateur'=>$droitsCreation==1?'1':'0'
				//,'enregistrerPaieAbsences'=>$user->rights->absence->myactions->enregistrerPaieAbsences&&$estValideur
			)
			,'view'=>array(
				'mode'=>$mode
				,'head'=>dol_get_fiche_head(absencePrepareHead($absence, 'absence')  , 'fiche', $langs->trans('Absence'))
				,'head2'=>dol_get_fiche_head(absencePrepareHead($absence, 'absenceCreation')  , 'fiche', $langs->trans('Absence'))
				,'dateFormat'=>$langs->trans("FormatDateShortJavaInput")
				,'form_start'=>$form_start
				,'form_end'=>$form->end_form()
			)
			,'translate' => array(
				'User' => $langs->trans('User'),
				'CurrentUser' => $langs->trans('CurrentUser'),
				'AbsenceType' => $langs->trans('AbsenceType'),
				'StartDate' => $langs->trans('StartDate'),
				'EndDate' => $langs->trans('EndDate'),
				'DurationInDays' => $langs->trans('DurationInDays'),
				'DurationInHours' => $langs->trans('DurationInHours'),
				'CountedDurationInHours' => $langs->trans('CountedDurationInHours'),
				'State' => $langs->trans('State'),
				'Warning' => $langs->trans('Warning'),
				'ValidationLevel' => $langs->trans('ValidationLevel'),
				'ValidatorComment' => $langs->trans('ValidatorComment'),
				'Comment' => $langs->trans('Comment'),
				'CreatedThe' => $langs->trans('CreatedThe'),
				'ValidatedThe' => $langs->trans('ValidatedThe'),
				'HolidaysPaid' => $langs->trans('HolidaysPaid'),
				'CumulatedDayOff' => $TTypeAbsence['rttcumule'],
				'NonCumulatedDayOff' => $TTypeAbsence['rttnoncumule'],
				'Register' => $langs->trans('Register'),
				'ConfirmAcceptAbsenceRequest' => addslashes( $langs->transnoentitiesnoconv('ConfirmAcceptAbsenceRequest') ),
				'Accept' => $langs->trans('Accept'),
				'Refuse' => $langs->trans('Refuse'),
				'ConfirmRefuseAbsenceRequest' => addslashes($langs->transnoentitiesnoconv('ConfirmRefuseAbsenceRequest')),
				'ConfirmSendToSuperiorAbsenceRequest' => addslashes($langs->transnoentitiesnoconv('ConfirmSendToSuperiorAbsenceRequest')),
				'SendToSuperiorValidator' => $langs->transnoentitiesnoconv('SendToSuperiorValidator'),
				'ConfirmDeleteAbsenceRequest' =>addslashes( $langs->transnoentitiesnoconv('ConfirmDeleteAbsenceRequest')),
				'Delete' => $langs->trans('Delete')
				,'AbsenceBy' => $langs->trans('AbsenceBy')
				,'acquisRecuperation'=>$langs->trans('acquisRecuperation')
				,'dontSendMail'=>$langs->trans('dontSendMail')
				,'langs'=>$langs
			)
			,'other' => array(
				'dontSendMail' => (int)$user->rights->absence->myactions->CanAvoidSendMail
				,'dontSendMail_CB' => '<input type="checkbox" name="dontSendMail" id="dontSendMail" value="1" />' // J'utilise pas $form->checkbox1('','dontSendMail', 1) parce que j'ai besoin que la ce soit toujours cochable meme en mode view pour les valideurs
				,'autoValidatedAbsence' => (int)($form->type_aff == 'edit' &&  $user->rights->absence->myactions->CanDeclareAbsenceAutoValidated)
				,'autoValidatedAbsenceChecked'=> ( !empty($user->rights->absence->myactions->voirToutesAbsencesListe) ? ' checked="checked" ':'')
			)
			
		)
	);

	// End of page
	
	global $mesg, $error, $warning, $popinExisteDeja, $existeDeja;
	
	if($warning)$typeMesg = 'warning';
	elseif($error)$typeMesg = 'error';
	else $typeMesg='ok';
	
	dol_htmloutput_mesg($mesg, '', $typeMesg);
	
	if(!empty($popinExisteDeja) && !empty($existeDeja)) {
		?>
		<script type="text/javascript">
		
		$(document).ready(function() {
		
			$('#user-planning-dialog div.content').before( "<?php echo addslashes($popinExisteDeja); ?>" );
		
			$('#user-planning-dialog div.content').load('planningUser.php?fk_user=<?php echo $existeDeja[2]; ?>&date_debut=<?php echo __get('date_debut'); ?>&date_fin=<?php echo __get('date_fin'); ?> #plannings');
		
			$('#user-planning-dialog').dialog({
				title: "<?php echo $langs->trans('CreationError'); ?>"	
				,width:700
				,modal:true
			});
			
		});
		
		</script>
		
		<?php
	}
	
	
	llxFooter();
}

function _ficheCommentaire(&$PDOdb, &$absence, $mode) {
	global $db,$user,$conf, $langs;
	llxHeader('', $langs->trans('AbsenceRequest'));

	$form=new TFormCore($_SERVER['PHP_SELF'],'form1','POST');
	$form->Set_typeaff($mode);
	echo $form->hidden('id', $absence->getId());
	echo $form->hidden('action', 'saveComment');
	
	print dol_get_fiche_head(absencePrepareHead($absence, 'absenceCreation')  , 'fiche', $langs->trans('Absence'));
	
	?> 
	<br><t style='color: #2AA8B9; font-size: 15px;font-family: arial,tahoma,verdana,helvetica;font-weight: bold;text-decoration: none;text-shadow: 1px 1px 2px #CFCFCF;'>
    <?php echo $langs->trans('AddComment') ?> </t><br/><br/><br/>
	<textarea name="commentValid" rows="3" cols="40"><?php echo $absence->commentaireValideur; ?></textarea><br><br>
	<INPUT class="button" TYPE="submit"   id="commentaire" VALUE="<?php echo $langs->trans('Continue'); ?>">
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;	
	<INPUT class="button" TYPE="button" id="newAsk" VALUE="<?php echo $langs->trans('NewRequestOnSameUser'); ?>" onclick="document.location.href='absence.php?action=new&fk_user=<?php echo $absence->fk_user; ?>'">	
	<br><br>

	<?php
	
	echo $form->end_form();
	// End of page
	
	llxFooter();
}

function _getUserAlreadyAccepted(&$PDOdb, &$db, &$absence)
{
	$TRes = array();

	$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'rh_valideur_object WHERE type="ABS" AND fk_object='.$absence->getId();
	$PDOdb->Execute($sql);
	
	while ($row = $PDOdb->Get_line())
	{
		$sql = 'SELECT lastname, firstname  FROM '.MAIN_DB_PREFIX.'user WHERE rowid = '.$row->fk_user;
		$resql = $db->query($sql);
		if ($resql && $db->num_rows($resql))
		{
			$u = $db->fetch_object($resql);
			$TRes[] = array(
				'date_acceptation' => date('d/m/Y', strtotime($row->date_cre))
				,'username' => trim($u->lastname.' '.$u->firstname)
			);	
		}
	}

	return $TRes;
}
	
	
