<?php

namespace Jnana\Template;

final class TemplateCompiler
{
    private ?string $layout = null;
    private array $dependencies = [];

    public function compile(string $code, TemplateContext $context): string
    {
        // RESET STATE (CRITICAL)
        $this->layout = null;
        $this->dependencies = [];

        $code = $this->compileExtends($code);
        $code = $this->compileIncludes($code);
        $code = $this->compilePhpIncludes($code);
        $code = $this->compileBlocks($code, $context);

        // If layout exists → inject into layout
        if ($this->layout !== null) {
            $layoutCode = $this->compileIncludes($this->layout);
            $layoutCode = $this->compilePhpIncludes($layoutCode);
            $layoutCode = $this->compileYields($layoutCode, $context);
            $code = $layoutCode;
        } else {
            $code = $this->compileYields($code, $context);
        }

        $code = $this->compileEscapedEchos($code);
        $code = $this->compileEchos($code);
        $code = $this->compileStatements($code);

        return $code;
    }

    public function dependencies(): array
    {
        return array_unique($this->dependencies);
    }
    private function compileExtends(string $code): string
    {
        return preg_replace_callback(
            '/{% ?extends ?["\']?([\w\/\-\.]+)["\']? ?%}/',
            function ($m) {
                $layout = "/public/views/layout/{$m[1]}.layout.php";
                $this->dependencies[] = $layout;

                $this->layout = file_get_contents(base_path($layout)) ?: '';
                return '';
            },
            $code
        );
    }

    private function compileIncludes(string $code): string
    {
        return preg_replace_callback(
            '/{% ?include ?[\'"]?([\w\/\-\.]+)[\'"]? ?%}/',
            function ($m) {
                $file = "/public/views/component/{$m[1]}.com.php";
                $this->dependencies[] = $file;
                $path = base_path($file);
                return file_exists($path)
                    ? file_get_contents($path)
                    : '';
            },
            $code
        );
    }

    private function compileBlocks(string $code, TemplateContext $context): string
    {
        return preg_replace_callback(
            '/{% ?block ?(\w+) ?%}(.*?){% ?endblock ?%}/is',
            function ($m) use ($context) {
                $name = $m[1];
                $body = $m[2];

                if (str_contains($body, '@parent')) {
                    $body = str_replace(
                        '@parent',
                        $context->getBlock($name),
                        $body
                    );
                }

                $context->setBlock($name, $body);
                return '';
            },
            $code
        );
    }

    private function compileYields(string $code, TemplateContext $context): string
    {
        return preg_replace_callback(
            '/{% ?yield ?(\w+)(?:\s*\|\s*(.+?))? ?%}/',
            function ($m) use ($context) {
                $block = $m[1];
                $default = $m[2] ?? '';

                $content = trim($context->getBlock($block));

                if ($content !== '') {
                    return $content;
                }

                return $default !== ''
                    ? $default
                    : '';
            },
            $code
        );
    }

    private function compilePhpIncludes(string $code): string
    {
        return preg_replace_callback(
            '/{%\s*php_include\s+[\'"](.+?)[\'"]\s*%}/',
            function ($m) {

                $file = $m[1];

                $this->dependencies[] = $file;

                return "<?php include view_path('{$file}'); ?>";
            },
            $code
        );
    }

    private function compileEscapedEchos(string $code): string
    {
        return preg_replace(
            '/\{{{\s*(.+?)\s*}}}/',
            '<?php echo \\Library\\Template\\Escaper::escape($1); ?>',
            $code
        );
    }

    private function compileEchos(string $code): string
    {
        return preg_replace(
            '/\{{\s*(?!{)(.+?)\s*}}/',
            '<?php echo $1; ?>',
            $code
        );
    }

    private function compileStatements(string $code): string
    {
        $map = [
            '/{% if (.*?) %}/'      => '<?php if ($1): ?>',
            '/{% elseif (.*?) %}/' => '<?php elseif ($1): ?>',
            '/{% else %}/'         => '<?php else: ?>',
            '/{% endif %}/'        => '<?php endif; ?>',
            '/{% foreach (.*?) %}/'=> '<?php foreach ($1): ?>',
            '/{% endforeach %}/'   => '<?php endforeach; ?>',
        ];

        return preg_replace(
            array_keys($map),
            array_values($map),
            $code
        );
    }

}
