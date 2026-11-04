#!/usr/bin/env bash
# Verify HTTP deny/allow rules against the actual extracted Core artifact
# on an isolated loopback-only Apache 2.4 instance, with rewrite on and off.
# This tests static access control, NOT PHP-FPM, login, or hosted licensing.
set -euo pipefail

if [ "$#" -ne 1 ] || [ ! -d "$1" ]; then
  echo 'Usage: bash tools/verify-apache-guards.sh <extracted COMPLETE-UPLOAD directory>' >&2
  exit 2
fi
site="$(realpath "$1")"
for rel in .htaccess vendor/.htaccess vendor/autoload.php config/config.example.php includes/bootstrap.php core/Install/Installer.php admin/views/layouts/admin.php themes/base/theme.json assets/images/cajunwebpro-logo.png; do
  test -f "$site/$rel" || { echo "Missing packaged access-control probe: $rel" >&2; exit 1; }
done
command -v apache2 >/dev/null || { echo 'Apache 2.4 required' >&2; exit 2; }
command -v curl >/dev/null || exit 2

work="$(mktemp -d /tmp/wc-apache-acceptance-XXXXXX)"
config="$work/apache.conf"
port=18115
active=0
stop_server() {
  if [ "$active" -eq 1 ]; then
    apache2 -f "$config" -k stop >/dev/null 2>&1 || true
    active=0
  fi
}
cleanup() {
  stop_server
  # Preserve no host paths, config, logs, or credentials as public artifacts.
  rm -rf "$work"
}
trap cleanup EXIT

make_config() {
  local mode="$1"
  cat > "$config" <<APACHE
ServerRoot "/etc/apache2"
ServerName localhost
Listen 127.0.0.1:$port
DefaultRuntimeDir "$work"
PidFile "$work/apache.pid"
ErrorLog "$work/error.log"
LogLevel warn
User www-data
Group www-data
APACHE
  # Explicitly exclude any globally enabled rewrite module, so we can
  # run one real Apache process without it and one with it.
  for f in /etc/apache2/mods-enabled/*.load; do
    [ -f "$f" ] || continue
    [ "$(basename "$f")" = 'rewrite.load' ] && continue
    printf 'Include %s\n' "$f" >> "$config"
  done
  if [ "$mode" = 'rewrite' ]; then
    echo 'LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so' >> "$config"
  fi
  cat >> "$config" <<APACHE
IncludeOptional /etc/apache2/mods-enabled/*.conf
TypesConfig /etc/mime.types
DocumentRoot "$site"
<Directory />
  Require all denied
</Directory>
<Directory "$site">
  Options FollowSymLinks
  AllowOverride All
  Require all granted
</Directory>
# A second URL location exercises the *same* package under a subfolder.
Alias /catering/ "$site/"
<FilesMatch "^\\.ht">
  Require all denied
</FilesMatch>
APACHE
}

probe() {
  local route="$1" expected="$2" code
  code="$(curl --noproxy '*' --silent --show-error --max-time 8 \
    -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port$route")"
  if [ "$code" != "$expected" ]; then
    echo "Apache guard failure: $route expected HTTP $expected, got $code" >&2
    exit 1
  fi
}

for mode in rewrite no-rewrite; do
  make_config "$mode"
  apache2 -f "$config" -t
  apache2 -f "$config" -k start
  active=1
  for i in $(seq 1 40); do
    if curl --noproxy '*' --silent --max-time 2 -o /dev/null \
      "http://127.0.0.1:$port/assets/images/cajunwebpro-logo.png"; then break; fi
    sleep 0.2
  done
  for prefix in '' '/catering'; do
    probe "$prefix/assets/images/cajunwebpro-logo.png" 200
    probe "$prefix/vendor/autoload.php" 403
    probe "$prefix/config/config.example.php" 403
    probe "$prefix/includes/bootstrap.php" 403
    probe "$prefix/core/Install/Installer.php" 403
    probe "$prefix/admin/views/layouts/admin.php" 403
    probe "$prefix/themes/base/theme.json" 403
    probe "$prefix/.htaccess" 403
  done
  echo "Apache 2.4 guards pass: $mode, root and /catering/ (16 HTTP assertions)"
  stop_server
done
