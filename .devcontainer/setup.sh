#!/bin/bash

# Attendre que WordPress soit prêt
echo "⏳ Attente du démarrage de WordPress..."
until docker exec wordpress wp core is-installed --allow-root; do
  sleep 5
done

echo "🚀 Configuration initiale de WordPress..."

# Installation rapide (URL temporaire Codespaces)
docker exec wordpress wp core install \
  --url="http://localhost:8080" \
  --title="Dev Sandbox" \
  --admin_user="admin" \
  --admin_password="password" \
  --admin_email="admin@example.com" \
  --skip-email \
  --allow-root

# Activation de votre plugin (le nom du dossier doit correspondre au volume)
echo "🔌 Activation du plugin..."
docker exec wordpress wp plugin activate mon-plugin-en-dev --allow-root

# Création forcée du fichier de log pour éviter l'erreur "file not found" au tail
docker exec wordpress touch /var/www/html/wp-content/debug.log
docker exec wordpress chmod 666 /var/www/html/wp-content/debug.log

echo "✅ Environnement prêt !"
