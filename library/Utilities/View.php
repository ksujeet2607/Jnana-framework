<?php

namespace Library\Utilities;

use Library\Exceptions\ViewNotFoundException;
use Library\Http\Response;
use Library\Template\TemplateCache;
use Library\Template\TemplateCompiler;
use Library\Template\TemplateRenderer;

class View
{
    private array $data = [];

    public function render(string $view, array $parameters = []): Response
    {
        if (!$this->exists($view)) {
            throw new ViewNotFoundException("View '{$view}' not found");
        }

        $templatePath = view_path($this->normalize($view));

        $mergedParameters = array_merge(
            $this->data,
            $parameters
        );

        $cachePath = cache_path();

        $compiler = new TemplateCompiler();
        $cache    = new TemplateCache(
            $cachePath,
            filter_var(getenv('CACHING') ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        $renderer = new TemplateRenderer($compiler, $cache);

        return \Library\Facades\Response::setBody($renderer->render($templatePath, $mergedParameters));
       
    }


    public function share(array $data): void
    {
        $this->$data = array_merge($this->$data, $data);
    }

    public function exists(string $view): bool
    {
        $path = view_path($this->normalize($view));
        return is_file($path);
    }

    private function normalize(string $view): string
    {
        $view = str_replace('.', DIRECTORY_SEPARATOR, $view);

        if (!str_ends_with($view, '.php')) {
            $view .= '.php';
        }

        return ltrim($view, '/');
    }

}


