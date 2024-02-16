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

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class pzem extends eqLogic
{

    public static function getpzemInfo($_url){
        $return = self::deamon_info();
        if ($return['state'] != 'ok') {
            return "";
        }
    }

    public static function socket_connection($value){
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, 0);
            socket_connect($socket, config::byKey('sockethost', 'pzem', '127.0.0.1'), config::byKey('socketport', 'pzem', '5559'));
            socket_write($socket, $value, strlen($value));
            socket_close($socket);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

	/**
	 * Creation objet sur reception de trame
	 * @param string $adco
	 * @return eqLogic
	 */
    public static function createFromDef(string $oDEVICE){
            $pzem = pzem::byLogicalId($oDEVICE, 'pzem');
            if (!is_object($pzem)) {
                $eqLogic = (new pzem())
                        ->setName('PZEM_' . $oDEVICE);
            }
            $eqLogic->setLogicalId($oDEVICE)
                    ->setEqType_name('pzem')
                    ->setIsEnable(1)
                    ->setIsVisible(1);
            $eqLogic->save();
            return $eqLogic;
    }

	/**
	 * Creation commande sur reception de trame
	 * @param $oDEVICE adresse du PZEM
	 * @param $oKey etiquette
	 * @param $oValue valeur
	 * @return Commande
	 */
    public static function createCmdFromDef($oDEVICE, $oKey, $oValue, $oUnit){
        
		if (!isset($oKey) || !isset($oDEVICE)) {
            log::add('pzem', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($oKey, true) . ' ' . print_r($oDEVICE, true));
            return false;
        }
        $pzem = pzem::byLogicalId($oDEVICE, 'pzem');
        if (!is_object($pzem)) {
            return false;
        }
			log::add('pzem', 'info', 'Création de la commande ' . $oKey . ' sur le PZEM @:' . $oDEVICE);
            $cmd = (new pzemCmd())
                    ->setName($oKey)
                    ->setLogicalId($oKey)
                    ->setType('info');
            $cmd->setSubType('numeric')
                    ->setDisplay('generic_type', 'GENERIC_INFO');
            $cmd->setEqLogic_id($pzem->id);
            $cmd->setConfiguration('info_conso', $oKey);
			$cmd->setUnite($oUnit);
            $cmd->setIsHistorized(0)->setIsVisible(1);
            $cmd->save();
            $cmd->event($oValue);
       // }
            return $cmd;
    }

	/**
     *
     * @param type $debug
     * @return boolean
     */
    public static function runDeamon($debug = false){
        log::add('pzem', 'info', 'Démarrage ');
        $pzemPath         	  = realpath(dirname(__FILE__) . '/../../ressources');
		$modemVitesse         = config::byKey('modem_vitesse', 'pzem');
		$socketPort			  = config::byKey('socketport', 'pzem', '5559');
		if (config::byKey('port', 'pzem') == "serie") {
			$port = config::byKey('modem_serie_addr', 'pzem');
		}else {
			$port = jeedom::getUsbMapping(config::byKey('port', 'pzem'));
		}
		
		if ($modemVitesse == "") {
			$modemVitesse = '9600';
		}
		exec('sudo chmod 777 ' . $port . ' > /dev/null 2>&1');
		
        log::add('pzem', 'info', '---------- Informations de lancement ---------');
        log::add('pzem', 'info', 'Port : ' . $port);
		log::add('pzem', 'info', 'Vitesse série : ' . $modemVitesse);
        log::add('pzem', 'info', 'Socket : ' . $socketPort);
        log::add('pzem', 'info', '---------------------------------------------');

        $cmd          = 'nice -n 19 /usr/bin/python3 ' . $pzemPath . '/pzem.py';
		$cmd         .= ' --port ' . $port;
        $cmd         .= ' --vitesse ' . $modemVitesse;
        $cmd         .= ' --apikey ' . jeedom::getApiKey('pzem');
        $cmd         .= ' --socketport ' . $socketPort;
        $cmd         .= ' --cycle ' . config::byKey('cycle', 'pzem','0.3');
        $cmd         .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/pzem/core/php/jeepzem.php';
        $cmd         .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('pzem'));
        /*$cmd         .= ' --cyclesommeil ' . config::byKey('cycle_sommeil', 'pzem', '0.5');*/

        log::add('pzem', 'info', 'Exécution du service : ' . $cmd);
        $result = exec($cmd . ' >> ' . log::getPathToLog('pzem_deamon') . ' 2>&1 &');
        if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
            log::add('pzem', 'error', $result);
            return false;
        }
        sleep(2);
        if (!self::deamonRunning()) {
            sleep(10);
            if (!self::deamonRunning()) {
                log::add('pzem', 'error', 'Impossible de lancer le démon, vérifiez la configuration.', 'unableStartDeamon');
                return false;
            }
        }
        message::removeAll('pzem', 'unableStartDeamon');
        log::add('pzem', 'info', 'Service OK');
        log::add('pzem', 'info', '---------------------------------------------');
		
		$eqLogics = pzem::byType('pzem');
		foreach ($eqLogics as $eqLogic) {
			if ($eqLogic->getLogicalId()!=""){
				log::add('pzem', 'debug', 'Ajout du PZEM @:' . $eqLogic->getLogicalId());
				$eqLogic->sendToDaemon(array('device' => $eqLogic->getLogicalId(),'cmd' =>'add'));
			}
		}
    }

    /**
     *
     * @return boolean
     */
    public static function deamonRunning(){
        $result = exec("ps aux | grep pzem.py | grep -v grep | awk '{print $2}'");
        if ($result != "") {
            return true;
        }
        log::add('pzem', 'info', '[deamonRunning] Vérification de l\'état du service : NOK ');
        return false;
    }

    /**
     *
     * @return array
     */
    public static function deamon_info(){
        $return               = array();
        $return['log']        = 'pzem';
        $return['state']      = 'nok';
		$pidFile = jeedom::getTmpFolder('pzem') . '/pzem.pid';
        if (file_exists($pidFile)) {
            if (posix_getsid(trim(file_get_contents($pidFile)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec('sudo rm -rf ' . $pidFile . ' 2>&1 > /dev/null;rm -rf ' . $pidFile . ' 2>&1 > /dev/null;');
            }
        }
        $return['launchable'] = 'ok';
        return $return;
    }

    /**
     * appelé par jeedom pour démarrer le deamon
     */
    public static function deamon_start($debug = false){
        log::add('pzem', 'info', '[deamon_start] Démarrage du service');
        if (config::byKey('port', 'pzem') != "" ) {    // Si un port est sélectionné
            if (!self::deamonRunning()) {
                self::runDeamon($debug, 'PZEM');
            }
            message::removeAll('pzem', 'nopzemPort');
        } else {
            log::add('pzem', 'info', 'Pas d\'informations sur le port USB (Modem série ?)');
        }
    }

    /**
     * appelé par jeedom pour arrêter le deamon
     */
    public static function deamon_stop(){
        log::add('pzem', 'info', '[deamon_stop] Arret du service');
        $deamonInfo = self::deamon_info();
        if ($deamonInfo['state'] == 'ok') {
			$pidFile = jeedom::getTmpFolder('pzem') . '/pzem.pid';
			if (file_exists($pidFile)) {
				$pid  = intval(trim(file_get_contents($pidFile)));
				$kill = posix_kill($pid, 15);
				usleep(500);
				if ($kill) {
					return true;
				} else {
					system::kill($pid);
				}
			}
			system::kill('pzem.py');
			$port = config::byKey('port', 'pzem');
			if ($port != "serie") {
				$port = jeedom::getUsbMapping(config::byKey('port', 'pzem'));
				system::fuserk(jeedom::getUsbMapping($port));
				sleep(1);

            }
        }
    }
  
    public static function sendToDaemon($_value) {
		log::add('pzem', 'debug', 'Envois de la nouvelle address au daemon');
        $value = array_merge(array('apikey' => jeedom::getApiKey('pzem')),$_value);
        $value = json_encode($value);
		log::add('pzem', 'info', 'Envois de value en json:'. $value);
        self::socket_connection($value,True);
  }

    public function preSave(){
        log::add('pzem', 'debug', '-------- PRESAVE --------');
		if ($this->getLogicalId()!=""){
			log::add('pzem', 'debug', 'suppression du PZEM au daemon');
			$this->sendToDaemon(array('device' => $this->getLogicalId(),'cmd' =>'del'));
		}
        $this->setCategory('energy', 1);
        $cmd = $this->getCmd('info', 'HEALTH');
        if (is_object($cmd)) {
            $cmd->remove();
        }

    }

    public function postSave(){
        log::add('pzem', 'debug', '-------- Sauvegarde de l\'objet --------');
        log::add('pzem', 'info', '==> Gestion des id des commandes');
        foreach ($this->getCmd('info') as $cmd) {
            log::add('pzem', 'debug', 'Commande : ' . $cmd->getConfiguration('info_conso'));
            $cmd->setLogicalId($cmd->getConfiguration('info_conso'));
            $cmd->save();
        }
		if ($this->getLogicalId()!=""){
			log::add('pzem', 'debug', 'Ajout du PZEM au daemon');
			$this->sendToDaemon(array('device' => $this->getLogicalId(),'cmd' =>'add'));
		}
        log::add('pzem', 'debug', '-------- Fin de la sauvegarde --------');
    }

    public function preRemove(){
        log::add('pzem', 'debug', 'Suppression d\'un objet');
		if ($this->getLogicalId()!=""){
			log::add('pzem', 'debug', 'suppression du PZEM au daemon');
			$this->sendToDaemon(array('device' => $this->getLogicalId(),'cmd' =>'del'));
		}
    }

    public function createOtherCmd(){
        log::add('pzem', 'debug', '-------- Santé --------');
        $array = array("HEALTH");
        foreach ($array as $value){
            $cmd = $this->getCmd('info', $value);
            if (!is_object($cmd)) {
                $cmd = new pzemCmd();
                $cmd->setName($value);
                $cmd->setEqLogic_id($this->id);
                $cmd->setLogicalId($value);
                $cmd->setType('info');
                $cmd->setConfiguration('info_conso', $value);
                $cmd->setConfiguration('type', 'health');
                $cmd->setSubType('string');
                $cmd->setIsHistorized(0);
                $cmd->setEventOnly(1);
                $cmd->setIsVisible(0);
                $cmd->save();
            }
        }
    }

    public function createPanelStats(){
        log::add('pzem', 'debug', '-------- Commandes des stats ---------');
        $array = array("pzem","pzem_HC", "pzem_HP", "pzem_PROD","STAT_YESTERDAY","STAT_YESTERDAY_HC","STAT_YESTERDAY_HP","STAT_YESTERDAY_PROD");
        foreach ($array as $value){
            $cmd = $this->getCmd('info', $value);

            if ($cmd === false) {
                log::add('pzem', 'debug', 'Nouvelle => ' . $value);
                $cmd = new pzemCmd();
                $cmd->setName($value);
                $cmd->setEqLogic_id($this->id);
                $cmd->setLogicalId($value);
                $cmd->setType('info');
                $cmd->setConfiguration('info_conso', $value);
                $cmd->setConfiguration('type', 'stat');
				$cmd->setConfiguration('historizeMode', 'none');
                $cmd->setDisplay('generic_type', 'DONT');
                $cmd->setSubType('numeric');
                $cmd->setUnite('Wh');
                $cmd->setIsHistorized(1);
                $cmd->setEventOnly(1);
                $cmd->setIsVisible(0);
                $cmd->save();
                $cmd->refresh();
            } else {
                log::add('pzem', 'debug', 'Ancienne => ' . $value);
                $cmd->setIsHistorized(1);
                $cmd->setConfiguration('type', 'stat');
                $cmd->setConfiguration('historizeMode', 'none');
                $cmd->setDisplay('generic_type', 'DONT');
                $cmd->save();
                $cmd->refresh();
            }

        }
    }

    public function CreateFromAbo($_abo){
        $this->setConfiguration('AutoGenerateFields', '0');
        $this->save();
    }

    /*     * ******** MANAGEMENT ZONE ******* */
    public static function dependancy_info(){
        $return                  = array();
        $return['log']           = 'pzem_update';
        $return['progress_file'] = '/tmp/jeedom/pzem/dependance';
        $return['state']         = (self::installationOk()) ? 'ok' : 'nok';
        return $return;
    }

    public static function installationOk(){
        try {
            $dependances_version = config::byKey('dependancy_version', 'pzem', 0);
            if (intval($dependances_version) >= 1.0) {
                return true;
            } else {
                config::save('dependancy_version', 1.0, 'pzem');
                return false;
            }
        } catch (\Exception $e) {
            return true;
        }
    }

    public static function dependancy_install(){
        log::remove(__CLASS__ . '_update');
        return array('script' => __DIR__ . '/../../ressources/install_#stype#.sh ' . jeedom::getTmpFolder('pzem') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
    }

}

class pzemCmd extends cmd{

    public function execute($_options = null){
		if ($this->getType() != 'action') {
		return;
		}
	}
}
