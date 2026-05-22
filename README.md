# 📘 Moodle 5.2 Installation Guide (AlmaLinux/RHEL 10 + LEMP)

## 1. Prerequisites
Update system and install base packages:
```bash
sudo dnf update -y
sudo dnf install git nginx mariadb-server -y
```

Install PHP and required extensions:
```bash
sudo dnf install php php-fpm php-mysqlnd php-xml php-gd php-intl php-json php-mbstring php-soap php-zip php-sodium -y
```

Optional session/cache extensions:
```bash
# Memcached
sudo dnf install memcached php-pecl-memcached -y

# Redis (via Remi repo)
sudo dnf install epel-release -y
sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-10.rpm -y
sudo dnf module enable redis:remi-7.2 -y
sudo dnf install redis php-pecl-redis -y
```

---

## 2. Enable and Start Services
Enable services to start at boot:
```bash
sudo systemctl enable nginx
sudo systemctl enable php-fpm
sudo systemctl enable mariadb
sudo systemctl enable memcached   # if using Memcached
sudo systemctl enable redis       # if using Redis
```

Start services immediately:
```bash
sudo systemctl start nginx
sudo systemctl start php-fpm
sudo systemctl start mariadb
sudo systemctl start memcached    # if using Memcached
sudo systemctl start redis        # if using Redis
```

Check status:
```bash
systemctl status nginx php-fpm mariadb
```

---

## 3. Download Moodle via Git
```bash
cd /var/www
sudo git clone -b MOODLE_52_STABLE https://github.com/moodle/moodle.git
```

Create Moodle data directory:
```bash
sudo mkdir /srv/moodledata
sudo chown -R nginx:nginx /srv/moodledata
chmod -R 770 /srv/moodledata
```

---

## 4. Database Setup
Secure MariaDB:
```bash
sudo mysql_secure_installation
```

Create Moodle DB and user:
```sql
CREATE DATABASE moodle DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'moodleuser'@'localhost' IDENTIFIED BY 'StrongPasswordHere';
GRANT ALL PRIVILEGES ON moodle.* TO 'moodleuser'@'localhost';
FLUSH PRIVILEGES;
```

---

## 5. Nginx Configuration
Create `/etc/nginx/conf.d/moodle.conf`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;

    root /var/www/moodle/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ [^/]\.php(/|$) {
        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        if (!-f $document_root$fastcgi_script_name) {
            return 404;
        }
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param DOCUMENT_ROOT $document_root;
    }

    location ~* ^/srv/moodledata/ {
        deny all;
    }

    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
}
```

Reload Nginx:
```bash
sudo systemctl restart nginx php-fpm
```

---

## 6. Moodle Configuration (`config.php`)
After running the web installer, Moodle generates `config.php`. Example:

```php
$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodleuser';
$CFG->dbpass    = 'StrongPasswordHere';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
    'dbpersist' => 0,
    'dbport' => '',
    'dbsocket' => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://yourdomain.com';
$CFG->dataroot  = '/srv/moodledata';
$CFG->admin     = 'admin';

// Default DB sessions
$CFG->session_handler_class = null;
```

---

## 7. Cron Setup
Moodle requires cron every minute:
```bash
sudo crontab -u nginx -e
```
Add:
```bash
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php >/dev/null 2>&1
```

---

## 8. Error Handling
- **Session handler misconfigured** → Use DB sessions (`$CFG->session_handler_class = null`).  
- **Cannot find session record** → Clear browser cookies, ensure cron runs, or configure Memcached/Redis.  
- **Primary script unknown** → Ensure Nginx `root` points to `/var/www/moodle/public`.  
- **FastCGI buffering errors** → Add buffer tuning in `nginx.conf`.  
- **Missing PHP extension** → Install required modules (`php-sodium`, `php-intl`, etc.) and restart PHP‑FPM.

---

## 9. Server & Moodle Hardening

## 🛡️ Server Hardening (AlmaLinux/RHEL 10)
- **Update regularly**  
  ```bash
  sudo dnf update -y
  ```
  Apply security patches promptly.

- **Firewall (firewalld)**  
  Allow only required services:
  ```bash
  sudo systemctl enable firewalld --now
  sudo firewall-cmd --permanent --add-service=http
  sudo firewall-cmd --permanent --add-service=https
  sudo firewall-cmd --reload
  ```

- **SELinux**  
  Keep SELinux enforcing:
  ```bash
  getenforce
  setenforce 1
  ```
  Configure contexts for `/srv/moodledata` if needed:
  ```bash
  sudo semanage fcontext -a -t httpd_sys_rw_content_t "/srv/moodledata(/.*)?"
  sudo restorecon -Rv /srv/moodledata
  ```

- **System services**  
  Disable unused services:
  ```bash
  sudo systemctl disable --now telnet ftp
  ```
  Only run what Moodle needs.

- **SSH security**  
  - Disable root login (`PermitRootLogin no` in `/etc/ssh/sshd_config`).  
  - Use key‑based authentication.  
  - Restrict SSH to trusted IPs via firewall.

---

## 🔐 Moodle Hardening
- **Config.php protection**  
  Ensure `config.php` is owned by `nginx` and not world‑writable:
  ```bash
  chown nginx:nginx /var/www/moodle/config.php
  chmod 640 /var/www/moodle/config.php
  ```

- **Moodledata protection**  
  Already outside web root (`/srv/moodledata`) and denied in Nginx. Keep permissions restricted (`770`).

- **Sessions**  
  - Default DB sessions are safe.  
  - For high concurrency, use Memcached or Redis.  
  - Ensure services are secured (bind to localhost, firewall rules).

- **Passwords & accounts**  
  - Use strong DB and Moodle admin passwords.  
  - Enforce password policies in Moodle (`Site administration → Security → Site policies`).

- **Disable directory listing**  
  In Nginx, ensure:
  ```nginx
  autoindex off;
  ```

- **Regular updates**  
  - Pull Moodle updates via Git:
    ```bash
    cd /var/www/moodle
    sudo git pull origin MOODLE_52_STABLE
    ```
  - Apply OS and PHP updates regularly.

- **Cron job monitoring**  
  Cron must run every minute. Check logs:
  ```bash
  tail -f /var/www/moodle/moodledata/moodle.log
  ```

- **Logging & monitoring**  
  - Enable Nginx access/error logs.  
  - Use `fail2ban` to block brute‑force attempts.  
  - Monitor `/var/log/secure` for suspicious activity.

---

## 🧰 Optional Advanced Hardening
- **TLS/HTTPS**  
  Use Let’s Encrypt with strong ciphers:
  ```bash
  sudo dnf install certbot python3-certbot-nginx -y
  sudo certbot --nginx -d yourdomain.com
  ```
- **PHP hardening**  
  In `/etc/php.ini`:
  ```
  expose_php = Off
  display_errors = Off
  session.cookie_httponly = 1
  session.cookie_secure = 1   # if HTTPS enabled
  ```
- **Database hardening**  
  - Bind MariaDB to localhost (`bind-address=127.0.0.1` in `my.cnf`).  
  - Remove test DB and anonymous users.  
  - Restrict DB user privileges to only Moodle DB.

---

## ✅ Checklist
- [ ] OS updated and patched  
- [ ] Firewall allows only HTTP/HTTPS/SSH  
- [ ] SELinux enforcing with correct contexts  
- [ ] Moodledata outside web root, permissions restricted  
- [ ] Strong DB/admin passwords  
- [ ] Cron running every minute  
- [ ] HTTPS enabled with Let’s Encrypt  
- [ ] PHP hardened (no info leaks, secure cookies)  
- [ ] Logs monitored, fail2ban active  

---
