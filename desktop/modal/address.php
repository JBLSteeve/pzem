<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
$eqLogics = pzem::byType('pzem');
$pzem_address = init('id');

log::add('pzem', 'debug', 'PZEM addres :' . $pzem_address);
sendVarToJS('pzem_address',$pzem_address);

  echo '<div role="tabpanel" class="tab-pane active" id="configNodeTab">';
    echo '<form class="form-horizontal">';
      echo '<fieldset>';
		echo '<legend>{{Configuration }}</legend>';
		echo '<div class="form-group">';
			echo '<label class="col-sm-4 control-label">Nouvelle Adresse du PZEM</label>';
			echo '<div class="col-sm-3">';
				echo '<input type="number" class="ConfigAttr form-control"/>';
				echo '<a class="btn btn-success pull-right" id="bt_saveNewAddress"><i class="fas fa-check-circle"></i> {{Valider}}</a>';
			echo '</div>';
		echo '</div>';
      echo '</fieldset>';
    echo '</form>';
  echo '</div>';
?>

<script>
$('#bt_saveNewAddress').off('click').on('click',function(){
	var NewAdress = parseInt($('.ConfigAttr').value());
	console.log("address PZEM :" + pzem_address);
	console.log("Nouvelle adresse PZEM :" + NewAdress);
	// En cours la mise à jour de l'adresse via le plugin

    if(Number.isInteger(NewAdress)){
		  $('#md_modal').showAlert({message: "{{Changement de configuration du PZEM en cours}}", level: 'warning'});
			  $.ajax({
				type: "POST",
				url: "plugins/pzem/core/ajax/pzem.ajax.php" ,
				data: {
				  action: "UpdateAddress",
				  address : pzem_address,
				  newaddress: NewAdress,
				},
				dataType: 'json',
				global: false,
				error: function (request, status, error) {
				  handleAjaxError(request, status, error);
				},
				success: function (data) {
				  if (data.state != 'ok') {
					$('#md_modal').showAlert({message: data.result, level: 'danger'});
					return;
				  }
				  $('#md_modal').showAlert({message: '{{Nouvelle adresse envoyé au PZEM :' + NewAdress + ' => Configuration envoyé avec succès}}', level: 'success'});
				  window.location.reload();
				}
			});
      }else{
		  $('#md_modal').showAlert({message: '{{Configuration non réussie}}', level: 'danger'});
      }

});
</script>