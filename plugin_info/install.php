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


function pzem_install() {
    $core_version = '1.1.1';
    if (!file_exists(dirname(__FILE__) . '/info.json')) {
        log::add('pzem','warning','Pas de fichier info.json');
        goto step2;
    }
    $data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
    if (!is_array($data)) {
        log::add('pzem','warning','Impossible de décoder le fichier info.json');
        goto step2;
    }
    try {
        $core_version = $data['pluginVersion'];
    } catch (\Exception $e) {

    }
    step2:
    if (pzem::deamonRunning()) {
        pzem::deamon_stop();
    }

    message::removeAll('pzem');
    message::add('pzem', 'Installation du plugin pzem terminée, vous êtes en version ' . $core_version . '.', null, null);

}

function pzem_update() {
    log::add('pzem','debug','pzem_update');
    $core_version = '1.1.1';
    if (!file_exists(dirname(__FILE__) . '/info.json')) {
        log::add('pzem','warning','Pas de fichier info.json');
        goto step2;
    }
    $data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
    if (!is_array($data)) {
        log::add('pzem','warning','Impossible de décoder le fichier info.json');
        goto step2;
    }
    try {
        $core_version = $data['pluginVersion'];
    } catch (\Exception $e) {
        log::add('pzem','warning','Pas de version de plugin');
    }
    step2:
    if (pzem::deamonRunning()) {
        pzem::deamon_stop();
    }
    message::add('pzem', 'Mise à jour du plugin PZEM en cours...', null, null);
    log::add('pzem','info','*****************************************************');
    log::add('pzem','info','*********** Mise à jour du plugin PZEM **********');
    log::add('pzem','info','*****************************************************');
    log::add('pzem','info','**        Core version    : '. $core_version. '                  **');
    log::add('pzem','info','*****************************************************');

    if (is_object($cron)) {
        $cron->remove();
    }
    if (is_object($crontoday)) {
        $crontoday->remove();
    }

    if (!is_object($cron)) {
        $cron = new cron();
        $cron->setClass('pzem');
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule('10 00 * * *');
        $cron->save();
    }
    else{
        $cron->setSchedule('10 00 * * *');
        $cron->save();
    }
    $cron->stop();

    if (!is_object($crontoday)) {
        $crontoday = new cron();
        $crontoday->setClass('pzem');
        $crontoday->setEnable(1);
        $crontoday->setDeamon(0);
        $crontoday->setSchedule('*/5 * * * *');
        $crontoday->save();
    }
    $crontoday->stop();
    message::removeAll('pzem');
    message::add('pzem', 'Mise à jour du plugin Mulimètre PZEM terminée, vous êtes en version ' . $core_version . '.', null, null);
    pzem::cron();
}

function pzem_remove() {
    if (pzem::deamonRunning()) {
        pzem::deamon_stop();
    }
    if (is_object($cron)) {
        $cron->remove();
    }
    if (is_object($crontoday)) {
        $crontoday->remove();
    }
    message::removeAll('pzem');
    message::add('pzem', 'Désinstallation du plugin Mulimètre PZEM terminée, vous pouvez de nouveau relever les index à la main ;)', null, null);
}
