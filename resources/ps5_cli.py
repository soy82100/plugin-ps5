#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CLI PS5 pour le plugin Jeedom - via pyremoteplay
Remplace playactor (non maintenu depuis 2022, echec 403 sur firmware recent).

Usage :
    ps5_cli.py status  --ip 192.168.1.67
    ps5_cli.py standby --ip 192.168.1.67
    ps5_cli.py wakeup  --ip 192.168.1.67

Sortie : JSON sur stdout. Code retour 0 = OK, 1 = erreur.

Prerequis : profil d'appairage dans $HOME/.pyremoteplay/.profile.json
"""

import argparse
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


def out(payload, code=0):
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(code)


def get_device_and_user(ip):
    """Retourne (device, user, profiles) ou leve une exception explicite."""
    profiles = Profiles.load()
    device = RPDevice(ip)

    # get_status() est indispensable : il peuple les infos de la console
    # (mac, type...) sans lesquelles get_users() ne trouve rien.
    status = device.get_status()
    if status is None:
        raise RuntimeError("console injoignable sur %s" % ip)

    users = device.get_users(profiles=profiles)
    if not users:
        raise RuntimeError(
            "aucun compte PSN appaire pour cette console "
            "(lancer : pyremoteplay --register %s)" % ip
        )

    return device, users[0], profiles


def cmd_status(ip):
    device = RPDevice(ip)
    status = device.get_status()
    if status is None:
        out({"success": True, "online": 0, "etat": "Injoignable", "application": ""})

    # status est un dict : status, host-type, running-app-name, etc.
    raw = str(status.get("status", "")).lower()
    if "ok" in raw or "200" in raw:
        etat = "Allumee"
        online = 1
    elif "standby" in raw or "620" in raw:
        etat = "Veille"
        online = 0
    else:
        etat = "Eteinte"
        online = 0

    out({
        "success": True,
        "online": online,
        "etat": etat,
        "application": status.get("running-app-name", "") or "",
        "raw": status,
    })


def cmd_standby(ip):
    device, user, profiles = get_device_and_user(ip)
    ok = device.standby(user, profiles)
    if ok:
        out({"success": True, "action": "standby", "user": user})
    out({"success": False, "error": "standby refuse par la console"}, 1)


def cmd_wakeup(ip):
    device, user, profiles = get_device_and_user(ip)
    device.wakeup(user, profiles)
    out({"success": True, "action": "wakeup", "user": user})


def main():
    parser = argparse.ArgumentParser(description="CLI PS5 (pyremoteplay)")
    parser.add_argument("action", choices=["status", "standby", "wakeup"])
    parser.add_argument("--ip", required=True, help="IP de la PS5")
    args = parser.parse_args()

    try:
        if args.action == "status":
            cmd_status(args.ip)
        elif args.action == "standby":
            cmd_standby(args.ip)
        elif args.action == "wakeup":
            cmd_wakeup(args.ip)
    except Exception as exc:  # noqa: BLE001
        out({"success": False, "error": str(exc)}, 1)


if __name__ == "__main__":
    main()
