#!/bin/bash
# Récap setup VPS Debian 12 - preprod.painsansgluten.fr
# Généré pas à pas, à transformer en script PRA à la fin

## Étape 1 — Mise à jour système, hostname, fuseau horaire
apt update && apt full-upgrade -y
apt autoremove -y
hostnamectl set-hostname web.painsansgluten
timedatectl set-timezone Europe/Paris

## Étape 2 — Utilisateur admin + utilisateur dédié au site
adduser deploy
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
# ATTENTION : si la clé de root a une restriction "command=..." (cloud-init / image Infomaniak),
# il faut l'enlever du fichier copié pour deploy — ne garder QUE "ssh-<type> <clé> <commentaire>"
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

adduser --system --group --home /var/www/painsansgluten --shell /usr/sbin/nologin painsansgluten
usermod -aG painsansgluten deploy
chmod 2775 /var/www/painsansgluten

## Étape 3 — Durcissement SSH (port 54000, plus de root/password)
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
# Dans /etc/ssh/sshd_config, s'assurer d'avoir :
#   Port 54000
#   PermitRootLogin no
#   PasswordAuthentication no
#   PubkeyAuthentication yes
sshd -t
systemctl restart ssh
# Vérifier AVANT de fermer la session root en cours :
#   ssh -p 54000 deploy@<IP_VPS>
#   sudo whoami   -> doit renvoyer root

## Étape 4 — fail2ban + logs persistants (pas de pare-feu host, géré en amont par les security groups Infomaniak Public Cloud)
apt install -y fail2ban
cat > /etc/fail2ban/jail.local << 'JAIL'
[sshd]
enabled = true
port = 54000
backend = systemd
maxretry = 4
bantime = 1h
findtime = 10m
JAIL
systemctl enable --now fail2ban
systemctl restart fail2ban

apt install -y rsyslog
systemctl enable --now rsyslog
mkdir -p /var/log/journal
systemd-tmpfiles --create --prefix /var/log/journal
systemctl restart systemd-journald

## Étape 5 — Paquets de base + dépôt Sury
apt install -y apt-transport-https lsb-release ca-certificates curl gnupg2 unzip git htop

curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt update

## Étape 6 — PHP 8.4 + extensions
apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-common \
  php8.4-mysql php8.4-mbstring php8.4-xml php8.4-bcmath \
  php8.4-curl php8.4-zip php8.4-intl php8.4-gd php8.4-opcache \
  php8.4-readline

## Étape 7 — Composer (vérification checksum officielle)
cd /tmp
EXPECTED_CHECKSUM="$(curl -sS https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo 'ERREUR checksum invalide' >&2
else
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
fi
rm composer-setup.php

## Étape 8 — Node.js 24.x (LTS actif, version épinglée explicitement pour reproductibilité PRA)
curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
apt install -y nodejs

## Étape 9 — Apache2 + modules
apt install -y apache2
a2enmod rewrite proxy_fcgi setenvif ssl headers
a2enconf php8.4-fpm
systemctl enable --now apache2 php8.4-fpm

## Étape 10 — MariaDB (mysql_secure_installation = interactif, répondu : root password fort, Y partout sauf unix_socket=N)
apt install -y mariadb-server mariadb-client
systemctl enable --now mariadb
# mysql_secure_installation (interactif, voir recap humain pour les réponses)

# Base + user dédié au site (MOT_DE_PASSE_A_RENSEIGNER = généré via `openssl rand -base64 32`, stocké en gestionnaire de mdp, PAS en dur ici)
mysql -u root -p <<'SQL'
CREATE DATABASE painsansgluten_preprod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'painsansgluten'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_A_RENSEIGNER';
GRANT ALL PRIVILEGES ON painsansgluten_preprod.* TO 'painsansgluten'@'localhost';
FLUSH PRIVILEGES;
SQL

## Étape 11 — Supervisor (base). Config précise du worker queue + cron schedule:run : À FAIRE au déploiement applicatif (chemin app inconnu à ce stade)
apt install -y supervisor
systemctl enable --now supervisor
# TODO déploiement : /etc/supervisor/conf.d/painsansgluten-worker.conf (user=painsansgluten, php8.4 artisan queue:work)
# TODO déploiement : crontab -u painsansgluten -e -> * * * * * cd <app_path> && php8.4 artisan schedule:run >> /dev/null 2>&1

## Étape 12a — Pool PHP-FPM dédié au site (isolation multi-sites)
cat > /etc/php/8.4/fpm/pool.d/painsansgluten.conf << 'POOL'
[painsansgluten]
user = painsansgluten
group = painsansgluten
listen = /run/php/php8.4-fpm-painsansgluten.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
POOL
php-fpm8.4 -t
# IMPORTANT : un simple `reload` ne suffit pas pour qu'un NOUVEAU pool crée son socket, il faut un restart complet
systemctl restart php8.4-fpm

## Étape 12b — Authentification basique (.htpasswd)
apt install -y apache2-utils
# htpasswd -c /etc/apache2/.htpasswd-painsansgluten-preprod <USER_A_RENSEIGNER>   (interactif, mot de passe demandé 2x)

## Étape 12c — Page de test + permissions (à remplacer par le vrai code Laravel au déploiement)
mkdir -p /var/www/painsansgluten/public
chown -R painsansgluten:painsansgluten /var/www/painsansgluten/public

## Étape 12d — VirtualHost Apache preprod.painsansgluten.fr
cat > /etc/apache2/sites-available/preprod-painsansgluten.conf << 'VHOST'
<VirtualHost *:80>
    ServerName preprod.painsansgluten.fr
    DocumentRoot /var/www/painsansgluten/public

    <Directory /var/www/painsansgluten/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm-painsansgluten.sock|fcgi://localhost/"
    </FilesMatch>

    Header always set X-Robots-Tag "noindex, nofollow"

    ErrorLog ${APACHE_LOG_DIR}/painsansgluten-preprod-error.log
    CustomLog ${APACHE_LOG_DIR}/painsansgluten-preprod-access.log combined
</VirtualHost>
VHOST

cat > /var/www/painsansgluten/public/.htaccess << 'HTACCESS'
AuthType Basic
AuthName "Zone preprod protegee"
AuthUserFile /etc/apache2/.htpasswd-painsansgluten-preprod
Require valid-user
HTACCESS
chown painsansgluten:painsansgluten /var/www/painsansgluten/public/.htaccess
# NOTE DEPLOIEMENT : fusionner ce .htaccess avec celui par défaut de Laravel (rewrite vers index.php) sans écraser l'un ou l'autre.

a2ensite preprod-painsansgluten.conf
a2dissite 000-default.conf
apache2ctl configtest
systemctl reload apache2

## Étape 14 — unattended-upgrades
apt install -y unattended-upgrades apt-listchanges
cat > /etc/apt/apt.conf.d/20auto-upgrades << 'AUTOUP'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
AUTOUP
systemctl enable --now unattended-upgrades

## Étape 15 — Vérif MariaDB bind localhost (OK: 127.0.0.1 confirmé) + masquage versions Apache/PHP
sed -i 's/^ServerTokens .*/ServerTokens Prod/' /etc/apache2/conf-available/security.conf
sed -i 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
sed -i 's/^expose_php = On/expose_php = Off/' /etc/php/8.4/fpm/php.ini
systemctl reload apache2
systemctl restart php8.4-fpm

## Étape 16 — Swap 2G (filet de sécurité, pas extension mémoire ; swappiness bas)
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
sysctl -p /etc/sysctl.d/99-swappiness.conf
