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
			<label class="col-md-4 control-label">{{Chemin vers playactor}}
				<sup><i class="fas fa-question-circle tooltips" title="{{Optionnel. Utilisé uniquement pour la mise en veille. Ex : /usr/local/bin/playactor}}"></i></sup>
			</label>
			<div class="col-md-4">
				<input class="configKey form-control" data-l1key="playactorPath" placeholder="/usr/local/bin/playactor"/>
			</div>
		</div>
		<div class="form-group">
			<div class="col-md-8 col-md-offset-2">
				<div class="alert alert-info">
					{{L'état de la console (allumée / veille / éteinte) et l'application en cours sont récupérés nativement, sans dépendance.}}<br/>
					{{Le réveil à distance nécessite un "user-credential" (voir la configuration de l'équipement).}}<br/>
					{{La mise en veille nécessite l'outil playactor (Node.js) installé sur la machine Jeedom.}}
				</div>
			</div>
		</div>
	</fieldset>
</form>
