<?php
/* This file is part of Jeedom. Plugin PS5. */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function ps5_install() {
	// Rien de particulier : pas de démon
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function ps5_update() {
	// Re-création des commandes manquantes sur les équipements existants
	foreach (eqLogic::byType('ps5') as $eqLogic) {
		$eqLogic->save();
	}
}

// Fonction exécutée automatiquement après la suppression du plugin
function ps5_remove() {
}

/**
 * État des dépendances.
 *
 * Le plugin a besoin de pyremoteplay (mise en veille de la console), installé
 * dans un environnement virtuel Python dédié : resources/python_venv.
 * La dépendance est considérée satisfaite si la bibliothèque est importable.
 */
function ps5_dependancy_info() {
	$return = array();
	$return['progress_file'] = jeedom::getTmpFolder('ps5') . '/dependance';
	$return['log'] = log::getPathToLog('ps5_update');

	$python = dirname(__FILE__) . '/../resources/python_venv/bin/python3';
	if (!file_exists($python)) {
		$return['state'] = 'nok';
		return $return;
	}

	$output = array();
	$code = 0;
	exec(escapeshellarg($python) . ' -c "import pyremoteplay" 2>&1', $output, $code);
	$return['state'] = ($code === 0) ? 'ok' : 'nok';
	return $return;
}

/**
 * Installation des dépendances.
 *
 * On n'utilise volontairement PAS packages.json : Jeedom y installe les paquets
 * pip un par un avec --force-reinstall --upgrade, si bien que chaque paquet
 * écrase les versions posées par les précédents. Or pyremoteplay 0.7.6 exige
 * pyee == 9.1.1 (ImportError au-delà) et un async_timeout qu'il ne déclare pas.
 * Le script dédié fait un seul pip install groupé, ce qui laisse pip résoudre
 * les versions ensemble.
 */
function ps5_dependancy_install() {
	log::remove('ps5_update');
	return array(
		'script' => dirname(__FILE__) . '/../resources/install_apt.sh '
			. jeedom::getTmpFolder('ps5') . '/dependance',
		'log' => log::getPathToLog('ps5_update'),
	);
}
