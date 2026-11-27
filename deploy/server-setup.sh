#!/usr/bin/env bash
# Prepara un servidor Ubuntu 22.04/24.04 (ARM o x86) para correr el proyecto.
# Lo ejecuta deploy.sh por SSH con sudo; es idempotente (se puede repetir).
#   - Docker + Compose + Buildx desde los repositorios de Ubuntu (sin scripts remotos)
#   - Swap de 2 GB si la máquina tiene poca RAM (compilar Angular/PHP la necesita)
#   - Abre 80/443 en el firewall local (incluye la regla REJECT que traen las VMs de Oracle)
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

echo "  · Paquetes"
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  apt-get update -y -qq
  apt-get install -y -qq docker.io docker-compose-v2 docker-buildx rsync curl >/dev/null
fi
systemctl enable --now docker >/dev/null 2>&1 || true

LOGIN_USER="${SUDO_USER:-}"
if [ -n "$LOGIN_USER" ] && [ "$LOGIN_USER" != "root" ]; then
  usermod -aG docker "$LOGIN_USER" || true
fi

echo "  · Swap"
MEM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
if [ "$MEM_MB" -lt 3500 ] && ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "  · Firewall (80/443)"
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
  ufw allow 22/tcp >/dev/null; ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null; ufw allow 443/udp >/dev/null
fi
# Las imágenes Ubuntu de Oracle Cloud traen iptables con un REJECT final
if iptables -S INPUT 2>/dev/null | grep -q -- "-j REJECT"; then
  iptables -C INPUT -p tcp -m multiport --dports 80,443 -j ACCEPT 2>/dev/null \
    || iptables -I INPUT 1 -p tcp -m multiport --dports 80,443 -j ACCEPT
  iptables -C INPUT -p udp --dport 443 -j ACCEPT 2>/dev/null \
    || iptables -I INPUT 1 -p udp --dport 443 -j ACCEPT
  if command -v netfilter-persistent >/dev/null 2>&1; then netfilter-persistent save >/dev/null 2>&1 || true; fi
fi

echo "  · Listo: $(docker --version | cut -d, -f1), $(docker compose version --short)"
