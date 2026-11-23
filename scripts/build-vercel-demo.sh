#!/usr/bin/env bash
# Membangun paket data DEMO untuk Vercel: database/demo.sqlite + database/demo-storage/
# Jalankan ulang bila ingin menyegarkan tanggal data dummy:  bash scripts/build-vercel-demo.sh
set -euo pipefail
cd "$(dirname "$0")/.."

WORK=$(mktemp -d)
mkdir -p "$WORK/storage/framework/"{views,cache,sessions} "$WORK/storage/logs" "$WORK/storage/app/private"
rm -f database/demo.sqlite && touch database/demo.sqlite

export DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/demo.sqlite" LARAVEL_STORAGE_PATH="$WORK/storage" \
       QUEUE_CONNECTION=sync MAIL_MAILER=log CACHE_STORE=array APP_ENV=local
php artisan migrate:fresh --seed --force

# Hanya media yang dirujuk database; video cukup 360p & 480p (batas respons Vercel 4,5 MB)
php -r '
$db = new PDO("sqlite:database/demo.sqlite");
foreach ($db->query("SELECT id, storage_key, renditions FROM files") as $f) {
    $r = json_decode($f["renditions"] ?? "null", true) ?: [];
    unset($r["720p"]);
    $db->prepare("UPDATE files SET renditions = ? WHERE id = ?")->execute([$r ? json_encode($r) : null, $f["id"]]);
}'
rm -rf database/demo-storage && mkdir -p database/demo-storage
php -r '
$db = new PDO("sqlite:database/demo.sqlite");
$src = getenv("LARAVEL_STORAGE_PATH")."/app/private/";
foreach ($db->query("SELECT storage_key, renditions FROM files") as $f) {
    $keys = [];
    $r = json_decode($f["renditions"] ?? "null", true) ?: [];
    foreach ($r as $k => $v) { foreach ((array) $v as $p) $keys[] = $p; }
    if (str_ends_with($f["storage_key"], ".pdf")) $keys[] = $f["storage_key"]; // PDF asli kecil
    foreach ($keys as $k) {
        if (!is_file($src.$k)) continue;
        @mkdir(dirname("database/demo-storage/".$k), 0777, true);
        copy($src.$k, "database/demo-storage/".$k);
    }
}'
sqlite3 database/demo.sqlite "VACUUM;" 2>/dev/null || php -r '(new PDO("sqlite:database/demo.sqlite"))->exec("VACUUM");'
rm -rf "$WORK"
du -sh database/demo.sqlite database/demo-storage
