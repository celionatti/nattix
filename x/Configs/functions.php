<?php

declare(strict_types=1);

use JetBrains\PhpStorm\NoReturn;

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

function get_root_dir()
{
    // Get the current file's directory
    $currentDirectory = __DIR__;

    // Navigate up the directory tree until you reach the project's root
    while (!file_exists($currentDirectory . '/vendor')) {
        // Go up one level
        $currentDirectory = dirname($currentDirectory);

        // Check if you have reached the filesystem root (to prevent infinite loop)
        if ($currentDirectory === '/') {
            echo "Error: Project root not found.\n";
            exit(1);
        }
    }

    return $currentDirectory;
}

function x_die($value, $die = true): void
{
    echo "<pre style='background:#282828; color:#52e3f6; padding:16px;border-radius:6px;overflow:hidden;word-wrap:normal;font: 12px Menlo, Monaco, monospace;text-align: left;white-space: pre;direction: ltr;line-height: 1.2;z-index: 100000;margin:0;font-size:15px;margin-bottom:5px;'>";
    var_dump($value);
    echo "</pre>";

    if ($die) {
        die;
    }
}