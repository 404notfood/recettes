#!/usr/bin/env bash
set -Eeuo pipefail

# Usage: sudo bash deploy/install-ssr-service.sh /opt/nvm/versions/node/v24.X.Y/bin/node [cuistot-recettes]
[[ $EUID -eq 0 ]] || { echo 'Lancer avec sudo.' >&2; exit 1; }
[[ $# -ge 1 && $# -le 2 ]] || { echo 'Usage : install-ssr-service.sh /chemin/absolu/node [nom-projet]' >&2; exit 1; }
node=$1
project=${2:-cuistot-recettes}
[[ $project =~ ^[a-z][a-z0-9-]{1,30}$ ]] || { echo 'Nom de projet invalide.' >&2; exit 1; }
[[ $node =~ ^/opt/nvm/versions/node/v24\.[0-9]+\.[0-9]+/bin/node$ && -x $node ]] || { echo 'Indiquer le chemin exact du Node 24 installé par le bootstrap dans /opt/nvm.' >&2; exit 1; }
[[ $(id -u "$project") != 0 ]] || { echo 'Le projet doit appartenir à un utilisateur dédié.' >&2; exit 1; }
app="/home/$project/app"
[[ -f "$app/bootstrap/ssr/app.js" && -f "$app/.env" ]] || { echo 'Installer et compiler le projet avant le service SSR.' >&2; exit 1; }
runuser -u "$project" -- "$node" --version | grep -q '^v24\.'
unit="/etc/systemd/system/$project-ssr.service"
if [[ -e $unit || -L $unit ]]; then
    [[ -f $unit && ! -L $unit ]] && head -n 1 "$unit" | grep -qx '# Cuistot Club managed SSR service' || { echo 'Service existant non géré : refus de le remplacer.' >&2; exit 1; }
fi
if ! systemctl is-active --quiet "$project-ssr.service"; then
    if ss -H -ltn 'sport = :13714' | grep -q .; then
        echo 'Le port 13714 est déjà occupé. Aucun service créé.' >&2
        exit 1
    fi
fi
template="$(dirname -- "${BASH_SOURCE[0]}")/inertia-ssr.service.template"
candidate=$(mktemp)
trap 'rm -f -- "$candidate"' EXIT
sed -e "s|__PROJECT__|$project|g" -e "s|__NODE__|$node|g" "$template" > "$candidate"
install -o root -g root -m 0644 "$candidate" "$unit"
systemctl daemon-reload
systemctl enable "$project-ssr.service"
systemctl restart "$project-ssr.service"
ready=0
for attempt in {1..15}; do
    if runuser -u "$project" -- /bin/bash -c 'cd -- "$1"; exec /usr/bin/php8.4 artisan inertia:check-ssr' -- "$app" >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done
[[ $ready -eq 1 ]] || { echo "SSR indisponible : journalctl -u $project-ssr.service -n 50" >&2; exit 1; }
echo "SSR opérationnel : $project-ssr.service, 127.0.0.1:13714."
