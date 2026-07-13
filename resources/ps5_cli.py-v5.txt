#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI PS5 pour le plugin Jeedom - via pyremoteplay
Remplace playactor (non maintenu depuis 2022, echec 403 a l'appairage sur les
firmwares PS5 recents).

Usage :
    ps5_cli.py status  --ip 192.168.1.XX
    ps5_cli.py wakeup  --ip 192.168.1.XX
    ps5_cli.py standby --ip 192.168.1.XX

Sortie : UNIQUEMENT du JSON sur stdout. Code retour 0 = OK, 1 = erreur.

Prerequis : profil d'appairage dans $HOME/.pyremoteplay/.profile.json
            (appairage a realiser une seule fois - voir la documentation)

Le meme profil sert au reveil ET a la mise en veille : aucun "user-credential"
n'est necessaire.

Particularites de pyremoteplay 0.7.6, toutes gerees ici :

1. standby() est une COROUTINE, wakeup() ne l'est PAS. Appeler standby() sans
   await renvoie un objet coroutine (toujours evalue a True) sans rien executer :
   la commande n'est jamais envoyee alors que tout semble reussir.

2. La valeur de retour de standby() n'est pas fiable : la negociation emet des
   "Version not accepted" puis la lib renvoie False, alors que la console recoit
   bien l'ordre. On ignore donc le booleen et on interroge l'etat reel.

3. Pendant une bascule, la console cesse de repondre au DDP : elle est
   temporairement INJOIGNABLE. Ce silence n'est pas un echec.

La lib ecrit du bruit sur stdout/stderr, qui casserait le json_decode() cote
PHP : toute sortie parasite est capturee et jetee.
"""

import argparse
import asyncio
import contextlib
import io
import json
import logging
import sys
import time
import warnings

warnings.filterwarnings("ignore")
logging.disable(logging.CRITICAL)

try:
    from pyremoteplay import RPDevice
    from pyremoteplay.profile import Profiles
except ImportError as exc:
    print(json.dumps({"success": False, "error": "pyremoteplay absent : %s" % exc}))
    sys.exit(1)


TIMEOUT = 40        # etablissement de la session Remote Play (lent)
CHECK_DELAY = 5     # attente avant la premiere verification
CHECK_TRIES = 7     # nombre de verifications
CHECK_INTERVAL = 3  # secondes entre deux verifications

STATUS_ON = 200
STATUS_STANDBY = 620


def emit(payload, code=0):
    """Ecrit le JSON sur le vrai stdout et termine."""
    sys.__stdout__.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.__stdout__.flush()
    sys.exit(code)


def fetch_status(ip):
    """Interroge la console. Retourne le dict de statut, ou None si injoignable."""
    return RPDevice(ip).get_status()


def describe(status):
    if status is None:
        return 0, "Injoignable", ""
    code = status.get("status-code")
    if code == STATUS_ON:
        return 1, "Allumee", status.get("running-app-name", "") or ""
    if code == STATUS_STANDBY:
        return 0, "Veille", ""
    return 0, "Eteinte", ""


def prepare(ip, profiles):
    """Retourne (device, status, user) ou un dict d'erreur."""
    device = RPDevice(ip)

    # Indispensable : peuple les infos de la console (mac, type, is_on).
    # Sans cet appel, get_users() ne retrouve aucun profil.
    status = device.get_status()
    if status is None:
        return None, {"success": False, "error": "console injoignable sur %s" % ip}

    users = device.get_users(profiles=profiles)
    if not users:
        return None, {
            "success": False,
            "error": "aucun compte PSN appaire pour cette console "
                     "(appairage a faire une fois en SSH, voir la documentation)",
        }

    return (device, status, users[0]), None


def cmd_status(ip):
    status = fetch_status(ip)
    online, etat, app = describe(status)
    emit({
        "success": True,
        "online": online,
        "etat": etat,
        "application": app,
        "raw": status,
    })


# --------------------------------------------------------------------------- #
#  REVEIL  (wakeup est synchrone)
# --------------------------------------------------------------------------- #

def _wakeup(ip):
    profiles = Profiles.load()
    ready, err = prepare(ip, profiles)
    if err:
        return err
    device, status, user = ready

    if status.get("status-code") == STATUS_ON:
        return {"success": True, "action": "wakeup", "note": "deja allumee"}

    device.wakeup(user, profiles)

    # Seule verite : l'etat reel de la console.
    time.sleep(CHECK_DELAY)
    for _ in range(CHECK_TRIES):
        check = fetch_status(ip)
        if check is not None and check.get("status-code") == STATUS_ON:
            return {"success": True, "action": "wakeup", "user": user}
        time.sleep(CHECK_INTERVAL)

    return {"success": False, "error": "la console ne s'est pas allumee dans le delai imparti"}


def cmd_wakeup(ip):
    noise = io.StringIO()
    with contextlib.redirect_stdout(noise), contextlib.redirect_stderr(noise):
        result = _wakeup(ip)
    emit(result, 0 if result.get("success") else 1)


# --------------------------------------------------------------------------- #
#  MISE EN VEILLE  (standby est une coroutine)
# --------------------------------------------------------------------------- #

async def _standby(ip):
    profiles = Profiles.load()
    ready, err = prepare(ip, profiles)
    if err:
        return err
    device, status, user = ready

    if status.get("status-code") == STATUS_STANDBY:
        return {"success": True, "action": "standby", "note": "deja en veille"}

    if not device.is_on:
        return {
            "success": False,
            "error": "la console doit etre allumee (etat : %s)"
                     % status.get("status", "inconnu"),
        }

    # Valeur de retour volontairement ignoree : elle n'est pas fiable (cf. entete).
    try:
        await asyncio.wait_for(device.standby(user, profiles), timeout=TIMEOUT)
    except (asyncio.TimeoutError, Exception):  # noqa: BLE001
        pass
    finally:
        try:
            res = device.disconnect()
            if asyncio.iscoroutine(res):
                await res
        except Exception:  # noqa: BLE001
            pass

    # Verification sur l'etat reel.
    #  - 620 (Veille)       -> succes confirme
    #  - None (injoignable) -> bascule en cours : ce n'est PAS un echec
    #  - 200 (Allumee)      -> on continue d'attendre
    await asyncio.sleep(CHECK_DELAY)
    unreachable = False

    for _ in range(CHECK_TRIES):
        check = fetch_status(ip)
        if check is None:
            unreachable = True
        elif check.get("status-code") == STATUS_STANDBY:
            return {"success": True, "action": "standby", "user": user}
        await asyncio.sleep(CHECK_INTERVAL)

    if unreachable:
        return {
            "success": True,
            "action": "standby",
            "user": user,
            "note": "console injoignable (bascule en cours)",
        }

    return {"success": False, "error": "la console est restee allumee"}


def cmd_standby(ip):
    noise = io.StringIO()
    with contextlib.redirect_stdout(noise), contextlib.redirect_stderr(noise):
        result = asyncio.run(_standby(ip))
    emit(result, 0 if result.get("success") else 1)


# --------------------------------------------------------------------------- #

def main():
    parser = argparse.ArgumentParser(description="CLI PS5 (pyremoteplay)")
    parser.add_argument("action", choices=["status", "wakeup", "standby"])
    parser.add_argument("--ip", required=True, help="IP de la PS5")
    args = parser.parse_args()

    try:
        if args.action == "status":
            cmd_status(args.ip)
        elif args.action == "wakeup":
            cmd_wakeup(args.ip)
        elif args.action == "standby":
            cmd_standby(args.ip)
    except SystemExit:
        raise
    except Exception as exc:  # noqa: BLE001
        emit({"success": False, "error": str(exc)}, 1)


if __name__ == "__main__":
    main()
