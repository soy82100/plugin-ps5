<?php
/* This file is part of Jeedom. Plugin PS5. */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function ps5_install() {
	// Pas de démon. La bibliothèque pyremoteplay, nécessaire uniquement à la
	// mise en veille, s'installe manuellement (voir la documentation) : son
	// appairage au compte PSN passe par un navigateur et n'est pas automatisable.
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
