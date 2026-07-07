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
					echo '<i class="fab fa-playstation" style="font-size:4em;"></i>';
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
									<label class="col-sm-4 control-label">{{User-credential}}
										<sup><i class="fas fa-question-circle tooltips" title="{{Nécessaire uniquement pour la commande Réveiller. Chaîne hexadécimale de 64 caractères, à récupérer une fois avec playactor (voir documentation).}}"></i></sup>
									</label>
									<div class="col-sm-6">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="credential" placeholder="{{Optionnel : requis pour le réveil à distance}}"/>
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
