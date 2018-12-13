<?php

	require('config.php');
	require('./class/absence.class.php');
	require('./class/pointeuse.class.php');
	require('./lib/absence.lib.php');

	$langs->load('absence@absence');

	$ATMdb=new TPDOdb;

	$pointeuse=new TRH_Pointeuse;
	llxHeader('', $langs->trans('Clocking'));
	$action=GETPOST('action','alpha');
	if (empty($action)) {
		$action = 'list';
	}
	if(!empty($action)) {
		switch($action) {
			case 'new':
				$pointeuse->fk_user = $user->id;

				$pointeuse->set_values($_REQUEST);
				_fiche($ATMdb, $pointeuse,'edit');
				break;

			case 'imcomming':
				$pointeuse->loadByDate($ATMdb, date('Y-m-d'), $user->id);

				$pointeuse->set_date('date_jour', date('d/m/Y'));

				$planing = new TRH_EmploiTemps;
				$planing->loadByuser($ATMdb, $user->id);

				$date = $pointeuse->get_date('date_jour','Y-m-d');

				$THeure = $planing->getHeures($date);
				$heureFinMatin = (int)date('Hi', $THeure[1]);

				if(date('Hi')<$heureFinMatin && $pointeuse->date_deb_am==0) {
					$pointeuse->date_deb_am = time();
				}
				else if($pointeuse->date_deb_pm==0) {
					$pointeuse->date_deb_pm = time();
				}

				$pointeuse->fk_user = $user->id;

				$pointeuse->save($ATMdb);

				_liste($ATMdb, $pointeuse);
				break;


			case 'imleaving':
				$pointeuse->loadByDate($ATMdb, date('Y-m-d'), $user->id);

				$planing = new TRH_EmploiTemps;
				$planing->loadByuser($ATMdb, $user->id);

				$date = $pointeuse->get_date('date_jour','Y-m-d');

				$THeure = $planing->getHeures($date);
				$heureFinMatin = (int)date('Hi', $THeure[1]);


				if(date('Hi')<$heureFinMatin) {
					$pointeuse->date_fin_am = time();
				}
				else {
					$pointeuse->date_fin_pm = time();
				}

				$pointeuse->fk_user = $user->id;

				$pointeuse->set_date('date_jour', date('d/m/Y'));
				$pointeuse->save($ATMdb);

				_liste($ATMdb, $pointeuse);
				break;


			case 'save':
				if(!empty($_REQUEST['id'])) $pointeuse->load($ATMdb, $_REQUEST['id']);
				else $pointeuse->loadByDate($ATMdb, date('Y-m-d'), $_REQUEST['fk_user']);

				$pointeuse->set_values($_REQUEST);

				$pointeuse->set_date('date_jour', $_REQUEST['date_jour']);
				//print_r($_REQUEST);
				$pointeuse->date_deb_am = strtotime(date('Y-m-d '.$_REQUEST['date_deb_am'], $pointeuse->date_jour));
				$pointeuse->date_deb_pm = strtotime(date('Y-m-d '.$_REQUEST['date_deb_pm'], $pointeuse->date_jour));
				$pointeuse->date_fin_am = strtotime(date('Y-m-d '.$_REQUEST['date_fin_am'], $pointeuse->date_jour));
				$pointeuse->date_fin_pm = strtotime(date('Y-m-d '.$_REQUEST['date_fin_pm'], $pointeuse->date_jour));

				//$ATMdb->debug=true;
				$pointeuse->save($ATMdb);

				_fiche($ATMdb, $pointeuse,'view');

				break;

			case 'view':
				$pointeuse->load($ATMdb, $_REQUEST['id']);
				_fiche($ATMdb, $pointeuse,'view');
				break;

			case 'edit':
				$pointeuse->load($ATMdb, $_REQUEST['id']);
				_fiche($ATMdb, $pointeuse,'edit');
				break;

			case 'delete':
				$pointeuse->load($ATMdb, $_REQUEST['id']);
				$pointeuse->delete($ATMdb);

				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";
				</script>
				<?php
				break;

			case 'list' :
				_liste($ATMdb, $pointeuse);
				break;
		}
	}


	$ATMdb->close();

	llxFooter();


function _liste(&$ATMdb, &$pointeuse) {
	global $langs, $conf, $db, $user;

	print dol_get_fiche_head(pointeusePrepareHead()  , '', $langs->trans('TimeClock'));

	$r = new TSSRenderControler($pointeuse);

	$sql="SELECT p.rowid as 'Id', p.fk_user, u.login, u.firstname, u.lastname, p.date_deb_am, p.date_fin_am, p.date_deb_pm, p.date_fin_pm
			,time_presence as 'Temps de présence'
			,date_jour

			FROM ".MAIN_DB_PREFIX."rh_pointeuse as p INNER JOIN ".MAIN_DB_PREFIX."user as u ON (u.rowid=p.fk_user) WHERE 1 ";
	if(! $user->admin) $sql.=" AND p.fk_user=".$user->id;

	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
	$form=new TFormCore($_SERVER['PHP_SELF'].'?action=list','formtranslateList','post');
	echo $form->hidden('action', 'list');

	$THide = array('fk_user');

	$TOrder = array('date_jour'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');

	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;

	?><div style="text-align: right">
		<a class="butAction" href="?action=imcomming"><?php echo $langs->trans('ImComing'); ?></a>
		<a class="butAction" href="?action=imleaving"><?php echo $langs->trans('ImLeaving'); ?></a>
		<a class="butAction" href="?id=<?php echo $pointeuse->getId(); ?>&action=new"><?php echo $langs->trans('NewClocking'); ?></a><div style="clear:both"></div>
	</div><?php

	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>$page
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'Id'=>'<a href="?id=@val@&action=view">@val@</a>'
		)

		,'hide'=>$THide
		,'type'=>array('date_deb_am'=>'hour', 'date_fin_am'=>'hour', 'date_deb_pm'=>'hour', 'date_fin_pm'=>'hour', 'date_jour'=>'date')
		,'liste'=>array(
			'titre'=> $langs->trans('ListOfAbsence')
			,'image'=>img_picto('','title.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=> $langs->trans('MessageNothingAbsence')


		)
		,'title'=>array(
			'date_deb_am'=> $langs->trans('MorningArrival')
			, 'date_fin_am'=> $langs->trans('MorningLeaving')
			, 'date_deb_pm'=> $langs->trans('AfternoonArrival')
			, 'date_fin_pm'=> $langs->trans('AfternoonLeaving')
			, 'date_jour'=> $langs->trans('Day')
			, 'login'=> $langs->trans('User')
			,'firstname'=> $langs->trans('FirstName')
			,'lastname'=> $langs->trans('Name')
		)
		,'search'=>array(
			'date_jour'=>array('recherche'=>'calendars'),
			"login"=>true
			,"firstname"=>true
			,"lastname"=>true
		)
		,'eval'=>array(
			'Temps de présence'=>"_get_temps_presence(@val@)"
			,'login'=>'_linkUser(@fk_user@)'
		)
		,'math' => array('Temps de présence'=>'sum')
		,'mathformat' => array('Temps de présence'=>'time')
		,'orderBy'=>$TOrder

	));

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

function _get_user($fk_user) {
		global $db,$TCacheUserPointeur;

		if(empty($TCacheUserPointeur))$TCacheUserPointeur=array();

		if(!isset($TCacheUserPointeur[$fk_user])) {
			$TCacheUserPointeur[$fk_user]=new User($db);
			$TCacheUserPointeur[$fk_user]->fetch($fk_user);
		}

		$u = & $TCacheUserPointeur[$fk_user];

		return $u->getNomUrl(1);


}
function _get_temps_presence($time_presence) {

	return date('H\h i\m', $time_presence + strtotime(date('Y-01-01')));

}
function _fiche(&$ATMdb, &$pointeuse, $mode) {
	global $db,$user,$conf,$langs;

	//echo $_REQUEST['validation'];

	$fk_user = !empty($pointeuse->fk_user) ? $pointeuse->fk_user : $user->id; //TODO admin

	if($pointeuse->getId() == 0) {

		$emploi = new TRH_EmploiTemps;
		$emploi->load_by_fkuser($ATMdb, $pointeuse->fk_user, $pointeuse->get_date('date_jour','Y-m-d'));

		list($pointeuse->date_deb_am,$pointeuse->date_fin_am,$pointeuse->date_deb_pm,$pointeuse->date_fin_pm) = $emploi->getHeures($pointeuse->get_date('date_jour','Y-m-d'));
	}


	$form=new TFormCore($_SERVER['PHP_SELF'],'form1','POST');
	$form->Set_typeaff($mode);
	echo $form->hidden('id', $pointeuse->getId());
	echo $form->hidden('fk_user', $fk_user);
	echo $form->hidden('action', 'save');

	$TBS=new TTemplateTBS();
	print $TBS->render('./tpl/pointeuse.tpl.php'
		,array(  )
		,array(
			'pointeuse'=>array(
				'date_deb_am'=>$form->timepicker('','date_deb_am', date('H:i',$pointeuse->date_deb_am) ,5,7)
				,'date_fin_am'=>$form->timepicker('','date_fin_am', date('H:i',$pointeuse->date_fin_am),5,7)
				,'date_deb_pm'=>$form->timepicker('','date_deb_pm', date('H:i',$pointeuse->date_deb_pm),5,7)
				,'date_fin_pm'=>$form->timepicker('','date_fin_pm', date('H:i',$pointeuse->date_fin_pm),5,7)
				,'date_jour'=>$form->calendrier('', 'date_jour', $pointeuse->date_jour)
				,'motif'=>$form->zonetexte('', 'motif', $pointeuse->motif, 60,5)
				,'time_presence'=>_get_temps_presence($pointeuse->time_presence)
				,'id'=>$pointeuse->getId()
			)
			,'view'=>array(
				'mode'=>$mode
				,'head'=>dol_get_fiche_head(pointeusePrepareHead(), 'fiche', $langs->trans('Clocking'))
			)
			,'translate' => array(
				'MorningHourOfArrival' => $langs->trans('MorningHourOfArrival'),
				'MorningDepartureTime' => $langs->trans('MorningDepartureTime'),
				'AfternoonHourOfArrival' => $langs->trans('AfternoonHourOfArrival'),
				'AfternoonDepartureTime' => $langs->trans('AfternoonDepartureTime'),
				'PresenceTimeNoted' => $langs->trans('PresenceTimeNoted'),
				'Day' => $langs->trans('Day'),
				'Cancel' => $langs->trans('Cancel'),
				'Register' => $langs->trans('Register'),
				'Modify' => $langs->trans('Modify'),
				'Delete' => $langs->trans('Delete'),
				'ConfirmDeleteScore' => $langs->trans('ConfirmDeleteScore'),
				'Note' => $langs->trans('Note')
			)
		)
	);

	echo $form->end_form();
	// End of page

	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}

