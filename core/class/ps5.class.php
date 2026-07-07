<?php
/* This file is part of Jeedom. Plugin PS5.
 *
 * Communication avec la PlayStation 5 via le protocole DDP
 * (Device Discovery Protocol) sur le port UDP 9302 :
 *  - SRCH   : interroge l'état de la console (200 Ok = allumée, 620 = veille)
 *  - WAKEUP : réveille la console (nécessite un user-credential)
 * La mise en veille passe par l'outil externe playactor (optionnel).
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class ps5 extends eqLogic {

	const DDP_PORT = 9302;              // PS5 (la PS4 utilisait 987)
	const DDP_VERSION = '00030010';     // PS5 (PS4 : 00020020)

	/*     * *************************** Cron ****************************** */

	// Appelé par Jeedom toutes les minutes (activer "cron" dans la config du plugin)
	// L'intervalle réel est configurable dans la configuration du plugin (défaut : 1 min)
	public static function cron() {
		$interval = (int) config::byKey('refreshInterval', 'ps5', 1);
		if ($interval < 1) {
			$interval = 1;
		}
		if ((date('i') % $interval) != 0) {
			return;
		}
		foreach (self::byType('ps5', true) as $eqLogic) {
			try {
				$eqLogic->refreshInfo();
			} catch (Exception $e) {
				log::add('ps5', 'error', $eqLogic->getHumanName() . ' : ' . $e->getMessage());
			}
		}
	}

	/*     * *********************** Méthodes d'instance ******************* */

	public function postSave() {
		$this->createCommands();
		try {
			$this->refreshInfo();
		} catch (Exception $e) {
			// pas bloquant à la sauvegarde
		}
	}

	private function createCommands() {
		$order = 1;

		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new ps5Cmd();
			$refresh->setLogicalId('refresh');
			$refresh->setName(__('Rafraîchir', __FILE__));
			$refresh->setEqLogic_id($this->getId());
			$refresh->setType('action');
			$refresh->setSubType('other');
			$refresh->setOrder($order++);
			$refresh->save();
		}

		$online = $this->getCmd(null, 'online');
		if (!is_object($online)) {
			$online = new ps5Cmd();
			$online->setLogicalId('online');
			$online->setName(__('Allumée', __FILE__));
			$online->setEqLogic_id($this->getId());
			$online->setType('info');
			$online->setSubType('binary');
			$online->setIsHistorized(1);
			$online->setOrder($order++);
			$online->save();
		}

		$etat = $this->getCmd(null, 'etat');
		if (!is_object($etat)) {
			$etat = new ps5Cmd();
			$etat->setLogicalId('etat');
			$etat->setName(__('État', __FILE__));
			$etat->setEqLogic_id($this->getId());
			$etat->setType('info');
			$etat->setSubType('string');
			$etat->setOrder($order++);
			$etat->save();
		}

		$app = $this->getCmd(null, 'application');
		if (!is_object($app)) {
			$app = new ps5Cmd();
			$app->setLogicalId('application');
			$app->setName(__('Application en cours', __FILE__));
			$app->setEqLogic_id($this->getId());
			$app->setType('info');
			$app->setSubType('string');
			$app->setOrder($order++);
			$app->save();
		}

		$wake = $this->getCmd(null, 'wake');
		if (!is_object($wake)) {
			$wake = new ps5Cmd();
			$wake->setLogicalId('wake');
			$wake->setName(__('Réveiller', __FILE__));
			$wake->setEqLogic_id($this->getId());
			$wake->setType('action');
			$wake->setSubType('other');
			$wake->setOrder($order++);
			$wake->save();
		}

		$standby = $this->getCmd(null, 'standby');
		if (!is_object($standby)) {
			$standby = new ps5Cmd();
			$standby->setLogicalId('standby');
			$standby->setName(__('Mettre en veille', __FILE__));
			$standby->setEqLogic_id($this->getId());
			$standby->setType('action');
			$standby->setSubType('other');
			$standby->setOrder($order++);
			$standby->save();
		}
	}

	// Interroge la console et met à jour les commandes info
	public function refreshInfo() {
		$ip = trim($this->getConfiguration('ip'));
		if ($ip == '') {
			return;
		}
		$result = self::discover($ip);
		log::add('ps5', 'debug', $this->getHumanName() . ' : ' . json_encode($result));
		$this->checkAndUpdateCmd('online', $result['online']);
		$this->checkAndUpdateCmd('etat', $result['etat']);
		$this->checkAndUpdateCmd('application', $result['application']);
		$this->refreshWidget();
	}

	/**
	 * Envoie un paquet SRCH (DDP) à la console et analyse la réponse.
	 * @return array [online => 0/1, etat => string, application => string]
	 */
	public static function discover($ip, $timeoutSec = 2) {
		$result = array('online' => 0, 'etat' => __('Éteinte / injoignable', __FILE__), 'application' => '');

		$socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if ($socket === false) {
			throw new Exception(__('Impossible de créer la socket UDP', __FILE__));
		}
		socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeoutSec, 'usec' => 0));
		socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $timeoutSec, 'usec' => 0));

		$msg = "SRCH * HTTP/1.1\n" .
			"device-discovery-protocol-version:" . self::DDP_VERSION . "\n";

		@socket_sendto($socket, $msg, strlen($msg), 0, $ip, self::DDP_PORT);

		$buf = '';
		$from = '';
		$port = 0;
		$len = @socket_recvfrom($socket, $buf, 2048, 0, $from, $port);
		socket_close($socket);

		if ($len === false || $buf == '') {
			// Pas de réponse : console éteinte, IP incorrecte ou console sur un autre VLAN
			return $result;
		}

		if (strpos($buf, '200 Ok') !== false) {
			$result['online'] = 1;
			$result['etat'] = __('Allumée', __FILE__);
		} elseif (strpos($buf, '620') !== false) {
			$result['online'] = 0;
			$result['etat'] = __('Veille', __FILE__);
		}

		// La réponse contient des champs clé:valeur (host-name, running-app-name, ...)
		foreach (explode("\n", $buf) as $line) {
			$pos = strpos($line, ':');
			if ($pos === false) {
				continue;
			}
			$key = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1));
			if ($key == 'running-app-name') {
				$result['application'] = $value;
			}
		}
		if ($result['online'] == 1 && $result['application'] == '') {
			$result['application'] = __('Menu d\'accueil', __FILE__);
		}
		return $result;
	}

	// Réveil de la console via paquet WAKEUP (nécessite le user-credential)
	public function wake() {
		$ip = trim($this->getConfiguration('ip'));
		$credential = trim($this->getConfiguration('credential'));
		if ($ip == '') {
			throw new Exception(__('Aucune adresse IP configurée', __FILE__));
		}
		if ($credential == '') {
			throw new Exception(__('Aucun user-credential configuré : voir l\'onglet de configuration de l\'équipement', __FILE__));
		}

		$socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if ($socket === false) {
			throw new Exception(__('Impossible de créer la socket UDP', __FILE__));
		}
		$msg = "WAKEUP * HTTP/1.1\n" .
			"client-type:vr\n" .
			"auth-type:R\n" .
			"model:w\n" .
			"app-type:r\n" .
			"user-credential:" . $credential . "\n" .
			"device-discovery-protocol-version:" . self::DDP_VERSION . "\n";
		@socket_sendto($socket, $msg, strlen($msg), 0, $ip, self::DDP_PORT);
		socket_close($socket);
		log::add('ps5', 'info', $this->getHumanName() . ' : paquet WAKEUP envoyé à ' . $ip);
	}

	// Mise en veille via playactor (session TCP chiffrée, trop complexe en PHP pur)
	public function standby() {
		$ip = trim($this->getConfiguration('ip'));
		$playactor = trim(config::byKey('playactorPath', 'ps5', '/usr/local/bin/playactor'));
		if ($ip == '') {
			throw new Exception(__('Aucune adresse IP configurée', __FILE__));
		}
		if ($playactor == '' || !file_exists($playactor)) {
			throw new Exception(__('playactor introuvable : configurez son chemin dans la configuration du plugin (npm install -g playactor)', __FILE__));
		}
		$cmd = escapeshellarg($playactor) . ' standby --ip ' . escapeshellarg($ip) . ' --timeout 15000 2>&1';
		log::add('ps5', 'info', $this->getHumanName() . ' : ' . $cmd);
		exec($cmd, $output, $code);
		log::add('ps5', 'debug', 'playactor (' . $code . ') : ' . implode(' | ', $output));
		if ($code !== 0) {
			throw new Exception(__('Échec de la mise en veille : ', __FILE__) . implode(' ', $output));
		}
	}
}

class ps5Cmd extends cmd {

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		switch ($this->getLogicalId()) {
			case 'refresh':
				$eqLogic->refreshInfo();
				break;
			case 'wake':
				$eqLogic->wake();
				sleep(3);
				$eqLogic->refreshInfo();
				break;
			case 'standby':
				$eqLogic->standby();
				sleep(3);
				$eqLogic->refreshInfo();
				break;
		}
		return;
	}
}
