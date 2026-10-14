<?php

namespace Core\CLI\Commands;

use Core\ApplicationPaths;
use Jnana\Utilities\Utility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateControllerCommand extends Command
{
    use Utility;

    protected static $defaultName = 'make:controller';

    protected function configure(): void
    {
        $this
            ->setDescription('Generates a boilerplate controller.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $controllerName = preg_replace('/Controller$/i', '', ucfirst($name)) . 'Controller';
        $controllerPath = ApplicationPaths::instance()->app('app/Controllers/' . $controllerName . '.php');

        if (file_exists($controllerPath)) {
            $output->writeln("<error>Controller '$controllerName' already exists.</error>");
            return Command::FAILURE;
        }

        // Ensure directory exists
        $dir = dirname($controllerPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $template = $this->getControllerTemplate($controllerName);

        try {
            file_put_contents($controllerPath, $template);
            $output->writeln("<fg=green>Controller '$controllerName' created successfully at '$controllerPath'.</>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create controller: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function getControllerTemplate(string $controllerName): string
    {
        $routeGroup = $this->toResourceName($controllerName);
        return <<<PHP
<?php

namespace App\Controllers;

use Jnana\Routes\Get;
use Jnana\Routes\RouteGroup;

#[RouteGroup('/$routeGroup')]
final class $controllerName extends BaseController
{
    #[Get('')]
    public function index()
    {
        return \$this->response->setBody('Welcome to $controllerName!');
    }
}
PHP;
    }
}
