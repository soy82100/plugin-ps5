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

Deux pieges de pyremoteplay 0.7.6, geres ici :

1. RPDevice.standby() est une COROUTINE. L'appeler sans await renvoie un objet
   coroutine (toujours evalue a True) sans rien executer : la commande n'est
   jamais envoyee alors que tout semble reussir. D'ou asyncio.

2. La valeur de retour de standby() n'est PAS fiable. La negociation de session
   emet des "Version not accepted" et un "Network Test timed out", puis la lib
   renvoie False -- alors que la console recoit bien l'ordre et se met en veille.
   On ne se fie donc pas au booleen : on interroge l'etat reel de la console
   apres coup. C'est la seule verite.

La lib ecrit aussi du bruit sur stdout/stderr, qui casserait le json_decode()
cote PHP : toute la sortie parasite est capturee et jetee.
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


TIMEOUT = 45        # etablissement de la session Remote Play (lent)
CHECK_DELAY = 6     # attente avant de verifier l'etat reel de la console
CHECK_TRIES = 5     # nombre de verifications
CHECK_INTERVAL = 3  # secondes entre deux verifications

STATUS_ON = 200
STATUS_STANDBY = 620


def emit(payload, code=0):
    """Ecrit le JSON sur le vrai stdout et termine."""
    sys.__stdout__.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.__stdout__.flush()
    sys.exit(code)


def fetch_status(ip):
    """Interroge la console. Retourne (device, status) ; status peut etre None."""
    device = RPDevice(ip)
    return device, device.get_status()


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
    _, status = fetch_status(ip)
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

    # La valeur de retour est ignoree volontairement : elle est non fiable.
    try:
        await asyncio.wait_for(device.standby(user, profiles), timeout=TIMEOUT)
    except asyncio.TimeoutError:
        pass
    except Exception:  # noqa: BLE001
        pass
    finally:
        try:
            res = device.disconnect()
            if asyncio.iscoroutine(res):
                await res
        except Exception:  # noqa: BLE001
            pass

    # Seule verite : l'etat reel de la console.
    await asyncio.sleep(CHECK_DELAY)
    for _ in range(CHECK_TRIES):
        _, check = fetch_status(ip)
        if check is not None and check.get("status-code") == STATUS_STANDBY:
            return {"success": True, "action": "standby", "user": user}
        await asyncio.sleep(CHECK_INTERVAL)

    return {
        "success": False,
        "error": "la console n'est pas passee en veille dans le delai imparti",
    }


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
