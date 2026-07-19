#!/bin/bash
set -e

ENV_FILE=".env"

if [ ! -f "$ENV_FILE" ]; then
    echo "Brak pliku .env — najpierw go utwórz i uzupełnij."
    exit 1
fi

# wyciągnij VAULT z .env
VAULT=$(grep -E '^VAULT=' "$ENV_FILE" | cut -d '=' -f2-)

if [ -z "$VAULT" ]; then
    echo "Nie znaleziono VAULT= w .env. Ustaw np. VAULT=Dupa"
    exit 1
fi

TARGET="./perlite/${VAULT}"

if [ -d "$TARGET" ]; then
    echo "Folder $TARGET już istnieje, pomijam."
else
    mkdir -p "$TARGET"
    echo "Utworzono $TARGET"
fi

if [ -f "$TARGET/HOME.md" ]; then
    echo "$TARGET/HOME.md już istnieje, pomijam kopiowanie."
else
    cp ./HOME.md "$TARGET/HOME.md"
    echo "Skopiowano HOME.md do $TARGET"
fi

echo "Gotowe. Możesz odpalić: docker-compose up -d"
