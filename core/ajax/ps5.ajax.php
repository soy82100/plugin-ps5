<?php
/* This file is part of Jeedom. Plugin PS5. */

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	ajax::init();

	if (init('action') == 'refreshInfo') {
		$eqLogic = ps5::byId(init('id'));
		if (!is_object($eqLogic)) {
			throw new Exception(__('Équipement PS5 introuvable : ', __FILE__) . init('id'));
		}
		$eqLogic->refreshInfo();
		ajax::success();
	}

	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
