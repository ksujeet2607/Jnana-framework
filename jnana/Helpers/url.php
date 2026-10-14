<?php
if (! function_exists('base_uri')) {
    function base_uri():string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        return $protocol . "://" . $host . $scriptDir . "/";
    }

}

if (! function_exists('url')) {
    function url(string $url):string
    {
        $baseUri = base_uri();

        if(substr($url, 0, strlen($baseUri)) === $baseUri){
            $url = substr($url, strlen($baseUri));
        }

        $url = ltrim($url, '/');

        return $baseUri.$url;

    }

}

if (! function_exists('asset_url')) {
    function asset_url(string $path):string
    {
        $assetDir = 'public/assets/';

        if(substr($path, 0, strlen($assetDir)) === $assetDir){
            $path = substr($path, strlen($assetDir));
        }

        $path = ltrim($path, '/');

        if (is_file(public_path('assets/' . $path))) {
            return base_uri().$assetDir.$path;
        }

        return $path;

    }

}

if (! function_exists('public_uri')) {
    function public_uri():string
    {
        return  getenv('BASE_URL') . getenv('PUBLIC_URL');
    }
}

if (! function_exists('service_uri')) {
    function service_uri():string
    {
        return getenv('SERVICE_URL');
    }
}

if (! function_exists('absolute_path')) {
    function absolute_path(string $path):string
    {
        return base_path(ltrim($path, '/\\'));

    }

}