#!/usr/bin/env bash
# One-time hardening of a fresh Oracle Cloud Ubuntu 24.04 (ARM) VM.
# Run once as the image's default user:  sudo bash provision.sh
# Safe to run again: every step checks before it changes anything.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "run with sudo" >&2
    exit 1
fi

log() { printf '\n==> %s\n' "$*"; }

log "packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get upgrade -yq
apt-get install -yq ufw fail2ban unattended-upgrades ca-certificates curl git

log "user deploy"
if ! id deploy >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" deploy
fi
usermod -aG sudo deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
if [ ! -s /home/deploy/.ssh/authorized_keys ]; then
    install -m 600 -o deploy -g deploy "/home/${SUDO_USER:-ubuntu}/.ssh/authorized_keys" /home/deploy/.ssh/authorized_keys
fi
# Key-only login, so sudo cannot ask for a password deploy does not have.
echo "deploy ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/90-deploy
chmod 440 /etc/sudoers.d/90-deploy
install -d -m 700 -o deploy -g deploy /home/deploy/certs /home/deploy/backups

log "ssh: keys only, no root"
# sshd keeps the FIRST value it reads for each option, and the image may ship
# 50-cloud-init.conf with PasswordAuthentication yes: this file must sort first.
cat > /etc/ssh/sshd_config.d/01-hardening.conf <<'CONF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
CONF
sshd -t
systemctl reload ssh

log "firewall"
# Oracle's Ubuntu image ships iptables-persistent with REJECT-everything rules
# that fight ufw and Docker. Drop the package and its rules before ufw and
# Docker install their own; skipped once the package is gone.
if dpkg -s iptables-persistent >/dev/null 2>&1; then
    apt-get purge -yq iptables-persistent netfilter-persistent
    iptables -P INPUT ACCEPT
    iptables -P FORWARD ACCEPT
    iptables -F INPUT
    iptables -F FORWARD
fi
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 443/tcp
ufw --force enable

log "fail2ban"
cat > /etc/fail2ban/jail.d/sshd.local <<'CONF'
[sshd]
enabled = true
maxretry = 5
bantime = 1h
CONF
systemctl enable --now fail2ban
systemctl restart fail2ban

log "unattended-upgrades"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CONF

log "timezone"
timedatectl set-timezone America/Sao_Paulo

log "docker"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    # shellcheck disable=SC1091
    . /etc/os-release
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
usermod -aG docker deploy
systemctl enable --now docker

log "done - reconnect as: ssh deploy@<vm-ip>"
