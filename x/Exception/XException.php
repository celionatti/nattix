<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X XException
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\Exception;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use X\X;

class XException extends Exception
{
    protected string $errorLevel = "error";
    protected int $maxBytes = 1048576;

    #[NoReturn] public function __construct(string $message = "", int $code = 0, string $errorLevel = 'error', string $sql = '', array $params = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $validErrorLevels = ['error', 'info', 'critical', 'generic'];
        if (in_array($errorLevel, $validErrorLevels, true)) {
            $this->errorLevel = $errorLevel;
        } else {
            $this->errorLevel = 'error'; // Default to error if invalid error level is provided
        }

        $this->logError($sql, $params);
        $this->displayError();
    }

    protected function logError(string $sql, array $params): void
    {
        $errorMessage = "[" . date("Y-m-d H:i:s") . "] ";
        $errorMessage .= "[" . strtoupper($this->errorLevel) . "] ";
        $errorMessage .= $this->getMessage() . "\n";

        $errorLogDir = X::$x->pathResolver->resolve() . 'logs' . DIRECTORY_SEPARATOR;

        if (!file_exists($errorLogDir)) {
            mkdir($errorLogDir, 0777, true);
        }

        $logDir = $errorLogDir . strtolower($this->errorLevel) . DIRECTORY_SEPARATOR;

        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logMessage = "[" . date('Y-m-d H:i:s') . "] Error: $errorMessage";
        $logFilePath = $logDir . strtolower($this->errorLevel) . '.log';

        if (file_exists($logFilePath) && filesize($logFilePath) >= $this->maxBytes) {
            unlink($logFilePath);
        }

        error_log($logMessage . PHP_EOL, 3, $logFilePath);
    }

    #[NoReturn] protected function displayError(): void
    {
        $style = $this->getStyle();

        $file = $this->getFile();
        $line = $this->getLine();

        $html = $this->generateErrorHtml($style, $file, $line);

        echo $html;
        exit(1);
    }

    protected function getStyle(): string
    {
        $styles = [
            'error' => 'background-color: tomato; color: #FFFFFF;',
            'warning' => 'background-color: #FFA500; color: #000000;',
            'info' => 'background-color: #007BFF; color: #FFFFFF;',
            'critical' => 'background-color: #FF0000; color: #FFFFFF; font-weight: bold;',
        ];

        return $styles[$this->errorLevel] ?? '';
    }

    protected function generateErrorHtml(string $style, string $file, int $line): string
    {
        return <<<HTML
        <html lang="en-us">
        <head><title>Natti-x Error</title></head>
        <style>
        body {
          margin: 0;
          padding: 0;
          background-color: #F0F0F0;
        }
        .error-container {
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100vh;
        }
        .error-box {
          background-color: #FFF;
          width: 80%;
          max-width: 600px;
          border: 1px solid #E0E0E0;
          border-radius: 5px;
          padding: 20px;
          text-align: center;
        }
        h2 {
          text-transform: uppercase;
          color: #333;
        }
        .error-details {
          text-align: left;
          padding: 10px;
        }
        </style>
        </head>
        <body>
        <div class="error-container">
        <div class="error-box">
        <h2>Natti-x Error</h2>
        <div style="{$style}border-radius: 5px; padding: 10px; margin-top: 10px;">
        <strong>{$this->errorLevel}:</strong> {$this->getMessage()}
        <p><strong>File:</strong> {$file}</p>
        <p><strong>Line:</strong> {$line}</p>
        </div>
        <div class="error-details">
        <p style="text-align:center;">copyright X.</p>
        </div>
        </div>
        </div>
        </body>
        </html>
        HTML;
    }
}