# Plugin PS5

Ce plugin permet de superviser et de contrôler une console **PlayStation 5** depuis Jeedom.

## Fonctionnalités

| Fonction | Source | Installation requise |
|---|---|---|
| **État** : Allumée / Veille / Éteinte | locale | aucune |
| **Réveiller** | locale | appairage PSN (une fois, en SSH) |
| **Mettre en veille** | locale | appairage PSN (une fois, en SSH) |
| **Jeu en cours** + **jaquette** | API PlayStation | jeton npsso (optionnel) |
| **Widget dashboard** animé | — | aucune |

L'**état** est récupéré directement sur le réseau local, sans aucune dépendance.

Le **réveil** et la **mise en veille** passent par le protocole Remote Play de Sony :
ils nécessitent que Jeedom soit appairé à la console, exactement comme le serait
une manette. Cet appairage se fait **une seule fois**, en ligne de commande.

Le **jeu en cours** est facultatif : les firmwares PS5 récents ne diffusent plus
cette information sur le réseau local (voir plus bas). Sans jeton, le plugin reste
entièrement local.

## Comment ça marche ?

La PS5 répond nativement au protocole de découverte de Sony (*Device Discovery
Protocol*, port UDP 9302). Le plugin l'interroge directement en PHP pour connaître
son état.

Le réveil et la mise en veille utilisent la bibliothèque Python **pyremoteplay**,
appelée par `resources/ps5_cli.py`.

> **Note** : les versions antérieures du plugin s'appuyaient sur l'outil
> [playactor](https://github.com/dhleong/playactor). Ce projet n'est plus maintenu
> depuis 2022 et son enregistrement échoue désormais avec une erreur
> `403 Forbidden` sur les firmwares PS5 récents. Il a été entièrement remplacé.
> Le champ « Chemin vers playactor » et le « user-credential » ne sont plus utilisés.

## Prérequis

- Jeedom 4.2 ou supérieur
- Une PS5 sur le même réseau local que Jeedom
- **Sur la console** : Paramètres > Système > Économie d'énergie > Fonctionnalités
  disponibles en mode repos > cocher **« Rester connecté à Internet »**.
  Sans cela, la console ne répond plus au réseau en veille et apparaîtra
  « Éteinte / injoignable ».
- **Sur la console** : Paramètres > Système > Lecture à distance >
  **Activer la lecture à distance**
- Fortement recommandé : attribuer une **IP fixe** à la PS5 (bail DHCP statique)

## Installation du plugin

1. Activer la source GitHub : **Réglages > Système > Configuration > onglet
   « Mises à jour/Market »** > section GitHub > activer, puis sauvegarder
   (aucun token nécessaire, le dépôt est public)
2. **Plugins > Gestion des plugins > Ajouter**, type de source **Github** :
   - ID logique du plugin : `ps5`
   - Utilisateur ou organisation : `soy82100`
   - Nom du dépôt : `plugin-ps5`
   - Branche : `beta`
3. Sauvegarder, puis **Activer** le plugin
4. Sur la page du plugin, activer le **cron** (rafraîchissement automatique)

## Création d'un équipement

**Plugins > Multimédia > PS5 > Ajouter** :

- **Nom** : ex. « PS5 Salon »
- **Objet parent** : la pièce où se trouve la console
- **Adresse IP** : l'adresse IP locale de la PS5
- **Jeton npsso** *(optionnel)* : pour afficher le jeu en cours (voir plus bas)

Sauvegarder : les commandes sont créées automatiquement. Le bouton
**« Tester / Rafraîchir maintenant »** vérifie immédiatement la communication.

### Commandes créées

| Commande | Type | Description |
|---|---|---|
| Allumée | Info / binaire | 1 si la console est allumée, 0 sinon (historisée) |
| État | Info / texte | Allumée / Veille / Éteinte ou injoignable |
| Application en cours | Info / texte | Nom du jeu (nécessite le jeton npsso) |
| Jaquette | Info / texte | URL de l'image du jeu (nécessite le jeton npsso) |
| Rafraîchir | Action | Force une interrogation immédiate |
| Réveiller | Action | Sort la console de veille |
| Mettre en veille | Action | Passe la console en mode repos |

---

## Réveil et mise en veille : installation

Ces deux commandes nécessitent la bibliothèque **pyremoteplay** et un **appairage**
à votre compte PSN. L'appairage passe par une connexion via navigateur : il ne peut
pas être automatisé, et se fait **une seule fois**. Il est ensuite permanent — il
survit aux redémarrages et aux mises à jour du plugin.

### 1. Installer les bibliothèques

En SSH sur la machine Jeedom, en root (`su -`) :

```bash
sudo apt install -y python3-venv

sudo python3 -m venv /var/www/html/plugins/ps5/resources/python_venv

sudo /var/www/html/plugins/ps5/resources/python_venv/bin/python3 -m pip install \
    "pyee==9.1.1" async_timeout pyremoteplay PSNAWP

sudo chown -R www-data:www-data /var/www/html/plugins/ps5/resources/python_venv
```

> **Important** : installer les paquets **en une seule commande**.
> `pyremoteplay` n'est pas compatible avec `pyee` version 10 ou supérieure ;
> les installer séparément écraserait la version correcte.

Vérification (l'avertissement `av not installed` est normal) :

```bash
/var/www/html/plugins/ps5/resources/python_venv/bin/python3 -c "import pyremoteplay; print('OK')"
```

### 2. Appairer la console

**Console allumée** (le mode veille ne suffit pas pour l'appairage initial) :

```bash
su -s /bin/bash -c "HOME=/var/www \
  /var/www/html/plugins/ps5/resources/python_venv/bin/python3 \
  -m pyremoteplay --register 192.168.1.XX" www-data
```

> La commande doit être lancée en tant que **`www-data`**, l'utilisateur sous lequel
> tourne Jeedom. C'est ce qui garantit que le plugin retrouvera l'appairage.
> Ne la lancez pas directement en root.

**Déroulé :**

1. La commande affiche une URL commençant par
   `https://auth.api.sonyentertainmentnetwork.com/...`
   Copiez-la et ouvrez-la dans le navigateur de votre ordinateur.
2. Connectez-vous à votre compte PSN — **celui qui est connecté sur la console**.
3. La page va sembler **se bloquer ou rester blanche** sur une adresse contenant le
   mot `redirect`. **C'est normal, ne fermez pas l'onglet.**
4. Copiez l'**intégralité** de l'adresse affichée dans la barre d'adresse
   (elle contient `?code=...`) et collez-la au prompt `Enter Redirect URL >`.

   > ⚠️ À cette étape, le terminal attend l'**URL**, pas un code à 8 chiffres.
   > Cette URL contient un jeton lié à votre compte : ne la partagez avec personne.

5. Le terminal demande alors un code d'appairage. Sur la console :
   *Paramètres > Système > Lecture à distance > **Associer un appareil***.
   Saisissez le code à 8 chiffres **immédiatement** — il expire très vite.

### 3. Vérifier

```bash
ls -l /var/www/.pyremoteplay/
```

Le fichier `.profile.json` doit être présent. Les boutons **Réveiller** et
**Mettre en veille** fonctionnent désormais depuis le dashboard.

Ces commandes prennent une dizaine de secondes : elles établissent une session
Remote Play, envoient l'ordre, puis vérifient que la console a bien changé d'état.

---

## Jeu en cours (optionnel)

Les firmwares PS5 récents **ne diffusent plus le nom du jeu** dans leur réponse
réseau : le champ `running-app-name` reste vide, même console allumée et jeu lancé.
L'information n'est donc **pas récupérable en local**.

Seule l'**API de présence PlayStation** la fournit — la même qui affiche
« joue à … » sous votre pseudo pour vos amis. Cette fonction est donc **facultative**
et **désactivée par défaut** : sans jeton, le plugin reste entièrement local.

### Récupérer le jeton npsso

1. Connectez-vous à votre compte sur **playstation.com**
2. Dans le **même navigateur**, ouvrez :
   `https://ca.account.sony.com/api/v1/ssocookie`
3. Vous obtenez un JSON du type `{"npsso":"xxxxx...","expires_in":...}`
4. Copiez **uniquement la valeur du champ `npsso`** (64 caractères), sans les
   accolades ni le reste
5. Collez-la dans le champ **Jeton npsso** de l'équipement, puis sauvegardez

Les commandes **Application en cours** et **Jaquette** se remplissent alors
automatiquement lorsque la console est allumée.

> ⚠️ **Ce jeton expire** au bout de quelques semaines. Lorsque c'est le cas, le log
> du plugin affiche `Your npsso code has expired or is incorrect` : il suffit d'en
> générer un nouveau et de le recoller.
>
> Ce jeton donne accès à votre compte PSN : ne le partagez pas.

---

## Widget du dashboard

Le plugin fournit un **widget graphique** : la console y est représentée avec un
témoin lumineux dont la couleur suit l'état (bleu clignotant allumée, orange en
veille, gris éteinte), accompagné de l'état, du jeu en cours, de la durée de session
et des boutons Réveiller / Mettre en veille / Actualiser.

**Pour l'activer** : sur l'équipement, **Configuration avancée** (icône engrenages)
> onglet **Informations** > ligne **Options** > cocher **« Template de widget »**,
puis **Sauvegarder**.

Si la case n'est pas cochée, Jeedom affiche le rendu standard (liste des commandes),
ce qui reste parfaitement fonctionnel.

Le widget est également utilisable dans le **module Design** : il peut y être déplacé,
redimensionné et paramétré par clic droit comme n'importe quel équipement. Pensez à
l'**agrandir** après l'avoir ajouté — le Design attribue une taille réduite par défaut
aux nouveaux éléments.

---

## Cas particulier : la PlayStation Portal

La **PlayStation Portal** est un client de Lecture à distance : elle se contente
d'afficher le flux d'une console. Elle ne diffuse aucune information sur le réseau et
n'expose aucun service interrogeable — elle **n'est donc pas gérée** par ce plugin, et
ne peut pas l'être.

L'équipement doit être créé avec l'adresse IP de la **console PS5**, jamais celle de
la Portal. Lorsque vous jouez via la Portal, la console est allumée : le plugin la
verra donc bien comme « Allumée ».

---

## Exemples d'utilisation en scénario

- **Notification de session de jeu** : déclencheur sur `Allumée` (passage à 1) →
  notification avec le nom du jeu via `Application en cours`.
- **Extinction automatique** : si `Allumée` = 1 après une certaine heure →
  commande « Mettre en veille ».
- **Suivi du temps de jeu** : la commande binaire `Allumée` étant historisée,
  l'historique permet de visualiser les plages d'utilisation.
- **Ambiance gaming** : à l'allumage de la console, baisser les volets et tamiser les
  lumières du salon.

---

## Dépannage

**La console apparaît « Éteinte / injoignable » alors qu'elle est en veille**
→ Vérifier que « Rester connecté à Internet » est activé dans les fonctionnalités du
mode repos.

**La console apparaît « Éteinte / injoignable » alors qu'elle est allumée**
→ Vérifier l'adresse IP. Vérifier que Jeedom et la PS5 sont sur le même réseau/VLAN
(le protocole utilise de l'UDP unicast, un pare-feu inter-VLAN peut le bloquer).
Test manuel depuis la machine Jeedom :

```bash
echo -e "SRCH * HTTP/1.1\ndevice-discovery-protocol-version:00030010" | nc -u -w2 <IP_PS5> 9302
```

Une console joignable répond `HTTP/1.1 200 Ok` (allumée) ou
`HTTP/1.1 620 Server Standby` (veille).

**Réveil ou veille : « pyremoteplay absent »**
→ La bibliothèque n'est pas installée. Reprendre l'étape 1.

**Réveil ou veille : « aucun compte PSN appairé »**
→ L'appairage n'a pas été fait, ou il a été fait en root. Le refaire **en `www-data`**,
avec la commande exacte de l'étape 2.

**Appairage : `must be awake for initial registration`**
→ La console doit être **complètement allumée**, pas en veille.

**Appairage : `403 Forbidden`**
→ Vérifier que la Lecture à distance est activée sur la console, et que le compte PSN
utilisé pour la connexion est bien celui connecté sur la console.

**Appairage : `Invalid URL`**
→ Le code PIN a été saisi à la place de l'URL. Reprendre à l'étape du redirect.

**« Application en cours » reste vide**
→ Normal si aucun jeton npsso n'est configuré : cette information n'est pas disponible
en local. Voir la section « Jeu en cours ».

**Le log affiche `Your npsso code has expired or is incorrect`**
→ Le jeton a expiré. En générer un nouveau et le recoller dans l'équipement.

**Le widget graphique ne s'affiche pas (rendu standard à la place)**
→ Configuration avancée > Informations > Options > cocher **« Template de widget »**,
sauvegarder, puis recharger le dashboard.

**Rien ne se met à jour automatiquement**
→ Vérifier que le cron du plugin est activé, et que le moteur de tâches Jeedom
fonctionne (Réglages > Système > Moteur de tâches).

Les logs (**Analyse > Logs > `ps5`**, niveau **Debug**) contiennent le détail des
commandes exécutées et des erreurs retournées.

---

## FAQ

**Le plugin fonctionne-t-il avec une PS4 ?**
Non, pas en l'état : la PS4 utilise le même protocole mais sur le port UDP 987 avec une
version différente. Une évolution est envisageable.

**Le plugin communique-t-il avec des serveurs Sony ?**
L'état, le réveil et la mise en veille sont **entièrement locaux** (LAN). Seule la
récupération du jeu en cours, **optionnelle**, interroge l'API PlayStation — et
uniquement si un jeton npsso est configuré.

**Faut-il encore un « user-credential » ?**
Non. Il n'est plus nécessaire depuis l'appairage pyremoteplay, et il n'est de toute
façon plus récupérable (playactor échouant à l'appairage). Le champ est conservé pour
les installations existantes ; laissez-le vide.

**Combien de consoles peut-on ajouter ?**
Autant que souhaité : un équipement par console.

---

## Licence

AGPL — voir le fichier de licence du dépôt.
