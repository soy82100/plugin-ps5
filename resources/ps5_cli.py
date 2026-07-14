#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI PS5 pour le plugin Jeedom.

Deux sources d'information, volontairement separees :

  * pyremoteplay (LOCAL) : etat de la console, reveil, mise en veille.
    Remplace playactor, non maintenu depuis 2022 (echec 403 a l'appairage sur
    les firmwares PS5 recents).

  * PSNAWP (CLOUD, OPTIONNEL) : jeu en cours.
    Les firmwares PS5 recents ne diffusent PLUS le champ running-app-name dans
    leur reponse DDP : le titre du jeu n'est donc pas recuperable en local.
    Seule l'API de presence PSN le fournit. Cette fonction est facultative :
    sans jeton npsso, le plugin reste 100% local.

Usage :
    ps5_cli.py status   --ip 192.168.1.XX
    ps5_cli.py wakeup   --ip 192.168.1.XX
    ps5_cli.py standby  --ip 192.168.1.XX
    ps5_cli.py presence --npsso-file /chemin/vers/token

Sortie : UNIQUEMENT du JSON sur stdout. Code retour 0 = OK, 1 = erreur.

Particularites de pyremoteplay 0.7.6, toutes gerees ici :
 1. standby() est une COROUTINE, wakeup() ne l'est pas.
 2. La valeur de retour de standby() n'est pas fiable : on verifie l'etat reel.
 3. Pendant une bascule, la console ne repond plus au DDP : ce n'est pas un echec.
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


TIMEOUT = 40
CHECK_DELAY = 5
CHECK_TRIES = 7
CHECK_INTERVAL = 3

STATUS_ON = 200
STATUS_STANDBY = 620


def emit(payload, code=0):
    """Ecrit le JSON sur le vrai stdout et termine."""
    sys.__stdout__.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.__stdout__.flush()
    sys.exit(code)


def fetch_status(ip):
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
    device = RPDevice(ip)
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
#  REVEIL  (synchrone)
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
#  MISE EN VEILLE  (coroutine)
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
#  PRESENCE PSN  (optionnel, cloud)
# --------------------------------------------------------------------------- #

def _presence(npsso_file):
    try:
        from psnawp_api import PSNAWP
    except ImportError as exc:
        return {"success": False, "error": "PSNAWP absent : %s" % exc}

    try:
        with open(npsso_file, "r") as fh:
            token = fh.read().strip()
    except Exception as exc:  # noqa: BLE001
        return {"success": False, "error": "jeton npsso illisible : %s" % exc}

    if not token:
        return {"success": False, "error": "jeton npsso vide"}

    try:
        psn = PSNAWP(token)
        me = psn.me()
        online_id = me.online_id
        user = psn.user(online_id=online_id)
        pres = user.get_presence()
    except Exception as exc:  # noqa: BLE001
        return {
            "success": False,
            "error": "PSN : %s (le jeton npsso a peut-etre expire, "
                     "voir la documentation pour le renouveler)" % exc,
        }

    basic = (pres or {}).get("basicPresence", {})
    platform = basic.get("primaryPlatformInfo", {}) or {}
    titles = basic.get("gameTitleInfoList") or []

    jeu = ""
    title_id = ""
    jaquette = ""
    if titles:
        first = titles[0]
        jeu = first.get("titleName", "") or ""
        title_id = first.get("npTitleId", "") or ""
        jaquette = first.get("conceptIconUrl", "") or ""

    return {
        "success": True,
        "compte": online_id,
        "statut_psn": platform.get("onlineStatus", "") or "",
        "plateforme": platform.get("platform", "") or "",
        "jeu": jeu,
        "title_id": title_id,
        "jaquette": jaquette,
    }


def cmd_presence(npsso_file):
    noise = io.StringIO()
    with contextlib.redirect_stdout(noise), contextlib.redirect_stderr(noise):
        result = _presence(npsso_file)
    emit(result, 0 if result.get("success") else 1)


# --------------------------------------------------------------------------- #

def main():
    parser = argparse.ArgumentParser(description="CLI PS5 (pyremoteplay / PSNAWP)")
    parser.add_argument("action", choices=["status", "wakeup", "standby", "presence"])
    parser.add_argument("--ip", help="IP de la PS5 (status / wakeup / standby)")
    parser.add_argument("--npsso-file", help="Fichier contenant le jeton npsso (presence)")
    args = parser.parse_args()

    try:
        if args.action == "presence":
            if not args.npsso_file:
                emit({"success": False, "error": "--npsso-file requis pour l'action presence"}, 1)
            cmd_presence(args.npsso_file)
        else:
            if not args.ip:
                emit({"success": False, "error": "--ip requis"}, 1)
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

