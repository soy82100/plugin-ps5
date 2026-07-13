#!/bin/bash
# Installation des dependances du plugin PS5 (Jeedom)
#
# Pourquoi un script dedie plutot que packages.json :
# le gestionnaire de dependances de Jeedom installe les paquets pip UN PAR UN,
# avec --force-reinstall --upgrade. Chaque paquet reinstalle donc ses propres
# dependances et ecrase les versions posees par les precedents.
#
# Or pyremoteplay 0.7.6 exige :
#   - pyee == 9.1.1  (a partir de pyee 10, ExecutorEventEmitter n'est plus
#                     exporte a la racine du module -> ImportError au demarrage)
#   - async_timeout  (dependance reelle, oubliee dans les metadonnees de la lib)
#
# Un seul "pip install" avec tous les paquets permet a pip de resoudre les
# versions ENSEMBLE : plus aucun paquet ne peut en ecraser un autre.

PROGRESS_FILE=$1
BASEDIR=$(cd "$(dirname "$0")" && pwd)
VENV="${BASEDIR}/python_venv"

progress() {
    if [ -n "${PROGRESS_FILE}" ]; then
        echo "$1" > "${PROGRESS_FILE}"
    fi
}

echo "*** Debut de l'installation des dependances PS5 ***"
progress 5

echo ""
echo "--- Paquets systeme ---"
sudo apt-get update
sudo apt-get install -y python3 python3-pip python3-venv
progress 25

echo ""
echo "--- Environnement virtuel Python ---"
sudo rm -rf "${VENV}"
sudo python3 -m venv "${VENV}"
progress 40

echo ""
echo "--- Mise a jour de pip ---"
sudo "${VENV}/bin/python3" -m pip install --upgrade pip wheel
progress 55

echo ""
echo "--- pyremoteplay et dependances (installation groupee) ---"
sudo "${VENV}/bin/python3" -m pip install "pyee==9.1.1" async_timeout pyremoteplay
progress 85

echo ""
echo "--- Droits ---"
sudo chown -R www-data:www-data "${VENV}"
progress 92

echo ""
echo "--- Verification ---"
sudo "${VENV}/bin/python3" -c "import pyremoteplay, pyee; print('pyremoteplay OK / pyee', pyee.__version__ if hasattr(pyee, '__version__') else '')"
if [ $? -ne 0 ]; then
    echo "ECHEC : pyremoteplay n'est pas importable."
    progress 100
    exit 1
fi

progress 100
echo ""
echo "*** Fin de l'installation des dependances PS5 ***"

if [ -n "${PROGRESS_FILE}" ]; then
    rm -f "${PROGRESS_FILE}"
fi
exit 0
