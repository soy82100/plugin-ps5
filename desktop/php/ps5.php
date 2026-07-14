<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('ps5');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div id="div_pageContainer">
	<div class="row row-overflow">
		<div class="col-xs-12 eqLogicThumbnailDisplay">
			<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
			<div class="eqLogicThumbnailContainer">
				<div class="cursor eqLogicAction logoPrimary" data-action="add">
					<i class="fas fa-plus-circle"></i>
					<br/>
					<span>{{Ajouter}}</span>
				</div>
				<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
					<i class="fas fa-wrench"></i>
					<br/>
					<span>{{Configuration}}</span>
				</div>
			</div>
			<legend><i class="fab fa-playstation"></i> {{Mes PS5}}</legend>
			<?php
			if (count($eqLogics) == 0) {
				echo '<br/><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement PS5 trouvé, cliquez sur "Ajouter" pour commencer}}</div>';
			} else {
				echo '<div class="input-group" style="margin:5px;">';
				echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic"/>';
				echo '<div class="input-group-btn">';
				echo '<a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>';
				echo '</div>';
				echo '</div>';
				echo '<div class="eqLogicThumbnailContainer">';
				foreach ($eqLogics as $eqLogic) {
					$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
					echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
					echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
					echo '<br/>';
					echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
					echo '</div>';
				}
				echo '</div>';
			}
			?>
		</div>

		<div class="col-xs-12 eqLogic" style="display: none;">
			<div class="input-group pull-right" style="display:inline-flex">
				<span class="input-group-btn">
					<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
					<a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span></a>
					<a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
					<a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
				</span>
			</div>
			<ul class="nav nav-tabs" role="tablist">
				<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
				<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Équipement}}</a></li>
				<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
			</ul>
			<div class="tab-content">
				<div role="tabpanel" class="tab-pane active" id="eqlogictab">
					<form class="form-horizontal">
						<fieldset>
							<div class="col-lg-6">
								<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
									<div class="col-sm-6">
										<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;"/>
										<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement (ex : PS5 Salon)}}"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{Objet parent}}</label>
									<div class="col-sm-6">
										<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
											<option value="">{{Aucun}}</option>
											<?php
											$options = '';
											foreach ((jeeObject::buildTree(null, false)) as $object) {
												$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
											}
											echo $options;
											?>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{Catégorie}}</label>
									<div class="col-sm-6">
										<?php
										foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
											echo '<label class="checkbox-inline">';
											echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '"/>' . $value['name'];
											echo '</label>';
										}
										?>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{Options}}</label>
									<div class="col-sm-6">
										<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
										<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
									</div>
								</div>
							</div>
							<div class="col-lg-6">
								<legend><i class="fab fa-playstation"></i> {{Paramètres PS5}}</legend>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{Adresse IP}}
										<sup><i class="fas fa-question-circle tooltips" title="{{Adresse IP de la PS5 sur le réseau local. Pensez à fixer un bail DHCP statique.}}"></i></sup>
									</label>
									<div class="col-sm-6">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="ip" placeholder="192.168.1.XX"/>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-offset-1 col-sm-10">
										<div class="alert alert-success" style="padding:8px;">
											<i class="fas fa-check-circle"></i> {{L'adresse IP suffit pour la détection d'état (allumée / veille / éteinte). Cette partie est entièrement locale et ne demande aucune configuration supplémentaire.}}
										</div>
									</div>
								</div>

								<legend><i class="fas fa-power-off"></i> {{Réveil et mise en veille}}</legend>
								<div class="form-group">
									<div class="col-sm-offset-1 col-sm-10">
										<div class="alert alert-warning" style="padding:8px;">
											<b><i class="fas fa-exclamation-triangle"></i> {{Une installation en SSH est nécessaire}}</b><br/><br/>
											{{Les commandes "Réveiller" et "Mettre en veille" exigent un appairage préalable entre Jeedom et votre console, réalisé auprès des serveurs PlayStation.}}<br/><br/>
											{{Cet appairage passe par une authentification dans un navigateur : il ne peut pas être automatisé et doit être effectué manuellement, en ligne de commande sur votre Jeedom (accès SSH requis). L'opération n'est à faire qu'une seule fois.}}<br/><br/>
											{{Tant que l'appairage n'est pas réalisé, ces deux commandes resteront sans effet. Le reste du plugin fonctionne normalement.}}<br/><br/>
											<i class="fas fa-book"></i> {{La procédure complète est décrite dans la documentation du plugin.}}
										</div>
									</div>
								</div>

								<legend><i class="fas fa-gamepad"></i> {{Jeu en cours (optionnel)}}</legend>
								<div class="form-group">
									<div class="col-sm-offset-1 col-sm-10">
										<div class="alert alert-info" style="padding:8px;">
											{{Les firmwares PS5 récents ne diffusent plus le nom du jeu sur le réseau local : seule l'API de présence PlayStation le fournit.}}<br/>
											{{Ce champ est facultatif. Laissé vide, le plugin reste entièrement local et la commande "Application en cours" reste vide.}}<br/><br/>
											<i class="fas fa-info-circle"></i> {{Le jeu est remonté quel que soit l'écran utilisé : téléviseur ou Remote Play (PlayStation Portal, smartphone, PC). En revanche, il n'est pas possible de savoir sur quel appareil la partie est jouée : Sony n'expose pas cette information.}}
										</div>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{Jeton npsso}}
										<sup><i class="fas fa-question-circle tooltips" title="{{Jeton de session PSN. Connectez-vous à votre compte sur playstation.com, puis ouvrez https://ca.account.sony.com/api/v1/ssocookie dans le même navigateur : copiez uniquement la valeur du champ npsso (64 caractères). Ce jeton expire au bout de quelques semaines et devra être renouvelé.}}"></i></sup>
									</label>
									<div class="col-sm-6">
										<input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="npsso" placeholder="{{Optionnel : requis pour afficher le jeu en cours}}"/>
									</div>
								</div>

								<legend><i class="fas fa-history"></i> {{Héritage}}</legend>
								<div class="form-group">
									<label class="col-sm-4 control-label">{{User-credential}}
										<sup><i class="fas fa-question-circle tooltips" title="{{Ancienne méthode de réveil, conservée pour les installations existantes. Ce champ n'est plus nécessaire : depuis l'appairage à pyremoteplay, le réveil fonctionne sans lui. Laissez-le vide.}}"></i></sup>
									</label>
									<div class="col-sm-6">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="credential" placeholder="{{Obsolète : laisser vide}}"/>
									</div>
								</div>

								<div class="form-group">
									<div class="col-sm-offset-4 col-sm-6">
										<a class="btn btn-info" id="bt_refreshInfo"><i class="fas fa-sync"></i> {{Tester / Rafraîchir maintenant}}</a>
									</div>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
				<div role="tabpanel" class="tab-pane" id="commandtab">
					<br/>
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
								<th style="min-width:200px;width:350px;">{{Nom}}</th>
								<th>{{Type}}</th>
								<th style="min-width:80px;width:200px;">{{État}}</th>
								<th style="min-width:80px;width:200px;">{{Options}}</th>
								<th style="min-width:80px;width:200px;">{{Actions}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'ps5', 'js', 'ps5'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
