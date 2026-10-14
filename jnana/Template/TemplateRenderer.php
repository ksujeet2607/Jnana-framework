<?php

namespace Jnana\Template;


final class TemplateRenderer
{
    public function __construct(
        private TemplateCompiler $compiler,
        private TemplateCache $cache
    ) {}

    public function render(string $view, array $data = []): string
    {

        $context = new TemplateContext($data);

        $compiled = $this->cache->get(
            $view,
            function () use ($view, $context) {
                $code = file_get_contents($view);
                $compiled = $this->compiler->compile($code, $context);

                return [$compiled, $this->compiler->dependencies()];
            }
        );
        return $this->evaluate($compiled, $context->data());
    }

    private function evaluate(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
