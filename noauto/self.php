<?php

function pcAutoLoad($class)
{
    $name = $class . '.php';
    $base = __DIR__ . '/../';
    $file = pcFindFile($base, $name);
    if ($file) {
        include($file);
    }
}

function pcFindFile($dir, $name)
{
    $dh = opendir($dir);
    while (($file=readdir($dh)) !== false) {
        if ($file[0] === '.' || $file === 'noauto' || $file === 'vendor') {
            continue;
        } elseif ($file === $name) {
            return $dir . DIRECTORY_SEPARATOR . $file;
        }

        if (is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
            $find = pcFindFile($dir . DIRECTORY_SEPARATOR . $file, $name);
            if (basename($find) === $name) {
                return $find;
            }
        }
    }

    return false;
}

spl_autoload_register('pcAutoLoad');

