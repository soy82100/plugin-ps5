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

Le réveil à distance nécessite un identifiant lié à votre compte PSN (le *user-credential*). Il se récupère **une seule fois** avec l'outil playactor.

⚠️ **Important : effectuer cette procédure depuis un ordinateur disposant d'un navigateur web** (votre Mac/PC, sur le même réseau que la console) — pas depuis une VM Jeedom sans interface graphique : playactor a besoin d'ouvrir une page de connexion PSN et se termine silencieusement s'il ne trouve pas de navigateur. Le credential obtenu est lié à votre compte, pas à la machine : il fonctionnera dans Jeedom quelle que soit la machine utilisée pour le récupérer.

**Préparation côté console** (allumée, sur le profil du compte PSN que vous allez utiliser) :
- Paramètres > Système > Lecture à distance > **Activer la lecture à distance**

**Sur l'ordinateur :**

```bash
# Installer Node.js si nécessaire (nodejs.org), puis :
sudo npm install -g playactor

# Lancer la procédure de connexion :
playactor login --ip <IP_DE_LA_PS5>
```

Déroulé :

1. playactor ouvre le navigateur (ou affiche un lien) vers la page de connexion PSN : connectez-vous avec le compte utilisé sur la console
2. Après connexion, le navigateur affiche une page vide ou en erreur : **c'est normal**. Copiez l'**URL complète de la barre d'adresse** (elle contient `redirect?code=...`) et collez-la dans le terminal, à l'invite de playactor
3. playactor demande ensuite un **PIN** : sur la console, Paramètres > Système > Lecture à distance > **Appairer le périphérique** → saisir le code à 8 chiffres affiché à l'écran, sans tarder

Le credential se trouve ensuite dans le fichier :

```bash
cat ~/.config/playactor/credentials.json
```

Copier la valeur du champ `user-credential` dans le champ correspondant de l'équipement Jeedom. ℹ️ Sur PS5, il s'agit généralement d'un **identifiant numérique court** (une dizaine de chiffres), et non d'une chaîne hexadécimale de 64 caractères comme sur PS4 — les deux formats sont acceptés par le plugin.

⚠️ Ne partagez pas le contenu de `credentials.json` (forum, capture d'écran...) : il contient aussi vos clés d'appairage locales (RP-Key, RegistKey).

**En cas d'erreur `Registration error: 403: Forbidden`** lors de l'appairage :

1. Vérifier que le profil actif sur la console est bien celui du compte PSN utilisé pour le login
2. Réinitialiser l'état de playactor et recommencer la procédure complète :

```bash
rm -rf ~/.config/playactor
playactor login --ip <IP_DE_LA_PS5>
```

3. Si l'erreur persiste, redémarrer complètement la console (menu alimentation > Redémarrer, pas le mode repos) et réessayer

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
→ Vérifier que le user-credential est renseigné dans l'équipement et qu'il provient bien d'un compte connecté sur cette console (voir la section dédiée, y compris la procédure en cas d'erreur 403 lors de sa récupération). La console doit être en veille « connectée » (voir ci-dessus), pas complètement éteinte.

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
