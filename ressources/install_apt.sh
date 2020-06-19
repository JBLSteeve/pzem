#!/bin/bash
PROGRESS_FILE=/tmp/jeedom/pzem/dependance
PROGRESS_FILE=$1
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
function apt_install {
  sudo apt-get -y install "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $1 - abort"
    rm ${PROGRESS_FILE}
    exit 1
  fi
}
function pip_install {
  sudo pip install "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $p - abort"
    rm ${PROGRESS_FILE}
    exit 1
  fi
}
function pip3_install {
  sudo pip3 install "$@"
  if [ $? -ne 0 ]; then
    echo "could not install $p - abort"
    rm ${PROGRESS_FILE}
    exit 1
  fi
}

#Prérequis installation de python3
echo 10 > ${PROGRESS_FILE}
#apt_install python3
#apt_install python3-pip
echo "-->Lancement de l'installation/mise à jour des dépendances PZEM"
echo "->Prérequis python3"
echo "->Raffraichissement du système"
sudo apt-get update
echo 20 > ${PROGRESS_FILE}
#echo "Installation de la librairie ftdi"
#sudo apt-get -y install python-ftdi
#sudo apt-get -y install python-ftdi1
echo 40 > ${PROGRESS_FILE}
echo "->Installation de la librairie serial"
#sudo pip uninstall -y serial
#apt_install python-serial
pip3_install pyserial
pip3_install setuptools
pip3_install requests
pip3_install pyudev
echo 50 > ${PROGRESS_FILE}
echo "->Installation de la librairie modbus"
pip3_install modbus-tk
pip3_install pyserial
echo 100 > ${PROGRESS_FILE}
echo "-->Everything is successfully installed!"
rm ${PROGRESS_FILE}
