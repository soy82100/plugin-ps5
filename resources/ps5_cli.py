#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI PS5 pour le plugin Jeedom - via pyremoteplay
Remplace playactor (non maintenu depuis 2022, echec 403 sur firmware recent).

Usage :
    ps5_cli.py status  --ip 192.168.1.XX
    ps5_cli.py standby --ip 192.168.1.XX

Sortie : JSON sur stdout. Code retour 0 = OK, 1 = erreur.

Prerequis : profil d'appairage dans $HOME/.pyremoteplay/.profile.json
            (voir la documentation du plugin)

Note importante : RPDevice.standby() est une COROUTINE. L'appeler sans await
renvoie un objet coroutine (toujours evalue a True) sans rien executer : la
commande n'est jamais envoyee a la console alors que tout semble reussir.
D'ou l'usage d'asyncio ci-dessous.
"""

import argparse
import asyncio
import json
import sys
import warnings

warnings.filterwarnings("ignore")  # masque le UserWarning "av not installed"

try:
    from pyremoteplay import RPDevice
    from pyremoteplay.profile import Profiles
except ImportError as exc:
    print(json.dumps({"success": False, "error": "pyremoteplay absent : %s" % exc}))
    sys.exit(1)


TIMEOUT = 45  # secondes : la session Remote Play met plusieurs secondes a s'etablir


def out(payload, code=0):
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(code)


async def maybe_await(value):
    """Attend la valeur si c'est une coroutine, la renvoie telle quelle sinon."""
    if asyncio.iscoroutine(value):
        return await value
    return value


def read_status(ip):
    """Interroge la console (synchrone). Retourne le dict de statut ou None."""
    device = RPDevice(ip)
    status = device.get_status()
    return device, status


def cmd_status(ip):
    device, status = read_status(ip)
    if status is None:
        out({"success": True, "online": 0, "etat": "Injoignable", "application": ""})

    code = status.get("status-code")
    if code == 200:
        etat, online = "Allumee", 1
    elif code == 620:
        etat, online = "Veille", 0
    else:
        etat, online = "Eteinte", 0

    out({
        "success": True,
        "online": online,
        "etat": etat,
        "application": status.get("running-app-name", "") or "",
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

    if not device.is_on:
        return {
            "success": False,
            "error": "la console doit etre allumee pour recevoir la mise en veille "
                     "(etat actuel : %s)" % status.get("status", "inconnu"),
        }

    users = device.get_users(profiles=profiles)
    if not users:
        return {
            "success": False,
            "error": "aucun compte PSN appaire pour cette console "
                     "(voir la documentation : appairage a faire une fois en SSH)",
        }

    user = users[0]

    try:
        ok = await asyncio.wait_for(device.standby(user, profiles), timeout=TIMEOUT)
    except asyncio.TimeoutError:
        return {"success": False, "error": "delai depasse lors de la mise en veille"}
    finally:
        # Ferme proprement la session Remote Play, qu'elle ait abouti ou non.
        try:
            await maybe_await(device.disconnect())
        except Exception:  # noqa: BLE001
            pass

    if ok:
        return {"success": True, "action": "standby", "user": user}
    return {"success": False, "error": "la console a refuse la mise en veille"}


def cmd_standby(ip):
    result = asyncio.run(_standby(ip))
    out(result, 0 if result.get("success") else 1)


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
        out({"success": False, "error": str(exc)}, 1)


if __name__ == "__main__":
    main()
