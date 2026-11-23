<?php

/**
 * Titik masuk Vercel (MODE DEMO TERBATAS).
 *
 * Vercel tidak punya disk permanen: hanya /tmp yang dapat ditulis dan isinya hilang saat
 * instance berhenti. Setiap instance baru menyalin database & media demo ke /tmp,
 * sehingga perubahan pengguna bersifat sementara dan berbeda antar-instance.
 */
$tmp = '/tmp/blj';
$storage = $tmp.'/storage';

if (! is_file($tmp.'/demo.sqlite')) {
    foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private'] as $dir) {
        @mkdir($storage.'/'.$dir, 0777, true);
    }
    $src = __DIR__.'/../database/demo-storage';
    if (is_dir($src)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $target = $storage.'/app/private/'.substr($item->getPathname(), strlen($src) + 1);
            $item->isDir() ? @mkdir($target, 0777, true) : copy($item->getPathname(), $target);
        }
    }
    copy(__DIR__.'/../database/demo.sqlite', $tmp.'/demo.sqlite.part');
    rename($tmp.'/demo.sqlite.part', $tmp.'/demo.sqlite');
}

$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

require __DIR__.'/../public/index.php';
