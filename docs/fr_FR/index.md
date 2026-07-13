# Plugin PS5

Supervision et contrôle d'une PlayStation 5 depuis Jeedom : état de la console
(allumée / veille / éteinte), application en cours, réveil à distance et mise en
veille.

---

## Fonctionnalités

| Fonction | Dépendance | Configuration requise |
|---|---|---|
| État de la console | aucune | adresse IP |
| Application en cours | aucune | adresse IP |
| Réveil (Wake-on-LAN) | aucune | user-credential |
| Mise en veille | pyremoteplay | appairage PSN (une fois, en SSH) |

L'état, l'application en cours et le réveil fonctionnent **sans aucune
installation supplémentaire** : ils utilisent le protocole DDP de Sony,
implémenté directement en PHP.

Seule la **mise en veille** nécessite une installation complémentaire, décrite
plus bas.

---

## Configuration du plugin

Une seule option :

- **Intervalle de rafraîchissement (minutes)** — fréquence d'interrogation de la
  console. Valeur par défaut : 1 minute.

Pensez à **activer le plugin** après son installation.

---

## Création d'un équipement

Créez un équipement et renseignez :

- **Adresse IP** : l'adresse IP de votre **console PS5**.
  Attribuez-lui une IP fixe dans votre box ou routeur : si elle change, le plugin
  ne la trouvera plus.

- **User-credential** : nécessaire uniquement pour le **réveil à distance**
  (voir la section dédiée ci-dessous).

Après sauvegarde, les commandes suivantes sont créées automatiquement :

| Commande | Type | Description |
|---|---|---|
| `refresh` | action | Force l'interrogation de la console |
| `online` | info binaire | 1 = allumée, 0 = veille ou éteinte |
| `etat` | info texte | « Allumée », « Veille », « Éteinte / injoignable » |
| `application` | info texte | Jeu ou application en cours |
| `wake` | action | Réveille la console |
| `standby` | action | Met la console en veille |

---

## Réveil à distance : récupérer le user-credential

Le paquet de réveil doit être signé par un identifiant lié à votre compte PSN.
Cet identifiant se récupère une seule fois.

Il correspond au champ `user-credential` utilisé par l'application PlayStation
lorsqu'elle réveille la console. Reportez-vous à la section correspondante de
cette documentation pour la procédure de récupération, puis collez la valeur dans
le champ de configuration de l'équipement.

Sans user-credential, la commande **Réveiller** renverra une erreur explicite
dans les logs. Les autres fonctions ne sont pas affectées.

---

## Mise en veille : installation et appairage

### Pourquoi une manipulation manuelle ?

La mise en veille passe par le protocole **Remote Play** de Sony, qui exige que
Jeedom soit *appairé* à la console — exactement comme le serait une manette ou
l'application PlayStation.

Cet appairage nécessite une connexion à votre compte PSN **via un navigateur**.
Il ne peut donc pas être automatisé, et doit être réalisé **une seule fois**, en
ligne de commande. Une fois effectué, il est permanent : il survit aux
redémarrages et aux mises à jour du plugin.

> **Note technique** : les versions précédentes du plugin utilisaient l'outil
> `playactor`. Ce projet n'est plus maintenu depuis 2022 et son enregistrement
> échoue désormais avec une erreur `403 Forbidden` sur les firmwares PS5 récents.
> Il a été remplacé par la bibliothèque Python `pyremoteplay`. Le champ
> « Chemin vers playactor » a disparu de la configuration : il n'est plus utile.

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
    "pyee==9.1.1" async_timeout pyremoteplay

sudo chown -R www-data:www-data /var/www/html/plugins/ps5/resources/python_venv
```

> **Important** : les trois paquets doivent être installés **en une seule
> commande**. `pyremoteplay` n'est pas compatible avec `pyee` version 10 ou
> supérieure ; les installer séparément entraînerait l'écrasement de la version
> correcte.

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

Le fichier `.profile.json` doit être présent. L'appairage est terminé : le bouton
**Mettre en veille** fonctionne désormais depuis le dashboard.

La commande prend une dizaine de secondes à s'exécuter : elle établit une session
Remote Play, envoie l'ordre, puis vérifie que la console est bien passée en
veille.

---

## Widget de dashboard

Le plugin fournit un widget personnalisé : console PS5 stylisée, témoin lumineux
qui change de couleur selon l'état (bleu clignotant = allumée, orange = veille,
gris = éteinte), application en cours, durée de session et boutons d'action.

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

Lorsque vous jouez via la Portal, la console est allumée : le plugin la verra donc
normalement comme « Allumée ».

---

## Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| État toujours « Éteinte / injoignable » | mauvaise IP, ou console sur un autre réseau | vérifiez l'IP, et que Jeedom et la console sont sur le même réseau |
| L'IP change régulièrement | pas de bail statique | attribuez une IP fixe à la console dans votre box |
| Le réveil ne fait rien | user-credential absent ou incorrect | voir la section « Réveil à distance » |
| Veille : « pyremoteplay absent » | bibliothèque non installée | reprendre l'étape 1 |
| Veille : « aucun compte PSN appairé » | appairage absent, ou fait en root | refaire l'étape 2 **en `www-data`** |
| Appairage : `must be awake for initial registration` | console en veille | allumez-la complètement |
| Appairage : `403 Forbidden` | Lecture à distance désactivée, ou mauvais compte PSN | vérifiez le réglage sur la console et le compte utilisé |
| Appairage : `Invalid URL` | code PIN saisi au lieu de l'URL | reprendre l'étape 3 |

Les logs du plugin (**Analyse → Logs → `ps5`**) contiennent le détail des
commandes exécutées et des erreurs retournées. Passez le niveau de log en
**Debug** dans la configuration du plugin pour un diagnostic complet.
