#!/usr/bin/env bash
# CI acceptance: install the real keyless Catering artifact under /catering/
# without a rewrite router. This is NOT Apache/LiteSpeed or production proof.
set -euo pipefail

if [ "$#" -ne 1 ] || [ ! -f "$1" ]; then
  echo 'Usage: bash tools/verify-subfolder-artifact.sh <Catering-TEST-BUNDLE.zip>' >&2
  exit 2
fi

archive="$1"
workspace="$(mktemp -d /tmp/wc-subfolder-XXXXXX)"
site="$workspace/www/catering"
server_pid=""
cleanup() {
  if [ -n "$server_pid" ]; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  if [ -s "$workspace/php.log" ]; then
    # The server log includes request URLs and status codes, not POST bodies.
    echo '--- Subfolder PHP server status log ---'
    tail -n 35 "$workspace/php.log"
  fi
  rm -rf "$workspace"
}
trap cleanup EXIT

mkdir -p "$site"
unzip -q "$archive" -d "$site"

test -f "$site/index.php"
test -f "$site/install.php"
test -f "$site/install/index.php"
test -f "$site/.htaccess"
test -f "$site/vendor/autoload.php"
test -z "$(find "$site" -type f -iname '*.zip' -print -quit)"
test ! -e "$site/config/config.php"
php -r '
  $m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
  if(($m["id"]??null)!=="cajunwebpro/catering"||($m["version"]??null)!=="2.1.1"||isset($m["license_key"])){
    fwrite(STDERR,"Catering 2.1.1 keyless package manifest missing or unexpected\n"); exit(1);
  }' "$site/modules/industries/industry-catering/addon.json"

# Native CI matrix runs MySQL 8.4 and MariaDB 11.4 separately.
php -r '
  $db=new PDO("mysql:host=127.0.0.1;port=3306","root","");
  $db->exec("CREATE DATABASE IF NOT EXISTS wc_subfolder_acceptance");'

# No dev-router.php and no web-server rewrite. Application URLs use
# index.php?p= or the index.php/PATH_INFO fallback.
php -S 127.0.0.1:8103 -t "$workspace/www" >"$workspace/php.log" 2>&1 &
server_pid="$!"
base='http://127.0.0.1:8103/catering'

code=""
for i in $(seq 1 40); do
  code="$(curl -sS -o "$workspace/install.html" -w '%{http_code}' \
    -D "$workspace/install.headers" -b "$workspace/installer.cookies" \
    -c "$workspace/installer.cookies" -H 'X-Forwarded-Proto: https' \
    "$base/" 2>/dev/null || true)"
  if [ "$code" = '200' ] && grep -q 'Install' "$workspace/install.html"; then
    break
  fi
  sleep 1
done
test "$code" = '200'
grep -q 'industry-catering' "$workspace/install.html"

csrf="$(sed -n 's/.*name="installer_csrf" value="\([^"]*\)".*/\1/p' "$workspace/install.html" | head -1)"
cookie="$(sed -n 's/^[Ss]et-[Cc]ookie: WCINSTALL=\([^;]*\).*/\1/p' "$workspace/install.headers" | tail -1)"
if [ -z "$cookie" ]; then
  cookie="$(awk '$6=="WCINSTALL"{print $7}' "$workspace/installer.cookies" | tail -1)"
fi
test -n "$csrf"
test -n "$cookie"

status="$(curl -sS -o "$workspace/installed.html" -w '%{http_code}' \
  -H 'X-Forwarded-Proto: https' --cookie "WCINSTALL=$cookie" \
  -c "$workspace/installer.cookies" -X POST "$base/" \
  --data-urlencode "installer_csrf=$csrf" \
  --data-urlencode 'site_name=Catering Subfolder CI' \
  --data-urlencode 'base_url=https://127.0.0.1:8103/catering' \
  --data-urlencode 'timezone=UTC' \
  --data-urlencode 'db_host=127.0.0.1' \
  --data-urlencode 'db_port=3306' \
  --data-urlencode 'db_name=wc_subfolder_acceptance' \
  --data-urlencode 'db_user=root' \
  --data-urlencode 'db_pass=' \
  --data-urlencode 'db_prefix=wc_' \
  --data-urlencode 'owner_username=ci-subfolder' \
  --data-urlencode 'owner_email=subfolder@example.test' \
  --data-urlencode 'owner_password=ci-subfolder-test-only-1' \
  --data-urlencode 'owner_password2=ci-subfolder-test-only-1' \
  --data-urlencode "storage_path=$site/storage")"
if [ "$status" != '200' ] || ! grep -q 'Installation complete' "$workspace/installed.html"; then
  echo "Subfolder installer did not finish: HTTP $status" >&2
  # Do not echo the HTML response: it may preserve submitted form values.
  exit 1
fi

test -f "$site/config/config.php"
test -f "$site/config/installed.lock"
test ! -e "$site/install.php"
test ! -d "$site/install"

# Database must contain the enabled migrated package, not just its files.
php -r '
  $pdo=new PDO("mysql:host=127.0.0.1;port=3306;dbname=wc_subfolder_acceptance","root","");
  $q=$pdo->query("SELECT version,state FROM wc_packages WHERE package_id=\"cajunwebpro/catering\"");
  $package=$q->fetch(PDO::FETCH_ASSOC);
  if(($package["version"]??"")!=="2.1.1"||($package["state"]??"")!=="enabled"){
    fwrite(STDERR,"Subfolder Catering package not registered and enabled\n"); exit(1);
  }
  if($pdo->query("SHOW TABLES LIKE \"wc_catering_inquiries\"")->rowCount()!==1){
    fwrite(STDERR,"Subfolder Catering migrations absent\n"); exit(1);
  }'

test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/index.php?p=/")" = '200'
test "$(curl -sS -o "$workspace/login-query.html" -w '%{http_code}' "$base/index.php?p=/admin/login")" = '200'
test "$(curl -sS -o "$workspace/login-pathinfo.html" -w '%{http_code}' "$base/index.php/admin/login")" = '200'
grep -Eq 'Sign in|Log in' "$workspace/login-query.html"
grep -q '/catering/index.php?p=' "$workspace/login-query.html"
test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/index.php?p=/unknown-subfolder-page")" = '404'
test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/cron.php")" = '403'
test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/install.php")" = '404'
test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/assets/images/cajunwebpro-logo.png")" = '200'

# No credentials, license contents or private paths may appear in the report.
php tools/host-preflight.php "$site" > "$workspace/preflight.json"
php -r '
  $report=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
  if(($report["core_version"]??null)!=="2.6.2"
    || ($report["installation_state"]??"")!=="marker_and_config_present"
    || ($report["industries"][0]["version"]??"")!=="2.1.1"
    || ($report["checks"]["install_php_present"]??true)
    || ($report["checks"]["install_dir_present"]??true)) {
    fwrite(STDERR,"Subfolder installed-artifact preflight failed\n"); exit(1);
  }' "$workspace/preflight.json"

echo "Subfolder no-rewrite acceptance OK: real Catering 2.1.1 ZIP, installer, database, URLs, assets, cleanup."
