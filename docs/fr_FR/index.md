# Plugin PS5

Supervision et contrôle d'une PlayStation 5 depuis Jeedom : état de la console
(allumée / veille / éteinte), jeu en cours, réveil à distance et mise en veille.

---

## Fonctionnalités

| Fonction | Méthode | Configuration requise |
|---|---|---|
| État de la console | locale (protocole DDP) | adresse IP |
| Réveil | locale (pyremoteplay) | appairage PSN (une fois, en SSH) |
| Mise en veille | locale (pyremoteplay) | appairage PSN (une fois, en SSH) |
| Jeu en cours + jaquette | API PlayStation (cloud) | jeton npsso — **optionnel** |

**L'état de la console fonctionne sans aucune installation supplémentaire** : il
utilise le protocole DDP de Sony, implémenté directement en PHP. L'adresse IP
suffit.

**Le réveil et la mise en veille nécessitent un appairage préalable**, à réaliser
une seule fois en SSH. La procédure est décrite plus bas.

**Le jeu en cours est optionnel** et repose sur l'API de présence PlayStation.
Sans jeton, le plugin reste entièrement local.

---

## Configuration du plugin

Une seule option :

- **Intervalle de rafraîchissement (minutes)** — fréquence d'interrogation de la
  console. Valeur par défaut : 1 minute.

Pensez à **activer le plugin** après son installation.

---

## Création d'un équipement

Créez un équipement et renseignez :

- **Adresse IP** : l'adresse IP de votre **console PS5** (jamais celle de la
  Portal — voir la section dédiée).
  Attribuez-lui une IP fixe dans votre box ou routeur : si elle change, le plugin
  ne la trouvera plus.

- **Jeton npsso** : optionnel, uniquement pour afficher le jeu en cours
  (voir la section dédiée).

- **User-credential** : **champ obsolète**, conservé pour les installations
  existantes. Laissez-le vide.

Après sauvegarde, les commandes suivantes sont créées automatiquement :

| Commande | Type | Description |
|---|---|---|
| `refresh` | action | Force l'interrogation de la console |
| `online` | info binaire | 1 = allumée, 0 = veille ou éteinte |
| `etat` | info texte | « Allumée », « Veille », « Éteinte / injoignable » |
| `application` | info texte | Jeu en cours (nécessite le jeton npsso) |
| `jaquette` | info texte | URL de la jaquette du jeu (invisible par défaut) |
| `wake` | action | Réveille la console |
| `standby` | action | Met la console en veille |

---

## Jeu en cours : le jeton npsso

### Pourquoi un jeton ?

Les firmwares PS5 récents ne diffusent plus le nom du jeu sur le réseau local :
le champ `running-app-name` du protocole DDP n'est plus renseigné. Cette
information n'est donc **plus récupérable localement**, quel que soit le plugin.

La seule source restante est l'**API de présence PlayStation**, qui exige de
s'authentifier auprès de Sony. C'est le rôle du jeton npsso.

Cette fonction est **entièrement optionnelle**. Sans jeton, le plugin ne
communique qu'avec votre console, sur votre réseau local.

### Récupérer le jeton

1. Dans un navigateur, connectez-vous sur **https://my.playstation.com** avec le
   compte PSN **utilisé sur la console**
2. Dans le **même navigateur**, ouvrez :
   `https://ca.account.sony.com/api/v1/ssocookie`
3. La page affiche un court texte de la forme `{"npsso":"xxxxxxxx..."}`
4. Copiez **uniquement** la valeur entre guillemets (64 caractères), sans les
   guillemets ni le reste
5. Collez-la dans le champ **Jeton npsso** de l'équipement, puis sauvegardez

> **Durée de vie** : ce jeton expire au bout de quelques semaines. Lorsque le jeu
> en cours cesse de remonter, régénérez-le en reprenant cette procédure. Les
> autres fonctions du plugin ne sont pas affectées par son expiration.

> **Confidentialité** : ce jeton donne accès à votre compte PSN. Ne le partagez
> avec personne, et ne le collez jamais dans un message public (forum, ticket,
> capture d'écran).

### Le jeu ne remonte pas ?

- **Aucun profil connecté sur la console.** Une PS5 allumée sur l'écran de
  sélection d'utilisateur est vue « Allumée » par le plugin, mais l'API PSN la
  considère hors ligne. Connectez un profil.
- **État en ligne masqué.** Si le compte est réglé sur « Ne pas afficher mon état
  en ligne » dans les paramètres de confidentialité PSN, l'API renverra toujours
  « hors ligne ».
- **Mauvais compte.** Le jeton doit être généré depuis le compte **connecté sur la
  console**, pas depuis un autre.
- **Jeton expiré.** Voir ci-dessus.

---

## Réveil et mise en veille : installation et appairage

### Pourquoi une manipulation manuelle ?

Le réveil et la mise en veille passent par le protocole **Remote Play** de Sony,
qui exige que Jeedom soit *appairé* à la console — exactement comme le serait une
manette ou l'application PlayStation.

Cet appairage nécessite une connexion à votre compte PSN **via un navigateur**.
Il ne peut donc pas être automatisé, et doit être réalisé **une seule fois**, en
ligne de commande. Une fois effectué, il est permanent : il survit aux
redémarrages et aux mises à jour du plugin.

Tant que l'appairage n'est pas réalisé, ces deux commandes restent sans effet.
Le reste du plugin fonctionne normalement.

> **Note technique** : les versions précédentes du plugin utilisaient l'outil
> `playactor`. Ce projet n'est plus maintenu depuis 2022 et son enregistrement
> échoue désormais avec une erreur `403 Forbidden` sur les firmwares PS5 récents.
> Il a été remplacé par la bibliothèque Python `pyremoteplay`. Le champ
> « User-credential » est devenu inutile : le réveil ne s'en sert plus.

### Prérequis

- Un accès **SSH** à votre Jeedom
- Votre **console allumée** — le mode veille ne suffit pas pour l'appairage initial
- Les identifiants du **compte PSN connecté sur la console**
- La **Lecture à distance activée** sur la PS5 :
  *Paramètres → Système → Lecture à distance → Activer la lecture à distance*

### Étape 1 — Installer pyremoteplay

Connectez-vous en SSH, passez root (`su -`), puis :

```bash
sudo apt install -y python3-venv

sudo python3 -m venv /var/www/html/plugins/ps5/resources/python_venv

sudo /var/www/html/plugins/ps5/resources/python_venv/bin/python3 -m pip install \
    "pyee==9.1.1" async_timeout pyremoteplay PSNAWP

sudo chown -R www-data:www-data /var/www/html/plugins/ps5/resources/python_venv
```

> **Important** : les paquets doivent être installés **en une seule commande**.
> `pyremoteplay` n'est pas compatible avec `pyee` version 10 ou supérieure ; les
> installer séparément entraînerait l'écrasement de la version correcte.

Vérification :

```bash
/var/www/html/plugins/ps5/resources/python_venv/bin/python3 -c "import pyremoteplay; print('OK')"
```

L'avertissement `av not installed` est normal et sans conséquence.

### Étape 2 — Appairer la console

**Console allumée**, lancez :

```bash
su -s /bin/bash -c "HOME=/var/www \
  /var/www/html/plugins/ps5/resources/python_venv/bin/python3 \
  -m pyremoteplay --register 192.168.1.XX" www-data
```

En remplaçant `192.168.1.XX` par l'IP de votre console.

> La commande doit être exécutée en tant que **`www-data`**, l'utilisateur sous
> lequel tourne Jeedom. C'est ce qui garantit que le plugin retrouvera
> l'appairage. Ne la lancez pas directement en root.

### Étape 3 — Se connecter au compte PSN

La commande affiche une longue URL commençant par
`https://auth.api.sonyentertainmentnetwork.com/...`

1. Copiez-la et ouvrez-la dans le navigateur de votre ordinateur
2. Connectez-vous à votre compte PSN

La page va ensuite sembler **se bloquer ou rester blanche** sur une adresse
contenant le mot `redirect`. **C'est normal, ne fermez pas l'onglet.**

3. Copiez l'**intégralité** de l'adresse affichée dans la barre d'adresse
   (elle contient `?code=...`)
4. Collez-la dans le terminal, au prompt `Enter Redirect URL >`

> **Piège fréquent** : à cette étape, le terminal attend l'**URL**, pas un code à
> 8 chiffres. Le code viendra à l'étape suivante.

> Cette URL contient un jeton lié à votre compte PSN : ne la partagez avec
> personne.

### Étape 4 — Saisir le code d'appairage

Le terminal affiche alors votre compte PSN et attend un code.

Sur la console : *Paramètres → Système → Lecture à distance → Associer un appareil*

Un **code à 8 chiffres** s'affiche. Saisissez-le immédiatement dans le terminal.

> Ce code expire très vite. Ne l'affichez qu'au moment où le terminal le réclame,
> et ne réutilisez jamais un code affiché précédemment.

### Étape 5 — Vérifier

```bash
ls -l /var/www/.pyremoteplay/
```

Le fichier `.profile.json` doit être présent, et appartenir à `www-data`.
L'appairage est terminé : les boutons **Réveiller** et **Mettre en veille**
fonctionnent désormais depuis le dashboard.

La mise en veille prend une dizaine de secondes à s'exécuter : elle établit une
session Remote Play, envoie l'ordre, puis vérifie que la console est bien passée
en veille.

---

## Widget de dashboard

Le plugin fournit un widget personnalisé : console PS5 stylisée, témoin lumineux
qui change de couleur selon l'état (bleu clignotant = allumée, orange = veille,
gris = éteinte), jeu en cours, durée de session et boutons d'action.

### Activation

Sur l'équipement : **Configuration avancée** → onglet **Informations** → ligne
**Options** → cocher **« Template de widget »**.

Sans cette option, Jeedom affiche le rendu standard.

### Utilisation dans le module Design

Le widget est pleinement compatible avec le module Design : il peut être
déplacé, redimensionné et paramétré par clic droit, comme n'importe quel
équipement.

À l'ajout, pensez à l'**agrandir** : le Design attribue une taille réduite par
défaut aux nouveaux éléments.

---

## Cas particulier : la PlayStation Portal

La **PlayStation Portal** est un client de Lecture à distance : elle se contente
d'afficher le flux d'une console. Elle ne diffuse aucune information sur le
réseau et n'expose aucun service interrogeable — elle **n'est donc pas gérée** par
ce plugin, et ne peut pas l'être.

L'équipement doit être créé avec l'adresse IP de la **console PS5**, jamais celle
de la Portal.

**Lorsque vous jouez via la Portal, tout fonctionne normalement** : la console est
allumée, le plugin la voit « Allumée », et le jeu en cours remonte comme si vous
jouiez sur le téléviseur.

En revanche, le plugin **ne peut pas savoir sur quel écran** la partie se joue.
Pour Sony, la Portal n'est pas un appareil de jeu mais un simple écran déporté :
l'information n'est exposée nulle part, ni sur le réseau local, ni via l'API de
présence. Une session sur Portal est indiscernable d'une session sur le
téléviseur. Il en va de même pour la Lecture à distance sur smartphone ou sur PC.

---

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| État toujours « Éteinte / injoignable » | mauvaise IP, ou console sur un autre réseau | vérifiez l'IP, et que Jeedom et la console sont sur le même réseau |
| L'IP change régulièrement | pas de bail statique | attribuez une IP fixe à la console dans votre box |
| Le réveil ou la veille ne fait rien | appairage non réalisé | voir « Réveil et mise en veille » |
| « pyremoteplay absent » | bibliothèque non installée | reprendre l'étape 1 |
| « aucun compte PSN appairé » | appairage absent, ou fait en root | refaire l'étape 2 **en `www-data`** |
| Appairage : `must be awake for initial registration` | console en veille | allumez-la complètement |
| Appairage : `403 Forbidden` | Lecture à distance désactivée, ou mauvais compte PSN | vérifiez le réglage sur la console et le compte utilisé |
| Appairage : `Invalid URL` | code PIN saisi au lieu de l'URL | reprendre l'étape 3 |
| Le jeu en cours reste vide | jeton npsso absent, expiré, ou aucun profil connecté sur la console | voir « Jeu en cours » |

Les logs du plugin (**Analyse → Logs → `ps5`**) contiennent le détail des
commandes exécutées et des erreurs retournées. Passez le niveau de log en
**Debug** dans la configuration du plugin pour un diagnostic complet.

> **Avant de publier un log sur le forum** : vérifiez qu'il ne contient ni votre
> jeton npsso, ni une URL d'appairage. Le mode Debug peut les faire apparaître.
