<?php

        require('../config.php');

        ini_set('display_errors',1);
        $PDOdb=new TPDOdb;
        $PDOdb2=new TPDOdb;

		dol_include_once('/absence/class/absence.class.php');

        $PDOdb->Execute("SELECT rowid, fk_user FROM ".MAIN_DB_PREFIX."rh_compteur WHERE fk_user>0");// AND fk_user=28045 OR fk_user=27886");

		llxHeader();

		echo '<table class="border" width="100%" >
		<tr class="liste_titre">
			<td>Utilisateur</td>
			<td>RTT Cumulé</td>
			<td>Err.</td>
			<td>RTT Non Cumulé</td>
			<td>Err.</td>
			<td>Congés NM1</td>
			<td>Err.</td>
			<td>Congés N</td>
			<td>Err.</td>
		</tr>';

	$fixSQL = '';
        while($obj = $PDOdb->Get_line()) {

				$compteur = new TRH_Compteur;
				$compteur->load($PDOdb2, $obj->rowid);

				$Tab = array_merge($compteur->checkConges($PDOdb2),$compteur->checkRTT($PDOdb2));
				
				if(!empty($Tab['rttcumule']['congesPrisNError']) 
				|| !empty($Tab['rttnoncumule']['congesPrisNError'])
				|| !empty($Tab['conges']['congesPrisNError'])
				|| !empty($Tab['conges']['congesPrisNM1Error'])
				) {
					
					$u=new User($db);
					$u->fetch($compteur->fk_user);
					
					echo '<tr>
					<td>'.$u->getNomUrl(1).'</td>
						<td>'.$Tab['rttcumule']['congesPrisNM1'].'</td>
						<td>'.(empty($Tab['rttcumule']['congesPrisNError']) ? '' : 'Err. : '.$compteur->rttCumulePris).'</td>
						<td>'.$Tab['rttnoncumule']['congesPrisNM1'].'</td>
						<td>'.(empty($Tab['rttnoncumule']['congesPrisNError']) ? '' : 'Err. : '.$compteur->rttNonCumulePris).'</td>
						<td>'.$Tab['conges']['congesPrisNM1'].'</td>
						<td>'.(empty($Tab['conges']['congesPrisNM1Error']) ? '' : 'Err. : '.$compteur->congesPrisNM1).'</td>
						<td>'.$Tab['conges']['congesPrisN'].'</td>
						<td>'.(empty($Tab['conges']['congesPrisNError']) ? '' : 'Err. : '.$compteur->congesPrisN).'</td>
					</tr>';
					
					$fixSQL.=" UPDATE ".MAIN_DB_PREFIX."rh_compteur 
						SET congesPrisNM1 = ".$Tab['conges']['congesPrisNM1'].", congesPrisN = ".$Tab['conges']['congesPrisN'].", rttCumulePris = ".$Tab['rttcumule']['congesPrisNM1']."
						, rttNonCumulePris = ".$Tab['rttnoncumule']['congesPrisNM1']."
						WHERE fk_user=".$u->id.";
					";			
				}
					
					
				
				
				
/*
                if($obj2->nb>0) continue; // pas de changement

                $sql=" UPDATE ".MAIN_DB_PREFIX."rh_compteur
                                SET  congesPrisNM1 = $nb_conges_nm1, congesPrisN = $nb_conges_n,  rttCumulePris = $nb_rtt, rttNonCumulePris = $nb_rttnon
                                WHERE fk_user=".$obj->fk_user.";
                ";

                print $sql.'<br />';
*/
        }

		
		echo '</table>';

if(isset($_REQUEST['show_sql'])) echo $fixSQL;

		llxFooter();
