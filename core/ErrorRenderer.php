<?php

declare(strict_types=1);

namespace Core;

use Jnana\Facades\View;
use Throwable;

/**
 * Renders safe, user-facing HTML error pages.
 *
 * Applications may override the default page by providing an errors/{status}
 * view. If that view is missing or fails to render, a standalone HTML page is
 * returned so error handling does not recursively fail.
 */
final class ErrorRenderer
{
    /**
     * @param int $statusCode HTTP status code.
     * @param string $message Safe, user-facing explanation; never pass raw exception details.
     * @param Throwable|null $exception Available to custom views for controlled logging/debug tooling.
     */
    public static function render(
        int $statusCode,
        string $message = '',
        ?Throwable $exception = null
    ): string {
        $details = self::detailsFor($statusCode);
        $message = trim($message) !== ''
            ? trim($message)
            : $details['message'];

        $view = "errors/{$statusCode}";

        try {
            if (View::exists($view)) {
                return View::render($view, [
                    'statusCode' => $statusCode,
                    'title' => $details['title'],
                    'message' => $message,
                    'description' => $details['description'],
                    'suggestions' => $details['suggestions'],
                    'exception' => $exception,
                ])->getBody();
            }
        } catch (Throwable) {
            // Error rendering must never trigger a second uncaught rendering error.
        }

        return self::fallbackHtml($statusCode, $message, $details);
    }

    /**
     * Provides plain-language descriptions and next steps for common HTTP errors.
     *
     * @return array{title:string,message:string,description:string,suggestions:list<string>}
     */
    private static function detailsFor(int $statusCode): array
    {
        $details = match ($statusCode) {
            400 => [
                'title' => 'Bad request',
                'message' => 'The request could not be understood.',
                'description' => 'Some of the information sent by your browser was missing or invalid.',
                'suggestions' => ['Return to the previous page and try again.', 'Check the information you entered before submitting.'],
            ],
            401 => [
                'title' => 'Sign-in required',
                'message' => 'Please sign in to continue.',
                'description' => 'You need to authenticate before this page or action can be accessed.',
                'suggestions' => ['Sign in with your account and try again.', 'If you are already signed in, your session may have expired.'],
            ],
            403 => [
                'title' => 'Access denied',
                'message' => 'You do not have permission to access this resource.',
                'description' => 'Your request was understood, but access is not permitted for this account.',
                'suggestions' => ['Check that you are signed in with the correct account.', 'Contact your administrator if you believe you should have access.'],
            ],
            404 => [
                'title' => 'Page not found',
                'message' => 'We could not find the page you requested.',
                'description' => 'The link may be outdated, the address may be incorrect, or the page may have moved.',
                'suggestions' => ['Check the address for typing mistakes.', 'Return to the home page and navigate to the page again.'],
            ],
            405 => [
                'title' => 'Method not allowed',
                'message' => 'This action is not supported for the requested address.',
                'description' => 'The address exists, but it does not accept this type of request.',
                'suggestions' => ['Return to the previous page and use the provided form or link.', 'If this continues, contact your application administrator.'],
            ],
            408 => [
                'title' => 'Request timed out',
                'message' => 'The request took too long to complete.',
                'description' => 'The server did not receive the complete request in time.',
                'suggestions' => ['Check your connection and try again.', 'If you were submitting a form, check whether the action completed before retrying.'],
            ],
            409 => [
                'title' => 'Request conflict',
                'message' => 'This action conflicts with the current state of the resource.',
                'description' => 'The information may have changed since you opened this page.',
                'suggestions' => ['Refresh the page to load the latest information.', 'Review your changes and submit again.'],
            ],
            419 => [
                'title' => 'Page expired',
                'message' => 'Your page or security token has expired.',
                'description' => 'For your protection, this request was not processed because the page security token is no longer valid.',
                'suggestions' => ['Refresh the page to obtain a new security token.', 'Review your form and submit it again.'],
            ],
            422 => [
                'title' => 'Unable to process details',
                'message' => 'Some of the submitted information needs attention.',
                'description' => 'The request was received, but one or more values could not be validated.',
                'suggestions' => ['Review the information you entered.', 'Correct any highlighted fields and submit again.'],
            ],
            429 => [
                'title' => 'Too many requests',
                'message' => 'You have made too many requests in a short period.',
                'description' => 'A temporary limit is in place to protect this application and its users.',
                'suggestions' => ['Wait a little while before trying again.', 'If you are signing in, check your credentials before your next attempt.'],
            ],
            500 => [
                'title' => 'Something went wrong',
                'message' => 'An unexpected error occurred while processing your request.',
                'description' => 'The application encountered a problem. Technical details have been hidden to protect your information.',
                'suggestions' => ['Try again in a moment.', 'If the problem continues, contact support and tell them what you were doing.'],
            ],
            502 => [
                'title' => 'Service temporarily unavailable',
                'message' => 'The application received an invalid response from an upstream service.',
                'description' => 'A service this application depends on may be temporarily unavailable.',
                'suggestions' => ['Wait a moment and try again.', 'If the problem continues, contact your administrator.'],
            ],
            503 => [
                'title' => 'Temporarily unavailable',
                'message' => 'This application is temporarily unavailable.',
                'description' => 'The service may be under maintenance or handling a high volume of requests.',
                'suggestions' => ['Wait a few minutes and try again.', 'If maintenance was announced, try again after the maintenance window.'],
            ],
            504 => [
                'title' => 'Service timed out',
                'message' => 'A service took too long to respond.',
                'description' => 'The request could not be completed within the expected time.',
                'suggestions' => ['Try again in a moment.', 'If the problem continues, contact your administrator.'],
            ],
            default => [
                'title' => 'Application error',
                'message' => 'The application could not complete your request.',
                'description' => 'Something unexpected prevented this page from loading.',
                'suggestions' => ['Return to the previous page and try again.', 'If the problem continues, contact your application administrator.'],
            ],
        };

        return [
            'title' => $details['title'],
            'message' => $details['message'],
            'description' => $details['description'],
            'suggestions' => $details['suggestions'],
        ];
    }

    /**
     * Renders the standalone fallback page without external assets or dependencies.
     *
     * @param array{title:string,message:string,description:string,suggestions:list<string>} $details
     */
    private static function fallbackHtml(
        int $statusCode,
        string $message,
        array $details
    ): string {
        $safeStatus = (string) $statusCode;
        $safeTitle = self::escape($details['title']);
        $safeMessage = self::escape($message);
        $safeDescription = self::escape($details['description']);

        $suggestions = '';
        foreach ($details['suggestions'] as $suggestion) {
            $suggestions .= '<li>' . self::escape($suggestion) . '</li>';
        }

        $year = date('Y');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>{$safeStatus} — {$safeTitle}</title>
    <style>
        :root {
            color-scheme: light;
            --page: #f4f7fb;
            --surface: #ffffff;
            --text: #182230;
            --muted: #5f6b7a;
            --border: #e4eaf1;
            --accent: #2457c5;
            --accent-hover: #1947a8;
            --soft: #edf3ff;
            --shadow: 0 24px 70px rgba(24, 34, 48, .09);
        }
        * { box-sizing: border-box; }
        html { min-height: 100%; background: var(--page); }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 32px 18px;
            display: grid;
            place-items: center;
            color: var(--text);
            background:
                radial-gradient(ellipse at top left, rgba(36, 87, 197, .08), transparent 36rem),
                var(--page);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .error-page { width: 100%; max-width: 760px; }
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 0 0 22px;
            color: #344054;
            font-size: .9rem;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            color: #fff;
            background: var(--accent);
            font-size: 1rem;
            font-weight: 800;
        }
        .card {
            overflow: hidden;
            border: 1px solid rgba(228, 234, 241, .9);
            border-radius: 22px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }
        .card-main { padding: clamp(26px, 6vw, 54px); }
        .status-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }
        .status-code {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 4px 11px;
            border: 1px solid #dbe6ff;
            border-radius: 999px;
            color: #214da9;
            background: var(--soft);
            font-size: .8rem;
            font-weight: 750;
            letter-spacing: .06em;
        }
        .status-label { color: var(--muted); font-size: .82rem; font-weight: 600; }
        h1 {
            max-width: 600px;
            margin: 0 0 14px;
            font-size: clamp(1.8rem, 5vw, 2.7rem);
            line-height: 1.14;
            letter-spacing: -.045em;
            font-weight: 760;
        }
        .message {
            margin: 0 0 10px;
            color: #344054;
            font-size: 1.08rem;
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .description {
            max-width: 600px;
            margin: 0;
            color: var(--muted);
            font-size: .98rem;
        }
        .help {
            margin-top: 30px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: #fbfcfe;
        }
        .help h2 { margin: 0 0 12px; font-size: .95rem; letter-spacing: -.01em; }
        .help ul { margin: 0; padding-left: 20px; color: var(--muted); }
        .help li { padding: 3px 0 3px 3px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .button {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            padding: 10px 17px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #344054;
            background: #fff;
            font-size: .92rem;
            font-weight: 700;
            text-decoration: none;
            transition: background .15s ease, border-color .15s ease;
        }
        .button:hover { background: #f8fafc; border-color: #cbd5e1; }
        .button-primary { color: #fff; border-color: var(--accent); background: var(--accent); }
        .button-primary:hover { color: #fff; border-color: var(--accent-hover); background: var(--accent-hover); }
        .card-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px 18px;
            padding: 17px clamp(26px, 6vw, 54px);
            border-top: 1px solid var(--border);
            color: #7b8796;
            font-size: .78rem;
        }
        @media (max-width: 520px) {
            body { padding: 20px 12px; }
            .card { border-radius: 16px; }
            .help { padding: 18px; }
            .actions { flex-direction: column; }
            .button { width: 100%; }
            .card-footer { flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="error-page">
        <div class="brand" aria-label="Application error page">
            <span class="brand-mark" aria-hidden="true">J</span>
            <span>Application notice</span>
        </div>
        <section class="card" aria-labelledby="error-title">
            <div class="card-main">
                <div class="status-row">
                    <span class="status-code">HTTP {$safeStatus}</span>
                    <span class="status-label">Request could not be completed</span>
                </div>
                <h1 id="error-title">{$safeTitle}</h1>
                <p class="message">{$safeMessage}</p>
                <p class="description">{$safeDescription}</p>
                <div class="help">
                    <h2>What you can do</h2>
                    <ul>{$suggestions}</ul>
                </div>
                <nav class="actions" aria-label="Error page actions">
                    <a class="button button-primary" href="/">Go to home page</a>
                    <a class="button" href="javascript:history.back()">Go back</a>
                </nav>
            </div>
            <footer class="card-footer">
                <span>If this keeps happening, contact your application administrator.</span>
                <span>© {$year}</span>
            </footer>
        </section>
    </main>
</body>
</html>
HTML;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
