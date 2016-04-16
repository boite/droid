<?php

namespace Droid;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArgvInput;
use Droid\Model\Project;
use Droid\Loader\YamlProjectLoader;
use RuntimeException;

class Application extends ConsoleApplication
{
    protected $project;
    protected $autoLoader;
    protected $droidConfig;
    
    public function __construct($autoLoader)
    {
        $this->autoLoader = $autoLoader;
        parent::__construct();

        $this->setName('Droid');
        $this->setVersion('1.0.0');
        
        // extract --droid-config argument, before interpreting other arguments
        foreach ($_SERVER['argv'] as $i => $argument) {
            if (substr($argument, 0, 15)=='--droid-config=') {
                $this->droidConfig = substr($argument, 15);
                unset($_SERVER['argv'][$i]);
            }
        }

        $filename = $this->getDroidFilename();
        
        if (!file_exists($filename)) {
            //exit("ERROR: Droid configuration not found in " . $filename . "\nSOLUTION: Create a droid.yml file, or use --droid-config= to specify which droid.yml you'd like to use.\n");
        } else {
            $this->project = new Project($filename);
            $loader = new YamlProjectLoader();
            $loader->load($this->project, $filename);
        }

        $this->registerCustomCommands();

    }
    
    public function getProject()
    {
        if (!$this->hasProject()) {
            throw new RuntimeException("No project configured");
        }
        return $this->project;
    }
    
    public function hasProject()
    {
        return isset($this->project);
    }
    

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function registerCustomCommands()
    {
        // Keep the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        if ($this->hasProject()) {
            // Register commands defined in project's droid.yml
            foreach ($this->getProject()->getRegisteredCommands() as $registeredCommand) {
                $className = $registeredCommand->getClassName();
                $command = new $className();
                if ($registeredCommand->hasProperty('name')) {
                    $command->setName($registeredCommand->getProperty('name'));
                }
                $this->add($command);
            }
        }
            
        // Automatically register commands by scanning namespaces for a 'DroidPlugin' class.
        //print_r($this->autoLoader);
        $prefixes = $this->autoLoader->getPrefixesPsr4();
        
        foreach ($prefixes as $namespace => $paths) {
            $className = $namespace . 'DroidPlugin';
            if (class_exists($className)) {
                $plugin = new $className($this);
                $commands = $plugin->getCommands();
                foreach ($commands as $command) {
                    $this->add($command);
                }
            }
        }
        
        if ($this->hasProject()) {
            foreach ($this->getProject()->getTargets() as $target) {
                $command = new \Droid\Command\TargetRunCommand();
                $command->setName($target->getName());
                $command->setDescription("Run target: " . $target->getName());
                $command->setTarget($target->getName());
                $this->add($command);
                
                //print_r($target);
            }
        }
        //exit();
    }
    
    private function getDroidFilename()
    {
        if ($this->droidConfig) {
            $filename = $this->droidConfig;
            switch ($filename[0]) {
                case '/':
                    // absolute filename
                    break;
                case '~':
                    // relative to home
                    $home = getenv("HOME");
                    $filename = $home . '/' . substr($filename, 2);
                    break;
                default:
                    // relative from pwd/cwd
                    $filename = getcwd() . '/' . $filename;
                    break;
            }
        } else {
            // no parameters, assume 'droid.yml' in current working directory
            $filename = getcwd() . '/droid.yml';
        }
        return $filename;
    }
    
    public function setAutoLoader($autoLoader)
    {
        $this->autoLoader = $autoLoader;
        return $this;
    }
    
    public function getAutoLoader()
    {
        return $this->autoLoader;
    }
}
