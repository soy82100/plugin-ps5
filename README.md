# Plugin PS5 pour Jeedom

Supervision et contrôle d'une PlayStation 5 depuis Jeedom :

- État de la console (allumée / veille / éteinte) via le protocole DDP (UDP 9302), sans dépendance
- Application / jeu en cours
- Réveil à distance (nécessite un user-credential, récupérable avec playactor)
- Mise en veille (via playactor)

## Installation depuis GitHub

Dans Jeedom : Plugins > Gestion des plugins > Ajouter > onglet GitHub
- Repository : plugin-ps5
- Branche : main
- ID du plugin : ps5

Après installation : activer le plugin, activer le cron dans sa configuration, créer un équipement avec l'adresse IP de la PS5.

Sur la console, activer « Rester connecté à Internet » dans les fonctionnalités du mode repos.
