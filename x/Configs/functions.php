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
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }

        .sf-dump-container {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two equal-width columns */
            height: 100vh;
            overflow: hidden;
        }

        .sf-dump {
            font: 13px Menlo, Monaco, monospace;
            direction: ltr;
            text-align: left;
            white-space: pre;
            word-wrap: normal;
            background: #282828;
            color: #eeeeee;
            line-height: 1.2;
            margin: 0;
            padding: 16px;
            border-radius: 5px;
            overflow: auto; /* Enable scrolling */
            max-height: calc(100vh - 32px); /* Limit height and allow scrolling */
            z-index: 100000;
            grid-column: 2; /* Specify the column for the dump content */
        }

        .sf-dump-two {
            background-color: #dc3545; /* Error background color */
            color: #ffffff; /* Error text color */
            line-height: 1.5;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 100000;
            grid-column: 1; /* Specify the column for the error content */
            text-align: center;
        }

        /* Style for link inside error content */
        .sf-dump-two a {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Style for link hover effect */
        .sf-dump-two a:hover {
            color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="sf-dump-container">
        <div class="sf-dump-two">
            <h2>Natti-X Error Dumper</h2>
            <p>Oops! Something went wrong while processing your request.</p>
            <p>Please <a href="#">click here</a> to go back to the previous page.</p>
        </div>
        <pre class="sf-dump">
            <h4 class="sf-dump-public"><a>DETAILS</a></h4>
HTML;

    var_dump($value);

    echo <<<HTML
        </pre>
    </div>
</body>
</html>
HTML;

    die;
}