#!/bin/bash

echo "⏳ Attente du démarrage de WordPress..."
until docker exec wordpress wp core is-installed --allow-root; do
  sleep 5
done

echo "🚀 Configuration initiale de WordPress..."
docker exec wordpress wp core install \
  --url="http://localhost:8080" \
  --title="ZoneBac Dev" \
  --admin_user="admin" \
  --admin_password="password" \
  --admin_email="admin@zonebac.com" \
  --skip-email \
  --allow-root

echo "🔌 Activation du plugin ZoneBac..."
docker exec wordpress wp plugin activate zonebac --allow-root

# Gestion du debug.log
docker exec wordpress touch /var/www/html/wp-content/debug.log
docker exec wordpress chmod 666 /var/www/html/wp-content/debug.log

# Création du lien symbolique pour un accès direct dans VS Code
ln -sf /var/www/html/wp-content/debug.log /var/www/html/wp-content/plugins/zonebac/debug.log

echo "✅ Environnement prêt ! Le fichier debug.log est disponible dans votre dossier racine."
