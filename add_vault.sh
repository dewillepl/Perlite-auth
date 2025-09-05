#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Użycie: $0 <nazwa_vaulta>"
  exit 1
fi

VAULT="$1"
TARGET="/opt/Perlite/perlite/${VAULT}"
USERNAME="$(whoami)"

# Tworzymy katalog vault
mkdir -p "$TARGET"

# Właściciel i grupa www-data
sudo chown -R www-data:www-data "$TARGET"

# setgid – wszystkie nowe pliki dziedziczą grupę www-data
sudo chmod g+s "$TARGET"

# ACL – www-data i użytkownik SSH mają pełne RW
sudo setfacl -R -m u:www-data:rwx,g:www-data:rwx "$TARGET"
sudo setfacl -dR -m u:www-data:rwx,g:www-data:rwx "$TARGET"

# Dodaj użytkownika SSH do grupy www-data
sudo usermod -aG www-data "$USERNAME"

echo "✅ Vault '${VAULT}' utworzony w ${TARGET}."
echo "✅ Użytkownik ${USERNAME} dodany do grupy www-data z pełnym dostępem."

