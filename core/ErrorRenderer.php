<?php

namespace Core;

use Jnana\Facades\View;
use Throwable;

final class ErrorRenderer
{
    public static function render(
        int $statusCode,
        string $message = '',
        ?Throwable $exception = null
    ): string {

        $view = "errors/{$statusCode}";

        try {
            if (View::exists($view)) {
                return View::render($view, [
                    'message' => $message,
                    'exception' => $exception,
                ])->getBody();
            }
        } catch (Throwable $e) {
            // swallow rendering errors completely
        }

        return self::fallbackHtml($statusCode, $message);
    }

    private static function fallbackHtml(int $statusCode, string $message): string
    {
        $title = match ($statusCode) {
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            //503 => '🚧 Under Maintenance',
            503 => 'Application Under Maintenance',
            default => 'Application Error',
        };

        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>{$statusCode} {$title}</title>
            <style>
            body {
                font-family: Arial, sans-serif;
                background-color: rgb(215 210 210 / 10%);
                text-align: center; 
                display: flex;
                align-items: center;
                justify-content: center; 
                min-height: calc(100vh - 100px);
            }
            .container {
                max-width: 600px;
                width: 80%;
                background: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
                word-break: break-word;
            }
            
            h1 {
             margin-top: 0;
             font-size: 2rem;
             color: #444242; 
            }
            p {
             font-size: 1rem;
             color: #444242;
            }
            a { color: #444242; font-weight: bold; }
            </style>
        </head>
        <body>
        <div class="container">
        <h1>{$statusCode} {$title}</h1>
        <p>{$safeMessage}</p> 
        </div>
        </body>
        </html>
        HTML;
    }
}
