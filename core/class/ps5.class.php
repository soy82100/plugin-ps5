<?php
/* This file is part of Jeedom. Plugin PS5.
 *
 * Deux sources d'information, volontairement separees :
 *
 *  - LOCAL (aucune dependance, aucun cloud) :
 *      Etat de la console et reveil/veille, via le protocole DDP (UDP 9302)
 *      et resources/ps5_cli.py (pyremoteplay).
 *
 *  - CLOUD (optionnel, desactive par defaut) :
 *      Jeu en cours, via l'API de presence PSN (PSNAWP).
 *      Les firmwares PS5 recents ne diffusent PLUS le champ running-app-name
 *      dans leur reponse DDP : le titre du jeu n'est pas recuperable en local.
 *      Sans jeton npsso configure, le plugin reste 100% local.
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class ps5 extends eqLogic {

	const DDP_PORT = 9302;              // PS5 (la PS4 utilisait 987)
	const DDP_VERSION = '00030010';     // PS5 (PS4 : 00020020)

	/*     * *************************** Cron ****************************** */

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

		// Jaquette du jeu (URL) - alimentee uniquement si la presence PSN est active
		$jaquette = $this->getCmd(null, 'jaquette');
		if (!is_object($jaquette)) {
			$jaquette = new ps5Cmd();
			$jaquette->setLogicalId('jaquette');
			$jaquette->setName(__('Jaquette', __FILE__));
			$jaquette->setEqLogic_id($this->getId());
			$jaquette->setType('info');
			$jaquette->setSubType('string');
			$jaquette->setIsVisible(0);
			$jaquette->setOrder($order++);
			$jaquette->save();
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

	/**
	 * Widget d'équipement personnalisé.
	 *
	 * Le div racine du template porte la classe `eqLogic-widget` : c'est elle que
	 * recherchent les sélecteurs du module Design (desktop/js/plan.js) pour
	 * appliquer position/dimensions et activer déplacement, redimensionnement et
	 * menu contextuel. Sans elle, l'élément est invisible pour le Design.
	 */
	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);

		$logicalIds = array('online', 'etat', 'application', 'jaquette', 'wake', 'standby', 'refresh');
		foreach ($logicalIds as $lid) {
			$cmd = $this->getCmd(null, $lid);
			$replace['#' . $lid . '_id#'] = is_object($cmd) ? $cmd->getId() : '';
			$replace['#' . $lid . '_name#'] = is_object($cmd) ? $cmd->getName() : '';
			if (is_object($cmd) && $cmd->getType() == 'info') {
				$val = $cmd->execCmd();
				$replace['#' . $lid . '_value#'] = ($val === null || $val === '') ? '' : $val;
			} else {
				$replace['#' . $lid . '_value#'] = '';
			}
		}

		return $this->postToHtml($_version, template_replace($replace,
			getTemplate('core', $version, 'eqLogic.ps5', 'ps5')));
	}

	/**
	 * Interroge la console (local) et, si configurée, la présence PSN (cloud).
	 */
	public function refreshInfo() {
		$ip = trim($this->getConfiguration('ip'));
		if ($ip == '') {
			return;
		}

		$result = self::discover($ip);
		log::add('ps5', 'debug', $this->getHumanName() . ' : ' . json_encode($result));

		$this->checkAndUpdateCmd('online', $result['online']);
		$this->checkAndUpdateCmd('etat', $result['etat']);

		// Jeu en cours : uniquement via la présence PSN, et seulement si la console
		// est allumée (inutile d'interroger le cloud sinon).
		$jeu = '';
		$jaquette = '';
		if ($result['online'] == 1 && trim($this->getConfiguration('npsso')) != '') {
			try {
				$presence = $this->getPresence();
				$jeu = isset($presence['jeu']) ? $presence['jeu'] : '';
				$jaquette = isset($presence['jaquette']) ? $presence['jaquette'] : '';
			} catch (Exception $e) {
				// Non bloquant : l'état local reste valide même si le cloud échoue.
				log::add('ps5', 'warning', $this->getHumanName() . ' : présence PSN : ' . $e->getMessage());
			}
		}

		$this->checkAndUpdateCmd('application', $jeu);
		$this->checkAndUpdateCmd('jaquette', $jaquette);
		$this->refreshWidget();
	}

	/**
	 * Envoie un paquet SRCH (DDP) à la console et analyse la réponse.
	 *
	 * Note : les firmwares PS5 récents ne renseignent plus `running-app-name`.
	 * On le lit tout de même, au cas où Sony le réactiverait — mais on n'invente
	 * plus de valeur par défaut : afficher « Menu d'accueil » alors que la console
	 * est en jeu est une information fausse.
	 *
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
			return $result;
		}

		if (strpos($buf, '200 Ok') !== false) {
			$result['online'] = 1;
			$result['etat'] = __('Allumée', __FILE__);
		} elseif (strpos($buf, '620') !== false) {
			$result['online'] = 0;
			$result['etat'] = __('Veille', __FILE__);
		}

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

		return $result;
	}

	/*     * ******************* Appels au CLI Python *********************** */

	/**
	 * Interpréteur Python à utiliser.
	 *
	 * Les bibliothèques sont installées dans un environnement virtuel dédié au
	 * plugin : le Python système ne les connaît pas.
	 */
	private static function getPython() {
		$venv = dirname(__FILE__) . '/../../resources/python_venv/bin/python3';
		if (file_exists($venv)) {
			return $venv;
		}
		return '/usr/bin/python3';
	}

	private static function getCli() {
		$cli = realpath(dirname(__FILE__) . '/../../resources/ps5_cli.py');
		if ($cli === false) {
			throw new Exception(__('ps5_cli.py introuvable dans le dossier resources du plugin', __FILE__));
		}
		return $cli;
	}

	/**
	 * Exécute le CLI et retourne sa réponse JSON décodée.
	 *
	 * HOME est indispensable : pyremoteplay y cherche le profil d'appairage
	 * (/var/www/.pyremoteplay/.profile.json). Jeedom n'exporte pas toujours HOME.
	 */
	private function runCli($args) {
		$cmd = 'HOME=/var/www ' . escapeshellarg(self::getPython()) . ' '
			. escapeshellarg(self::getCli()) . ' ' . $args . ' 2>&1';

		log::add('ps5', 'info', $this->getHumanName() . ' : ' . $cmd);
		exec($cmd, $output, $code);
		$raw = trim(implode('', $output));
		log::add('ps5', 'debug', 'ps5_cli (' . $code . ') : ' . $raw);

		$json = json_decode($raw, true);
		if (!is_array($json)) {
			throw new Exception(__('Réponse illisible du CLI : ', __FILE__) . $raw);
		}
		if (empty($json['success'])) {
			throw new Exception(isset($json['error']) ? $json['error'] : __('erreur inconnue', __FILE__));
		}
		return $json;
	}

	private function execCli($action) {
		$ip = trim($this->getConfiguration('ip'));
		if ($ip == '') {
			throw new Exception(__('Aucune adresse IP configurée', __FILE__));
		}
		return $this->runCli(escapeshellarg($action) . ' --ip ' . escapeshellarg($ip));
	}

	/**
	 * Jeu en cours, via l'API de présence PSN.
	 *
	 * Le jeton npsso est écrit dans un fichier temporaire à droits restreints
	 * plutôt que passé en argument : un argument de ligne de commande est visible
	 * de tous les utilisateurs de la machine (ps aux).
	 */
	private function getPresence() {
		$npsso = trim($this->getConfiguration('npsso'));
		if ($npsso == '') {
			throw new Exception(__('Aucun jeton npsso configuré', __FILE__));
		}

		$dir = jeedom::getTmpFolder('ps5');
		if (!is_dir($dir)) {
			@mkdir($dir, 0770, true);
		}
		$file = $dir . '/npsso_' . $this->getId();

		if (file_put_contents($file, $npsso) === false) {
			throw new Exception(__('Impossible d\'écrire le jeton npsso temporaire', __FILE__));
		}
		@chmod($file, 0600);

		try {
			$json = $this->runCli('presence --npsso-file ' . escapeshellarg($file));
		} finally {
			@unlink($file);
		}

		return $json;
	}

	/**
	 * Réveil de la console.
	 *
	 * Deux méthodes :
	 *  1. user-credential renseigné : paquet WAKEUP DDP en PHP pur (instantané).
	 *     Conservé pour les installations existantes.
	 *  2. Sinon : pyremoteplay, qui réveille à partir du seul profil d'appairage.
	 *     C'est la méthode recommandée — le user-credential n'est plus récupérable,
	 *     playactor échouant à l'appairage (403) sur les firmwares récents.
	 */
	public function wake() {
		$ip = trim($this->getConfiguration('ip'));
		if ($ip == '') {
			throw new Exception(__('Aucune adresse IP configurée', __FILE__));
		}

		$credential = trim($this->getConfiguration('credential'));

		if ($credential != '') {
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
			return;
		}

		$this->execCli('wakeup');
	}

	public function standby() {
		$this->execCli('standby');
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
