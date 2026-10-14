<?php

namespace Core\CLI\Commands;

use Core\ApplicationPaths;
use Jnana\Utilities\Utility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateMiddlewareCommand extends Command
{
    use Utility;
    protected function configure(): void
    {
        $this
            ->setName('make:middleware')
            ->setDescription('Generates a boilerplate middleware.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $middlewareName = ucfirst($name) . 'Middleware';
        $middlewarePath = ApplicationPaths::instance()->app('app/Middlewares/' . $middlewareName . '.php');

        if (file_exists($middlewarePath)) {
            $output->writeln("<error>Controller '$middlewareName' already exists.</error>");
            return Command::FAILURE;
        }

        $dir = dirname($middlewarePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $output->writeln("<error>Unable to create middleware directory: {$dir}</error>");
            return Command::FAILURE;
        }

        $template = $this->getMiddlewareTemplate($middlewareName);

        try {
            file_put_contents($middlewarePath, $template);
            $output->writeln("<info>Middleware '$middlewareName' created successfully at '$middlewarePath'.</info>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create middleware: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function getMiddlewareTemplate(string $middlewareName): string
    {
        $routeGroup = $this->toResourceName($middlewareName);
        return <<<PHP
<?php

namespace App\Middlewares;

use Jnana\Http\Request;
use Jnana\Http\Response;
use Jnana\Middleware\Middleware;
use Jnana\Middleware\MiddlewareInterface;

class {$middlewareName} extends Middleware implements MiddlewareInterface
{

    /**
     * @inheritDoc
     */
    public function process(Request \$request, Response \$response, callable \$next): Response
    {
        // Proceed to the next middleware or handler
        return \$next(\$request, \$response);
    }
}
PHP;
    }
}