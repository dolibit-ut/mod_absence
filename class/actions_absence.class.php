<?php
class ActionsAbsence
{
	 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */ 
      
    function formObjectOptions($parameters, &$object, &$action, $idUser) 
    {
    	global $db;
		
		if($parameters['currentcontext'] == 'actioncard') {
			
			// On cherche s'il existe une absence liée :
			$object->fetchObjectLinked(null,'rh_absence',$object->id,'action');
			
			if(!empty($object->linkedObjectsIds['rh_absence'])) {
				$TKeys = array_keys($object->linkedObjectsIds['rh_absence']);
				$absence_id = $object->linkedObjectsIds['rh_absence'][$TKeys[0]];
				
			}
			
			if(!empty($absence_id)) {

				define('INC_FROM_DOLIBARR', true);
				dol_include_once('/absence/config.php');
				dol_include_once('/absence/class/absence.class.php');
				$PDOdb = new TPDOdb;
				$absence = new TRH_Absence;
				$absence->load($PDOdb, $absence_id);
			
				$absence_type = new TRH_TypeAbsence;
				$absence_type->load_by_type($PDOdb, $absence->type);
			
				if(!empty($absence_id)){
				
					$page = 'absence.php';
					$label = 'Voir l\'absence liée';
					if(!empty($absence_type->isPresence)) {
						$page = 'presence.php';
						$label = 'Voir la présence liée';
					}
				
					print '<tr>';
					print '<td>';
					print 'Absence/Présence liée';
					print '</td>';
					print '<td colspan="3">';
					print '<a href="'.dol_buildpath('/absence/'.$page.'?action=view&id='.$absence_id, 2).'">'.$label.'</a>';
					print '</td>';
					print '</tr>';
			
				}
			}

		}
		
		return 1;
	}

}
