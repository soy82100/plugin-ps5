<?php
/* This file is part of Jeedom. Plugin PS5. */
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
	include_file('desktop', '404', 'php');
	die();
}
?>
<form class="form-horizontal">
	<fieldset>
		<legend><i class="fas fa-cogs"></i> {{Configuration générale}}</legend>
		<div class="form-group">
			<label class="col-md-4 control-label">{{Intervalle de rafraîchissement (minutes)}}
				<sup><i class="fas fa-question-circle tooltips" title="{{Fréquence d'interrogation de la console. 1 minute par défaut. La requête est locale et très légère (un paquet UDP), un intervalle court ne pose pas de problème.}}"></i></sup>
			</label>
			<div class="col-md-4">
				<input class="configKey form-control" data-l1key="refreshInterval" placeholder="1"/>
			</div>
		</div>
		<div class="form-group">
			<div class="col-md-8 col-md-offset-2">
				<div class="alert alert-info">
					{{L'état de la console (allumée / veille / éteinte) est récupéré nativement, sans aucune dépendance.}}<br/>
					{{Le jeu en cours est optionnel et nécessite un jeton npsso (voir la configuration de l'équipement).}}<br/>
					{{Le réveil et la mise en veille nécessitent la bibliothèque Python pyremoteplay et un appairage à votre compte PSN, à réaliser une seule fois en SSH : voir la documentation du plugin.}}
				</div>
			</div>
		</div>
	</fieldset>
</form>
