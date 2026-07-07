# Plugin PS5

Ce plugin permet de superviser et de contrôler une console **PlayStation 5** depuis Jeedom, entièrement en local, sans passerelle propriétaire ni service cloud.

## Fonctionnalités

- **État de la console** : Allumée / Veille / Éteinte (commande texte + commande binaire historisée, idéale pour les scénarios)
- **Application en cours** : nom du jeu ou de l'application en cours d'exécution
- **Réveiller** : sortie de veille à distance
- **Mettre en veille** : passage en mode repos à distance
- **Rafraîchissement automatique** de l'état chaque minute via le cron du plugin

## Comment ça marche ?

La PS5 répond nativement au protocole de découverte de Sony (*Device Discovery Protocol*, port UDP 9302). Le plugin interroge directement la console en PHP :

- La récupération de l'état et de l'application en cours ne nécessite **aucune dépendance**.
- Le **réveil** nécessite un identifiant lié à votre compte PSN (*user-credential*), à récupérer une seule fois (voir plus bas).
- La **mise en veille** nécessite l'outil externe [playactor](https://github.com/dhleong/playactor) (Node.js), car elle passe par une session chiffrée avec la console.

## Prérequis

- Jeedom 4.2 ou supérieur
- Une PS5 sur le même réseau local que Jeedom
- **Sur la console** : Paramètres > Système > Économie d'énergie > Fonctionnalités disponibles en mode repos > cocher **« Rester connecté à Internet »**. Sans cela, la console ne répond plus au réseau en veille et apparaîtra « Éteinte / injoignable ».
- Fortement recommandé : attribuer une **IP fixe** à la PS5 (bail DHCP statique sur votre box/routeur)

## Installation

Le plugin s'installe depuis GitHub :

1. Activer la source GitHub : **Réglages > Système > Configuration > onglet « Mises à jour/Market »** > section GitHub > activer, puis sauvegarder (aucun token nécessaire, le dépôt est public)
2. **Plugins > Gestion des plugins > Ajouter**, type de source **Github** :
   - ID logique du plugin : `ps5`
   - Utilisateur ou organisation du dépôt : `soy82100`
   - Nom du dépôt : `plugin-ps5`
   - Branche : `main`
3. Sauvegarder : le plugin s'installe
4. Dans **Plugins > Gestion des plugins**, cliquer sur le plugin **PS5** puis **Activer**

## Configuration du plugin

Sur la page du plugin (**Plugins > Gestion des plugins > PS5**) :

- **Cron** : activer le cron du plugin pour que l'état de la console soit rafraîchi automatiquement toutes les minutes.
- **Chemin vers playactor** (optionnel) : uniquement nécessaire pour la commande « Mettre en veille ». Par défaut : `/usr/local/bin/playactor`.

## Création d'un équipement

Aller dans **Plugins > Multimédia > PS5** puis **Ajouter** :

- **Nom** : ex. « PS5 Salon »
- **Objet parent** : la pièce où se trouve la console
- **Activer** et **Visible** : cocher les deux
- **Adresse IP** : l'adresse IP locale de la PS5
- **User-credential** (optionnel) : requis uniquement pour la commande « Réveiller » (voir section suivante)

Sauvegarder : les commandes sont créées automatiquement. Le bouton **« Tester / Rafraîchir maintenant »** permet de vérifier immédiatement la communication avec la console.

### Commandes créées

| Commande | Type | Description |
|---|---|---|
| Allumée | Info / binaire | 1 si la console est allumée, 0 sinon (historisée) |
| État | Info / texte | Allumée / Veille / Éteinte ou injoignable |
| Application en cours | Info / texte | Nom du jeu ou de l'application, « Menu d'accueil » sinon |
| Rafraîchir | Action | Force une interrogation immédiate de la console |
| Réveiller | Action | Sort la console de veille (nécessite le user-credential) |
| Mettre en veille | Action | Passe la console en mode repos (nécessite playactor) |

## Récupérer le user-credential (pour le réveil)

Le réveil à distance nécessite un identifiant de 64 caractères hexadécimaux lié à votre compte PSN. Il se récupère **une seule fois** avec l'outil playactor, depuis la machine Jeedom (ou n'importe quelle machine du réseau) :

```bash
# Installer Node.js si nécessaire, puis :
sudo npm install -g playactor

# Lancer la procédure de connexion (console allumée) :
playactor login --ip <IP_DE_LA_PS5>
```

Suivre les instructions : playactor affiche un lien d'authentification PSN à ouvrir dans un navigateur ; après connexion à votre compte, coller l'URL de redirection dans le terminal. Une étape d'appairage avec un code PIN affiché à l'écran de la console peut également être demandée (Paramètres > Système > Connexion à distance).

Le credential se trouve ensuite dans le fichier :

```bash
cat ~/.config/playactor/credentials.json
```

Copier la valeur du champ `user-credential` dans le champ correspondant de l'équipement Jeedom.

## Mise en veille (playactor)

La commande « Mettre en veille » s'appuie sur playactor (voir installation ci-dessus, à faire sur la **machine Jeedom** avec l'utilisateur `www-data` ou avec des credentials accessibles à celui-ci). Renseigner le chemin du binaire dans la configuration du plugin si différent de `/usr/local/bin/playactor` (vérifiable avec `which playactor`).

## Exemples d'utilisation en scénario

- **Notification de session de jeu** : déclencheur sur la commande `Allumée` (passage à 1) → notification « La PS5 vient d'être allumée » avec le nom du jeu via la commande `Application en cours`.
- **Extinction automatique** : si `Allumée` = 1 après une certaine heure → commande « Mettre en veille ».
- **Suivi du temps de jeu** : la commande binaire `Allumée` étant historisée, l'historique permet de visualiser les plages d'utilisation de la console.
- **Ambiance gaming** : à l'allumage de la console, baisser les volets et tamiser les lumières du salon.

## Dépannage

**La console apparaît « Éteinte / injoignable » alors qu'elle est en veille**
→ Vérifier sur la console que « Rester connecté à Internet » est bien activé dans les fonctionnalités du mode repos.

**La console apparaît « Éteinte / injoignable » alors qu'elle est allumée**
→ Vérifier l'adresse IP dans la configuration de l'équipement. Vérifier que Jeedom et la PS5 sont sur le même réseau/VLAN (le protocole utilise de l'UDP unicast, un pare-feu inter-VLAN peut le bloquer). Test manuel possible depuis la machine Jeedom :

```bash
echo -e "SRCH * HTTP/1.1\ndevice-discovery-protocol-version:00030010" | nc -u -w2 <IP_PS5> 9302
```

Une console joignable répond `HTTP/1.1 200 Ok` (allumée) ou `HTTP/1.1 620 Server Standby` (veille).

**La commande « Réveiller » ne fonctionne pas**
→ Vérifier que le user-credential est renseigné dans l'équipement et qu'il provient bien d'un compte connecté sur cette console. La console doit être en veille « connectée » (voir ci-dessus), pas complètement éteinte.

**La commande « Mettre en veille » échoue**
→ Vérifier le chemin de playactor dans la configuration du plugin, et que l'appairage playactor a été effectué. Consulter le log `ps5` (Analyse > Logs) en niveau Debug pour le détail de l'erreur.

**Rien ne se met à jour automatiquement**
→ Vérifier que le cron du plugin est activé sur sa page de configuration, et que le moteur de tâches Jeedom fonctionne (Réglages > Système > Moteur de tâches).

## FAQ

**Le plugin fonctionne-t-il avec une PS4 ?**
Non, pas en l'état : la PS4 utilise le même protocole mais sur le port UDP 987 avec une version différente. Une évolution du plugin est envisageable.

**Le plugin communique-t-il avec des serveurs Sony ?**
Non. Toutes les requêtes d'état et de réveil sont locales (LAN). Seule la récupération initiale du user-credential (via playactor) passe par une authentification PSN.

**Combien de consoles peut-on ajouter ?**
Autant que souhaité : un équipement par console.
