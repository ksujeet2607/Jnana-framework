<?php
if (! function_exists('view')) {
    function view(string $template, array $data = []):void
    {
        \Library\Utilities\View::render($template, $data);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        if (!isset($_SESSION['_old_input'])) {
            return $default;
        }

        $data = $_SESSION['_old_input'];

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        if ($data instanceof \DateTime) {
            return $data->format(getenv('DATE_FORMAT').' '.getenv('TIME_FORMAT'));
        }

        if (is_array($data)) {
            return $data;
        }

        return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('has_old')) {
    function has_old(string $key): bool
    {
        return isset($_SESSION['_old_input'][$key]);
    }
}

if (!function_exists('esc')) {
    function esc($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}