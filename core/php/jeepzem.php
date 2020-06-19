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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
set_time_limit(15);




if (!jeedom::apiAccess(init('apikey'), 'pzem')) {
    echo __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (pzem)', __FILE__);
    http_response_code(403);
    die();
}

if (init('test') != '') {
	echo 'OK';
	die();
}

$args = array(
	'device' => FILTER_SANITIZE_STRING,
    'adresse'   => FILTER_SANITIZE_STRING,
    'voltage'   => FILTER_SANITIZE_STRING,
    'current'   => FILTER_SANITIZE_STRING,
    'power'   => FILTER_SANITIZE_STRING,
    'energy'   => FILTER_SANITIZE_STRING,
    'frequency'   => FILTER_SANITIZE_STRING,
    'powerfactor'   => FILTER_SANITIZE_STRING,
);

$result = json_decode(file_get_contents("php://input"), true);

if (!is_array($result)) {
	die();
}


$var_to_log = '';
if (isset($result['device'])) {
    foreach ($result['device'] as $key => $data) {
    		$eqlogic = pzem::byLogicalId($data['adresse'], 'pzem');
    		if (is_object($eqlogic)) {
                $healthCmd = $eqlogic->getCmd('info','health');
                $healthEnable = false;
                if (is_object($healthCmd)) {
                    $healthEnable = true;
                }
                $flattenResults = array_flatten($data);
                foreach ($flattenResults as $key => $value) {
							switch ($key) {
								case "current":
									$key = "Intensité";
									$unit = "A";
									break;
								case "energy":
									$key = "Consommation";
									$unit = "Wh";
									break;
								case "frequency":
									$key = "Frequence";
									$unit = "Hz";
									break;
								case "power":
									$key = "Puissance";
									$unit = "W";
									break;
								case "powerfactor":
									$key = "Facteur de puissance";
									$unit = "";
									break;
								case "voltage":
									$key = "Tension";
									$unit = "V";
									break;
							}
                    $cmd = $eqlogic->getCmd('info',$key);
                    if ($cmd === false) {
                        if($key != 'device'&& $key != 'adresse'){
                            pzem::createCmdFromDef($eqlogic->getLogicalId(), $key, $value, $unit);
                            if($healthEnable) {
                                $healthCmd->setConfiguration($key, array("name" => $key, "value" => $value, "update_time" => date("Y-m-d H:i:s")));
                                $healthCmd->save();
                            }
                        }
                    }
                    else{
                        $cmd->event($value);
                        if($healthEnable) {
                            $healthCmd->setConfiguration($key, array("name" => $key, "value" => $value, "update_time" => date("Y-m-d H:i:s")));
                            $healthCmd->save();
                        }
                    }
                }
            }
            else {
                $pzem = ($data['adresse'] != '') ? pzem::createFromDef($data['adresse']) : pzem::createFromDef($data['adresse']);
                if (!is_object($pzem)) {
                    log::add('pzem', 'info', 'Aucun équipement trouvé pour le PZEM @:' . $data['adresse']);
                    die();
                }
            }
            //log::add('pzem','debug',$var_to_log);
        }
    }

function array_flatten($array) {
    global $var_to_log;
    $return = array();
    foreach ($array as $key => $value) {
        $var_to_log = $var_to_log . $key . '=' . $value . '|';
        if (is_array($value))
            $return = array_merge($return, array_flatten($value));
        else
            $return[$key] = $value;
    }
    return $return;
}
