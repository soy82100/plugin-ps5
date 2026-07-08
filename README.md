# Plugin PS5 pour Jeedom

Supervision et contrôle d'une **PlayStation 5** depuis Jeedom, 100 % en local, sans passerelle ni cloud.

> ✅ **Statut : stable.** Toutes les fonctionnalités (état, application en cours, réveil à distance) sont testées et validées sur console réelle. Les évolutions passent par la branche `beta` avant d'arriver en stable.

## Fonctionnalités

- 🟢 **État de la console** : Allumée / Veille / Éteinte (commande binaire historisée + commande texte)
- 🎮 **Application en cours** : nom du jeu ou de l'application qui tourne
- ⏰ **Réveil à distance** (nécessite un user-credential PSN, récupérable une fois avec [playactor](https://github.com/dhleong/playactor))
- 🌙 **Mise en veille** à distance (via playactor)
- 🔄 Rafraîchissement automatique chaque minute (cron du plugin)

L'état est récupéré via le protocole de découverte natif de Sony (UDP 9302), **sans aucune dépendance**.

## Installation

1. Dans Jeedom, activer la source GitHub : **Réglages > Système > Configuration > onglet Mises à jour/Market** > section GitHub > activer
2. **Plugins > Gestion des plugins > Ajouter**, type de source **Github** :

| Champ | Valeur |
|---|---|
| ID logique du plugin | `ps5` |
| Utilisateur ou organisation du dépôt | `soy82100` |
| Nom du dépôt | `plugin-ps5` |
| Branche | `main` |

3. Activer le plugin, puis activer son **cron** dans sa configuration
4. Créer un équipement (**Plugins > Multimédia > PS5**) avec l'adresse IP de la console

⚠️ **Sur la PS5** : activer *Paramètres > Système > Économie d'énergie > Fonctionnalités disponibles en mode repos > Rester connecté à Internet*, sinon la console sera injoignable en veille. Une IP fixe (bail DHCP statique) est fortement recommandée.

## Documentation complète

📖 [Documentation détaillée](docs/fr_FR/index.md) : configuration, récupération du user-credential, exemples de scénarios, dépannage, FAQ.

## Compatibilité

- Jeedom ≥ 4.2
- PlayStation 5 (toutes éditions). PS4 non supportée (port et version de protocole différents).

## Licence

AGPL
