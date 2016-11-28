
[view.head3;strconv=no]
		[view.titreCalendar;strconv=no;protect=no] 	
		
		[onshow;block=begin;when [absence.droits]=='1']
			<table class="liste border" style="width:100%">			
				<tr>
					<td>[absence.groupe;strconv=no;protect=no]</td>
					<td>[absence.TGroupe;strconv=no;protect=no]</td>
				</tr>
				<tr>
					<td>[absence.utilisateur;strconv=no;protect=no]</td>
					<td>[absence.TUser;strconv=no;protect=no]</td>
				</tr>
				<tr>
					<td>[absence.type;strconv=no;protect=no]</td>
					<td>[absence.TTypeAbsence;strconv=no;protect=no]</td>
				</tr>
				<tr>
					<td></td>
				 	<td colspan="2">[absence.btValider;strconv=no;protect=no] </td>
				</tr>
			</table>
		 	<br> 
		[onshow;block=end]
	
		<script>
		$('#groupe').change(function(){
				//alert('top');
				$.ajax({
					url: 'script/loadUtilisateurs.php?groupe='+$('#groupe option:selected').val()
					,dataType:'json'
				}).done(function(liste) {
					$("#idUtilisateur").empty(); // remove old options
					$.each(liste, function(key, value) {
					  $("#idUtilisateur").append($("<option></option>")
					     .attr("value", key).text(value));
					});	
				});
		});
		</script>

		
<div id="fullcalendar"></div>

