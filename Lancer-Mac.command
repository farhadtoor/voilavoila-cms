#!/bin/bash
set -eu
cd "$(dirname "$0")"
if ! command -v php >/dev/null 2>&1; then
  echo "PHP 8.3+ est nécessaire. Ouvrez INSTALLATION.html pour les options Mac ou Docker."
  read -r -p "Appuyez sur Entrée pour fermer. " vv_close
  exit 1
fi
echo "VoilaVoila CMS · http://127.0.0.1:8787"
php bin/console.php status
php bin/console.php install-key || true
echo "Laissez ce terminal ouvert. Ctrl+C pour arrêter."
(open "http://127.0.0.1:8787" >/dev/null 2>&1 || true) &
exec php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php
