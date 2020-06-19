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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new \Exception('401 Unauthorized');
    }

    switch (init('action')){
        case 'stopDeamon':
            pzem::deamon_stop();
            ajax::success();
        break;
        case 'restartDeamon':
            pzem::deamon_stop();
            pzem::deamon_start();
            ajax::success();
        break;
        case 'changeLogLive':
            ajax::success(pzem::changeLogLive(init('level')));
        break;
        case 'getpzem':
            if (init('object_id') == '') {
                $_GET['object_id'] = $_SESSION['user']->getOptions('defaultDashboardObject');
            }
            $object = jeeObject::byId(init('object_id'));
		    if (!is_object($object)) {
                $object = jeeObject::rootObject();
            }
            if (!is_object($object)) {
                throw new \Exception('{{Aucun objet racine trouve}}');
            }
            $return = array('object' => utils::o2a($object));

            $date = array(
                'start' => init('dateStart'),
                'end'   => init('dateEnd'),
            );

            if ($date['start'] == '') {
                $date['start'] = date('Y-m-d', strtotime('-6 days' . date('Y-m-d')));
            }
            if ($date['end'] == '') {
                $date['end'] = date('Y-m-d', strtotime('+1 days' . date('Y-m-d')));
            }
            $return['date'] = $date;
            foreach ($object->getEqLogic() as $eqLogic) {
                if ($eqLogic->getIsVisible() == '1' && $eqLogic->getEqType_name() == 'pzem') {
                    $return['eqLogics'][] = array('eqLogic' => utils::o2a($eqLogic), 'html' => $eqLogic->toHtml(init('version')));
                }
            }
            ajax::success($return);
        break;
        case 'getInformation':
            if (init('eqLogic_id') !== '') {

                $eqLogic                          = eqLogic::byId(init('eqLogic_id'));
                $return[$eqLogic->getId()]        = utils::o2a($eqLogic);
                $return[$eqLogic->getId()]['cmd'] = array();
                foreach ($eqLogic->getCmd() as $cmd) {
                    $cmd_info                           = utils::o2a($cmd);
                    $cmd_info['value']                  = $cmd->execCmd(null, 2);
                    $return[$eqLogic->getId()]['cmd'][] = $cmd_info;
                }
                ajax::success($return);
            } else {
                $eqLogics = eqLogic::byType('pzem');
                foreach ($eqLogics as $eqLogic) {
                    $return[$eqLogic->getId()]        = utils::o2a($eqLogic);
                    $return[$eqLogic->getId()]['cmd'] = array();
                    foreach ($eqLogic->getCmd() as $cmd) {
                        $cmd_info                           = utils::o2a($cmd);
                        $cmd_info['value']                  = $cmd->execCmd(null, 2);
                        $return[$eqLogic->getId()]['cmd'][] = $cmd_info;
                    }
                }
                ajax::success($return);
            }
        break;
		case 'UpdateAddress':
			if (init('address') !== '' && init('newaddress') !== '' ) {
				log::add('pzem', 'info', 'Ajax update address'. init('address') );
				log::add('pzem', 'info', 'Ajax update nouvelle address' . init('newaddress'));
				pzem::sendToDaemon(array('device' => init('address'),'cmd' =>'set','newaddress' => init('newaddress')));
				sleep(1);
				foreach (eqLogic::byType('pzem') as $eqLogic) {
					log::add('pzem', 'info', 'Ajax for eqlogic id=' . $eqLogic->getLogicalId());
					if ($eqLogic->getLogicalId() == init('address')) {
						log::add('pzem', 'info', 'Ajax for change eqlogic id=' . $eqLogic->getLogicalId() . 'to' . init('newaddress'));
						$eqLogic->setLogicalId(init('newaddress'));
						$eqLogic->save();
					}
				}
				ajax::success($return);
			}
        break;
        case 'getHealth':
            if (init('eqLogicID') !== '') {
                $pzem       = pzem::byLogicalId(init('eqLogicID'), 'pzem');
                $health_cmd     = $pzem->getCmd('info', 'health');
                $return         = array('object' => utils::o2a($health_cmd));
                $return["ADCO"] = init('eqLogicID');
                ajax::success($return);
            } else {
                foreach (eqLogic::byType('pzem') as $eqLogic) {
                    $health_cmd     = $eqLogic->getCmd('info', 'health');
                    $return         = array('object' => utils::o2a($health_cmd));
                    $return["ADCO"] = $eqLogic->getLogicalId();
                    ajax::success($return);
                }
            }
            ajax::error("", "");
        break;
        case 'getInfoDaemon':
            $return   = array();
            $_nbLines = 1000;
            $replace  = array(
                '&gt;'   => '>',
                '&apos;' => '',
            );
            $page     = array();

            $path = realpath(dirname(__FILE__) . '/../../ressources/pzem.log');
            if (!file_exists($path)) {
                $return['result'] = array('Deamon non lancé');
                ajax::success($return);
            } else {
                $log = new SplFileObject($path);
                if ($log) {
                    $log->seek(0); //Seek to the begening of lines
                    $linesRead = 0;
                    while ($log->valid() && $linesRead != $_nbLines) {
                        $line = trim($log->current()); //get current line
                        if ($line != '') {
                            array_unshift($page, $line);
                        }
                        $log->next(); //go to next line
                        $linesRead++;
                    }
                }
                $return['result'] = $page;
                ajax::success($return);
            }
        break;
        case 'getHistory':
            $return = array();
            $return = history::byCmdIdDatetime(init('id'), date('Y-m-d H:i:s'));
            ajax::success($return);
        break;
    }
    throw new \Exception('Aucune methode correspondante');
} catch (\Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
