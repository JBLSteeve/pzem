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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}

$port = config::byKey('port', 'pzem');
$core_version = '1.1.1';
if (!file_exists(dirname(__FILE__) . '/info.json')) {
    log::add('pzem','warning','Pas de fichier info.json');
}
$data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
if (!is_array($data)) {
    log::add('pzem','warning','Impossible de décoder le fichier info.json');
}
try {
    $core_version = $data['pluginVersion'];
} catch (\Exception $e) {
    log::add('pzem','warning','Impossible de récupérer la version.');
}
?>

<form class="form-horizontal">
    <fieldset>
        <legend><i class="icon fas fa-bolt"></i> {{Multimètre}}</legend>
        <div class="form-group div_local">
            <label class="col-lg-4 control-label">{{Port du multimètre PZEM}}</label>
            <div class="col-lg-4">
                <select id="select_port" class="configKey form-control" data-l1key="port">
                    <option value="">Aucun</option>
                    <?php
                    foreach (jeedom::getUsbMapping() as $name => $value) {
                        echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
                    }
                    echo '<option value="serie">Modem Série</option>';
                    ?>
                </select>
                <input id="port_serie" class="configKey form-control" data-l1key="modem_serie_addr" style="margin-top:5px;display:none" placeholder="Renseigner le port série (ex : /dev/ttyS0)"/>
                <script>
                $( "#select_port" ).change(function() {
                    $( "#select_port option:selected" ).each(function() {
                        if($( this ).val() == "serie"){
                            $("#port_serie").show();
                        }
                        else{
                            $("#port_serie").hide();
                        }
                    });
                });
                </script>
            </div>
        </div>

    </fieldset>
    <fieldset>
        <legend><i class="icon fas fa-cog"></i> {{Configuration avancée}} <i class="fas fa-plus-circle" data-toggle="collapse" href="#OptionsCollapse" role="button" aria-expanded="false" aria-controls="OptionsCollapse"></i></legend>
        <div class="collapse" id="OptionsCollapse">
            <div class="form-group div_local">
                <label class="col-lg-4 control-label">{{Vitesse du multimètre PZEM}}</label>
                <div class="col-lg-4">
                    <select class="configKey form-control" id="modem_vitesse" data-l1key="modem_vitesse">
                        <option value="">{{Par défaut}}</option>
                        <option style="font-weight: bold;" value="1200">1200</option>
                        <option value="2400">2400</option>
                        <option value="4800">4800</option>
                        <option style="font-weight: bold;" value="9600">9600</option>
                        <option value="19200">19200</option>
                        <option value="38400">38400</option>
                        <option value="56000">56000</option>
                        <option value="115200">115200</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
        	    <label class="col-lg-4 control-label">{{Adresse IP socket interne (modification dangereuse)}}</label>
        	    <div class="col-lg-2">
        	        <input class="configKey form-control" data-l1key="sockethost" placeholder="{{127.0.0.1}}" />
        	    </div>
            </div>
            <div class="form-group">
                <label class="col-lg-4 control-label">{{Port socket interne (modification dangereuse)}}</label>
                <div class="col-lg-2">
                    <input class="configKey form-control" data-l1key="socketport" placeholder="{{55559}}" />
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-4 control-label">{{Cycle (s)}}</label>
                <div class="col-sm-2">
                    <input class="configKey form-control" data-l1key="cycle" placeholder="{{0.3}}"/>
                </div>
            </div>
        </div> 
    </fieldset>
    <fieldset>
    <legend><i class="icon loisir-pacman1"></i> {{Version}}</legend>
        <div class="form-group">
            <label class="col-lg-4 control-label">Core PZEM <sup><i class="fas fa-question-circle tooltips" title="{{C'est la version du programme de connexion au PZEM}}" style="font-size : 1em;color:grey;"></i></sup></label>
            <span style="top:6px;" class="col-lg-4"><?php echo $core_version; ?></span>
        </div>
    </fieldset>
</form>

<script>
        $('#btn_diagnostic').on('click',function(){
            $('#md_modal').dialog({title: "{{Diagnostique de résolution d'incident}}"});
            $('#md_modal').load('index.php?v=d&plugin=pzem&modal=diagnostic').dialog('open');
        });

		$('#btn_detect_type').on('click',function(){
			if($( "#select_port option:selected" ).val() == "serie"){
				$selectPort = $("#port_serie").val();
                $type = "serie";
			}
			else {
				$selectPort = $( "#select_port option:selected" ).val();
                $type = "usb";
			}

            $.ajax({// fonction permettant de faire de l'ajax
                type: "POST", // methode de transmission des données au fichier php
                url: "plugins/pzem/core/ajax/pzem.ajax.php", // url du fichier php
                data: {
                    action: "findModemType",
					port: $selectPort,
                    type: $type,
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) { // si l'appel a bien fonctionné
                    if (data.state != 'ok') {
                        $('#div_alert').showAlert({message: data.result.message, level: 'danger'});
                        return;
                    }
                    if (data.result.state == 'ok') {
                        console.log(data);
                        $('#div_alert').showAlert({message: data.result.message + " N'oubliez pas de sauvegarder.", level: 'success'});
                        $("#modem_vitesse").val(data.result.vitesse);
                        $("#linky").prop('checked', data.result.linky);
                    }
                    else {
                        $('#div_alert').showAlert({message: data.result.message, level: 'warning'});
                    }
				}
            });
        });

        $('.changeLogLive').on('click', function () {
	           $.ajax({// fonction permettant de faire de l'ajax
                type: "POST", // methode de transmission des données au fichier php
                url: "plugins/pzem/core/ajax/pzem.ajax.php", // url du fichier php
                data: {
                    action: "changeLogLive",
    				level : $(this).attr('data-log')
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) { // si l'appel a bien fonctionné
                    if (data.state != 'ok') {
                        $('#div_alert').showAlert({message: data.result, level: 'danger'});
                        return;
                    }
                    $('#div_alert').showAlert({message: '{{Réussie}}', level: 'success'});
                }
            });
		});

</script>
