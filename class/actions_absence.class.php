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
			$TKeys = array_keys($object->linkedObjectsIds['rh_absence']);
			$absence_id = $object->linkedObjectsIds['rh_absence'][$TKeys[0]];

			if(!empty($absence_id)){
			
				print '<tr>';
				print '<td>';
				print 'Absence/Présence liée';
				print '</td>';
				print '<td colspan="3">';
				print '<a href="'.dol_buildpath('/absence/absence.php?action=view&id='.$absence_id, 2).'">Voir l\'absence liée</a>';
				print '</td>';
				print '</tr>';
			
			}
		}
		
		return 1;
	}

}