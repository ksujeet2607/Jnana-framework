<?php
if (!function_exists('pre')) {
    function pre(mixed $data): void
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}