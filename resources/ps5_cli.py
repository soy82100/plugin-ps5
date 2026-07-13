#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI PS5 pour le plugin Jeedom - via pyremoteplay
Remplace playactor (non maintenu depuis 2022, echec 403 sur firmware recent).

Usage :
    ps5_cli.py status  --ip 192.168.1.XX
    ps5_cli.py standby --ip 192.168.1.XX

Sortie : UNIQUEMENT du JSON sur stdout. Code retour 0 = OK, 1 = erreur.

Prerequis : profil d'appairage dans $HOME/.pyremoteplay/.profile.json
            (voir la documentation du plugin)

Trois pieges de pyremoteplay 0.7.6, tous geres ici :

1. RPDevice.standby() est une COROUTINE. L'appeler sans await renvoie un objet
   coroutine (toujours evalue a True) sans rien executer : la commande n'est
   jamais envoyee alors que tout semble reussir. D'ou asyncio.

2. La valeur de retour de standby() n'est PAS fiable. La negociation emet des
   "Version not accepted" et un "Network Test timed out", puis la lib renvoie
   False -- alors que la console recoit bien l'ordre et se met en veille.
   On ignore donc le booleen et on interroge l'etat reel de la console.

3. Pendant la bascule en veille, la console cesse de repondre au DDP : elle est
   temporairement INJOIGNABLE. Ce silence ne doit pas etre pris pour un echec,
   c'est au contraire le signe que la mise en veille est en cours.

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


async def _standby(ip):
    profiles = Profiles.load()
    device = RPDevice(ip)

    # Indispensable : peuple les infos de la console (mac, type, is_on).
    # Sans cet appel, is_on est faux et get_users() ne trouve aucun profil.
    status = device.get_status()
    if status is None:
        return {"success": False, "error": "console injoignable sur %s" % ip}

    if status.get("status-code") == STATUS_STANDBY:
        return {"success": True, "action": "standby", "note": "deja en veille"}

    if not device.is_on:
        return {
            "success": False,
            "error": "la console doit etre allumee (etat : %s)"
                     % status.get("status", "inconnu"),
        }

    users = device.get_users(profiles=profiles)
    if not users:
        return {
            "success": False,
            "error": "aucun compte PSN appaire pour cette console "
                     "(appairage a faire une fois en SSH, voir la documentation)",
        }

    user = users[0]

    # Valeur de retour volontairement ignoree : elle est non fiable (cf. entete).
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

    # Verification sur l'etat reel de la console.
    #  - 620 (Veille)      -> succes confirme
    #  - None (injoignable) -> bascule en cours : on patiente, ce n'est PAS un echec
    #  - 200 (Allumee)      -> toujours allumee : on continue d'attendre
    await asyncio.sleep(CHECK_DELAY)
    unreachable = False

    for _ in range(CHECK_TRIES):
        check = fetch_status(ip)
        if check is None:
            unreachable = True
        elif check.get("status-code") == STATUS_STANDBY:
            return {"success": True, "action": "standby", "user": user}
        await asyncio.sleep(CHECK_INTERVAL)

    # La console ne repond plus au DDP : elle est presque certainement en train
    # de basculer (ou deja eteinte). On considere la commande comme passee.
    if unreachable:
        return {
            "success": True,
            "action": "standby",
            "user": user,
            "note": "console injoignable (bascule en cours)",
        }

    return {"success": False, "error": "la console est restee allumee"}


def cmd_standby(ip):
    # pyremoteplay ecrit du bruit sur stdout/stderr ("Version not accepted",
    # "Network Test timed out"...) : on le capture pour garantir un JSON propre.
    noise = io.StringIO()
    with contextlib.redirect_stdout(noise), contextlib.redirect_stderr(noise):
        result = asyncio.run(_standby(ip))
    emit(result, 0 if result.get("success") else 1)


def main():
    parser = argparse.ArgumentParser(description="CLI PS5 (pyremoteplay)")
    parser.add_argument("action", choices=["status", "standby"])
    parser.add_argument("--ip", required=True, help="IP de la PS5")
    args = parser.parse_args()

    try:
        if args.action == "status":
            cmd_status(args.ip)
        elif args.action == "standby":
            cmd_standby(args.ip)
    except SystemExit:
        raise
    except Exception as exc:  # noqa: BLE001
        emit({"success": False, "error": str(exc)}, 1)


if __name__ == "__main__":
    main()
