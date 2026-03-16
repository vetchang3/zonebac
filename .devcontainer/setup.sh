#!/bin/bash
set -eu

echo "⏳ Attente du démarrage de WordPress (30s)..."
sleep 30

# Définir l'URL dynamique (Codespaces)
if [ -n "${CODESPACE_NAME:-}" ]; then
  CS_URL="https://${CODESPACE_NAME}-80.app.github.dev"
else
  CS_URL="http://localhost"
fi

echo "🚀 Installation de WordPress sur $CS_URL ..."

# Fonction utilitaire : exécuter wp-cli soit directement, soit via docker/docker-compose
run_wp() {
  # Si wp est disponible dans le PATH (cas : on est dans l'image wordpress)
  if command -v wp >/dev/null 2>&1; then
    wp "$@" --allow-root
    return $?
  fi

  # Si docker-compose est disponible (cas : on est sur l'hôte devcontainer)
  if command -v docker-compose >/dev/null 2>&1; then
    docker-compose exec -T wordpress wp "$@" --allow-root
    return $?
  fi

  # Si docker est disponible et qu'un conteneur nommé 'wordpress' existe, utiliser docker exec
  if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' | grep -q '^wordpress$'; then
    docker exec -i wordpress wp "$@" --allow-root
    return $?
  fi

  echo "⚠️ Impossible de trouver wp-cli ni docker-compose ni docker. Installation WP ignorée."
  return 1
}

# Essayer d'installer WP (ignore l'erreur mais affiche)
if run_wp core is-installed >/dev/null 2>&1; then
  echo "ℹ️ WordPress déjà installé."
else
  if run_wp core install --url="$CS_URL" --title="ZoneBac Dev" \
        --admin_user="admin" --admin_password="password" --admin_email="admin@zonebac.com" --skip-email; then
    echo "✅ WordPress installé."
  else
    echo "⚠️ Échec de l'installation automatique de WordPress. Voir logs pour plus d'info."
  fi
fi

echo "🔌 Activation du plugin ZoneBac..."
run_wp plugin activate zonebac || echo "⚠️ Impossible d'activer le plugin automatiquement."

# Gestion du debug.log
echo "📝 Configuration du fichier de log..."
# tenter de toucher le fichier dans le container / via wp path
if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' | grep -q '^wordpress$'; then
  docker exec -i wordpress bash -lc "touch /var/www/html/wp-content/debug.log && chmod 666 /var/www/html/wp-content/debug.log" || true
else
  # si on est dans le container wordpress (wp disponible), on peut toucher
  if command -v wp >/dev/null 2>&1; then
    bash -lc "touch /var/www/html/wp-content/debug.log && chmod 666 /var/www/html/wp-content/debug.log" || true
  else
    echo "ℹ️ Impossible de créer debug.log automatiquement (docker/wp manquant)."
  fi
fi

# Création du lien symbolique (si possible)
if [ -d "/var/www/html/wp-content/plugins/zonebac" ]; then
  ln -sf /var/www/html/wp-content/debug.log /var/www/html/wp-content/plugins/zonebac/debug.log || true
fi

echo "✅ Environnement prêt (ou partiellement configuré)."
