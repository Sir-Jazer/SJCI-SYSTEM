# Deploying SJCI SYSTEM

A step-by-step guide to put the app online on **Oracle Cloud (Always Free)**,
starting on the server's **IP address** (add a domain + HTTPS later — see Stage 8).

Your stack makes this simpler than most: **SQLite** means no separate database
server, and the admin panel needs **no Node/asset build** at runtime.

> Legend: 🖥️ = do it in the Oracle web console · `$` = run over SSH on the server.

---

## Stage 1 — Create the server (Oracle Cloud Always Free) 🖥️

1. Sign up at <https://www.oracle.com/cloud/free/>. Identity check asks for a card,
   but **Always Free** resources are never charged. Pick a home region close to you
   (e.g. **Singapore** or **Japan** for the Philippines).
2. Console → **Compute → Instances → Create instance**:
   - **Image:** Canonical **Ubuntu 24.04**.
   - **Shape:** Change shape → **Ampere (Arm)** → `VM.Standard.A1.Flex`. 1 OCPU / 6 GB
     RAM is plenty and stays in the Always Free allowance.
   - **SSH keys:** choose *Generate a key pair* and **download the private key** (you
     need it to log in). Keep it safe.
   - Leave networking on the default VCN (it creates one for you). Create.
3. When it's running, note the **Public IP address** (Instance details page).
4. First login from your Mac (replace the key path + IP):
   ```
   chmod 400 ~/Downloads/ssh-key-*.key
   ssh -i ~/Downloads/ssh-key-*.key ubuntu@YOUR_SERVER_IP
   ```

---

## Stage 2 — Open the firewall (⚠️ the classic Oracle trap)

Oracle has **two** firewalls. You must open ports **80** (and later **443**) in *both*
or the site is unreachable for no obvious reason.

**A. Cloud "Security List"** 🖥️
Networking → your VCN → default subnet → default Security List → **Add Ingress Rules**:
- Source `0.0.0.0/0`, IP Protocol **TCP**, Destination port **80**
- (add **443** now too, for when you enable HTTPS)

**B. The instance's own firewall** `$`
Ubuntu on Oracle ships locked-down `iptables`. Open the ports and persist:
```
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

---

## Stage 3 — Install the runtime `$`

```
sudo apt update && sudo apt upgrade -y

# The app's locked dependencies (Symfony 8.1) require PHP >= 8.4, which Ubuntu
# 24.04 doesn't ship by default — add the well-known ondrej/php PPA to get 8.4.
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# nginx + PHP 8.4
sudo apt install -y nginx git unzip sqlite3 \
  php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-bcmath php8.4-zip php8.4-intl php8.4-gd

# Composer
php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

---

## Stage 4 — Deploy the app `$`

```
# 1. Get the code (use YOUR GitHub repo URL)
sudo mkdir -p /var/www && sudo chown $USER:$USER /var/www
cd /var/www
git clone https://github.com/YOUR_USER/YOUR_REPO.git sjci
cd sjci

# 2. PHP dependencies (production only — skips dev/test packages)
composer install --no-dev --optimize-autoloader

# 3. Environment file
cp .env.production.example .env
php artisan key:generate            # fills APP_KEY
nano .env                           # set APP_URL=http://YOUR_SERVER_IP (fix DB_DATABASE path if not /var/www/sjci)

# 4. Create the database file + run migrations (NO --seed: that's test data)
touch database/database.sqlite
php artisan migrate --force

# 5. Link uploaded files into the public web root
php artisan storage:link

# 6. Cache config/routes/views for speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Permissions — the web user (www-data) must be able to WRITE the SQLite file,
#    its directory (for -wal/-shm files), and storage/. This is the #1 cause of
#    a blank "500" on first load, so don't skip it.
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

---

## Stage 5 — Point nginx at the app `$`

```
sudo nano /etc/nginx/sites-available/sjci
```
Paste (replace `YOUR_SERVER_IP`):
```nginx
server {
    listen 80;
    server_name YOUR_SERVER_IP;
    root /var/www/sjci/public;

    index index.php;
    charset utf-8;
    client_max_body_size 12M;              # room for uploaded proof images

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```
Enable it and reload:
```
sudo ln -s /etc/nginx/sites-available/sjci /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```
Visit **http://YOUR_SERVER_IP** — you should land on the SJCI login page. 🎉

---

## Stage 6 — Make it real

1. **Create the first Head Pastor account** (no test data was seeded):
   ```
   php artisan tinker
   ```
   ```php
   $main = App\Models\Church::create(['name' => 'Shepherd Jubilee Church Inc.', 'is_main' => true]);
   App\Models\User::create([
       'name' => 'Head Pastor Name',
       'email' => 'headpastor@yourchurch.org',
       'password' => 'a-temporary-password',
       'role' => App\Enums\UserRole::HeadPastor,
       'church_id' => $main->id,
       'must_change_password' => true,   // you'll be prompted to set your own on first login
   ]);
   exit
   ```
   Then log in at http://YOUR_SERVER_IP and set your real password. From there you
   create outreach churches + pastors from inside the app (invite-only).

2. **Email (for "Forgot password?"):** until you set SMTP, `MAIL_MAILER=log` keeps
   reset links in `storage/logs/laravel.log`. When ready, put real SMTP creds in
   `.env` (a transactional provider like Brevo/Mailgun/Postmark, or Gmail SMTP for
   very low volume), then `php artisan config:cache`.

3. **Confirm production hardening:** `APP_DEBUG=false` and `APP_ENV=production` in
   `.env` (already set in the template). Never turn debug on for a live site.

---

## Stage 7 — Nightly backups to Object Storage

Back up **both** the database and the uploaded files, **off the server**.

1. **Create the bucket** 🖥️: Storage → Buckets → **Create Bucket** → name it
   `sjci-backups`.
2. **Let the server upload to it.** Easiest secure method — *instance principals*
   (no API keys on the box):
   - Identity → **Dynamic Groups** → create `sjci-servers` with a rule matching your
     instance's OCID: `instance.id = 'ocid1.instance....'`.
   - Identity → **Policies** → create a policy:
     `Allow dynamic-group sjci-servers to manage objects in compartment <your-compartment> where target.bucket.name='sjci-backups'`
   - Install the CLI on the server: `bash -c "$(curl -L https://raw.githubusercontent.com/oracle/oci-cli/master/scripts/install/install.sh)"`
     and tell it to use instance auth by exporting `OCI_CLI_AUTH=instance_principal`
     (the backup script picks up the CLI automatically).
3. **Schedule it** `$`:
   ```
   chmod +x /var/www/sjci/scripts/backup.sh
   sudo crontab -e
   ```
   Add (runs every night at 2:15 AM server time):
   ```
   15 2 * * *  OCI_CLI_AUTH=instance_principal /var/www/sjci/scripts/backup.sh >> /var/log/sjci-backup.log 2>&1
   ```
   The script keeps the newest 14 archives locally and uploads each to the bucket.
   It works from day one even before OCI is configured (local-only, with a warning).

**Restore drill** (do this once so you trust it):
```
tar -xzf sjci-backup-YYYY-MM-DD_HHMMSS.tar.gz -C /tmp/restore
# database  -> copy /tmp/restore/database.sqlite over database/database.sqlite
# uploads   -> copy /tmp/restore/public-uploads/* into storage/app/public/
# then: sudo chown -R www-data:www-data database storage
```

---

## Stage 8 — Add HTTPS later (strongly recommended)

Pastors log in with passwords, so plain `http://` sends those in the clear. You
chose to start on the IP — that's fine to get going, but move to HTTPS soon.
Let's Encrypt won't issue a certificate for a bare IP, so you need a hostname:

- **Free & fast:** register a free subdomain at <https://www.duckdns.org> (e.g.
  `sjci.duckdns.org`) pointing at your IP — no purchase needed.
- **Or** buy a cheap domain and point an `A` record at the IP.

Then:
```
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d sjci.duckdns.org     # auto-configures nginx + auto-renews
```
Finally, in `.env` set `APP_URL=https://your-host` and `SESSION_SECURE_COOKIE=true`,
then `php artisan config:cache`.

---

## Redeploying updates later `$`

When you push new commits to GitHub:
```
cd /var/www/sjci
php artisan down                 # brief maintenance page
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache database
php artisan up
```

---

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Site won't load at all | Firewall — did you open port 80 in **both** the Security List *and* `iptables` (Stage 2)? |
| 500 error, blank page | Permissions — `storage/`, `bootstrap/cache/`, and the **`database/` dir** must be owned by `www-data` (Stage 4 step 7). Check `storage/logs/laravel.log`. |
| "database is locked" / can't save | The `database/` **directory** (not just the file) must be writable by `www-data` so SQLite can create `-wal`/`-shm` files. |
| Can log in but immediately logged out | `SESSION_SECURE_COOKIE=true` while still on `http://`. Set it `false` until HTTPS is on. |
| Password-reset email never arrives | Still on `MAIL_MAILER=log` — check `storage/logs/laravel.log`, or configure real SMTP (Stage 6.2). |
| Changed `.env` but nothing changed | Run `php artisan config:cache` again (cached config wins). |