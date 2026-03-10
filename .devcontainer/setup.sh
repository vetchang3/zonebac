#!/bin/bash

echo "⏳ Attente du démarrage de WordPress (30s)..."
# On laisse un peu de temps à MariaDB pour s'initialiser
sleep 30

# Définition de l'URL dynamique de Codespace
CS_URL="https://${CODESPACE_NAME}-80.app.github.dev"

echo "🚀 Installation de WordPress sur $CS_URL ..."
docker exec wordpress wp core install \
  --url="$CS_URL" \
  --title="ZoneBac Dev" \
  --admin_user="admin" \
  --admin_password="password" \
  --admin_email="admin@zonebac.com" \
  --skip-email \
  --allow-root

echo "🔌 Activation du plugin ZoneBac..."
docker exec wordpress wp plugin activate zonebac --allow-root

# Gestion du debug.log
echo "📝 Configuration du fichier de log..."
docker exec wordpress touch /var/www/html/wp-content/debug.log
docker exec wordpress chmod 666 /var/www/html/wp-content/debug.log

# Création du lien symbolique pour un accès direct dans l'explorateur VS Code
ln -sf /var/www/html/wp-content/debug.log /var/www/html/wp-content/plugins/zonebac/debug.log

echo "✅ Environnement prêt ! Le fichier debug.log est dans votre barre latérale."
