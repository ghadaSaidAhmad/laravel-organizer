<?php
namespace HZ\Laravel\Organizer\Console\Commands;

use File;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;

class ModuleBuilder extends Command
{    
    /**
     * Controller types
     * 
     * @const array
     */
    const CONTROLLER_TYPES = ['setter', 'getter', 'admin'];

    /**
     * Module directory path
     * 
     * @var string
     */
    protected $root;

    /**
     * The module name
     */
    protected $module;

    /**
     * Module info
     */
    protected $info = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module 
                                       {moduleName}
                                       {--controller=}
                                       {--type=getter}
                                       {--model=}
                                       {--data=}
                                       {--resource=}
                                       {--repository=}
                                       {--path=}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Module builder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->module = $this->argument('moduleName');

        $this->adjustOptionsValues();
    }

    /**
     * Adjust sent options and update its value if its default
     * 
     * @return void
     */
    protected function adjustOptionsValues() 
    {
        $this->root = config('organizer.root');
        $this->init();
        $this->create();
    }

    /**
     * Init data
     * 
     * @return void
     */
    protected function init()
    {
        $this->info('Preparing data...');
        $this->initController();
        $this->initModel();
        $this->initResource();
        $this->initRepository();
        $this->initData();
    }

    /**
     * Create files
     * 
     * @return void
     */
    protected function create()
    {
        $this->info('Creating controller file');
        $this->createController();
        $this->info('Creating resource file');
        $this->createResource();
        $this->info('Creating model file');
        $this->createModel();
        $this->info('Creating repository file');

        $this->createRepository();
        $this->info('Module has been created successfully');
    }

    /**
     * Some sections like repository and resource has the DATA constant
     * If the developer passed a list of data separated by comma it will be set there
     * 
     * @return void
     */
    protected function initData() 
    {
        $data = $this->option('data');

        if ($data) {
            $this->info['data'] = explode(',', $data);
        }
    }

    /**
     * Handle controller file
     * 
     * @return void
     */
    protected function initController()
    {
        $this->setData('controller');

        $controller = $this->info['controller'];

        $controllerPath = $this->option('path'); // the parent directory inside the Api directory

        if ($controllerPath) {
            $controller = "$controllerPath\\$controller";
        }

        $controllerType = $this->option('type');

        if (! in_array($controllerType, static::CONTROLLER_TYPES)) {
            throw new Exception(sprintf('Unknown controller type %s, available types: %s', $controllerType, implode(',', static::CONTROLLER_TYPES)));
        }

        $this->info['controller'] = $controller;
    }

    /**
     * Create controller file
     * 
     * @return void
     */
    protected function createController() 
    {
        $controller = $this->info['controller'];

        $controllerName = basename($controller);

        $controllerPath = dirname($controller);

        $controllerType = $this->option('type');

        $content = File::get($this->path("Http/Controllers/Api/Site/$controllerType.php"));        

        // replace controller name
        $content = str_ireplace("{$controllerType}Controller", "{$controllerName}Controller", $content);
        
        // replace controller path
        $content = str_ireplace("ControllerPath", $controllerPath, $content);

        // repository name 
        $content = str_ireplace('repo-name', $this->info['repositoryName'], $content);

        $controllerDirectory = base_path("app/Http/Controllers/Api/Site/$controllerPath");

        if (! File::isDirectory($controllerDirectory)) {
            File::makeDirectory($controllerDirectory, 0755, true);
        }

        // create the file
        $filePath = "$controllerDirectory/{$controllerName}Controller.php";

        $this->createFile($filePath, $content, 'Controller');

        // admin controller
        $this->info('Creating admin controller...');

        $content = File::get($this->path("Http/Controllers/Api/Admin/admin.php"));        

        // replace controller name
        $content = str_ireplace("AdminController", "{$controllerName}Controller", $content);
        
        // replace controller path
        $content = str_ireplace("ControllerPath", $controllerPath, $content);

        // repository name 
        $content = str_ireplace('repo-name', $this->info['repositoryName'], $content);

        $controllerDirectory = base_path("app/Http/Controllers/Api/Admin/$controllerPath");

        if (! File::isDirectory($controllerDirectory)) {
            File::makeDirectory($controllerDirectory, 0755, true);
        }

        // create the file
        $filePath = "$controllerDirectory/{$controllerName}Controller.php";

        $this->createFile($filePath, $content, 'Admin Controller');
    }

    /**
     * Create the file
     * 
     * @param  string $filePath
     * @param  string $content
     * $param  string $fileType
     * @return void
     */
    protected function createFile($filePath, $content, $fileType) 
    {
        $createFile = true;
        if (File::exists($filePath)) {
            $createFile = false;
            if ($this->confirm($fileType . ' exists, override it?')) {
                $createFile = true;
            }
        }

        if ($createFile) {
            File::put($filePath, $content);
        }
    }

    /**
     * Create the resource file
     * 
     * @return void
     */
    protected function createResource()
    {
        $resource = $this->info['resource'];

        $resourceName = basename($resource);

        $resourcePath = dirname($resource);

        $content = File::get($this->path("Http/Resources/resource.php"));        

        // make it singular 
        $resourceName = Str::singular($resourceName);

        // replace resource name
        $content = str_ireplace("ResourceName", "{$resourceName}", $content);
        
        // replace resource path
        $content = str_ireplace("ResourcePath", $resourcePath, $content);

        $dataList = '';

        if (! empty($this->info['data'])) {
            // add the id to the list if not provided
            if (! in_array('id', $this->info['data'])) {
                array_unshift($this->info['data'], 'id');
            }

            $dataList = "'" . implode("', '", $this->info['data']) . "'";
        }

        // replace resource data
        $content = str_ireplace("DATA_LIST", $dataList, $content);

        $resourceDirectory = base_path("app/Http/Resources/$resourcePath");

        if (! File::isDirectory($resourceDirectory)) {
            File::makeDirectory($resourceDirectory, 0755, true);
        }

        $this->info['resourcePath'] = $resourcePath . '\\' . $resourceName;

        // create the file
        $this->createFile("$resourceDirectory/{$resourceName}.php", $content, 'Resource');   
    }

    /**
     * Create the repository file
     * 
     * @return void
     */
    protected function createRepository()
    {
        $repository = $this->info['repository'];

        $repositoryName = basename($repository);

        $repositoryPath = dirname($repository);

        $content = File::get($this->path("Repositories/repository.php"));        

        // replace repository name
        $content = str_ireplace("RepositoryName", "{$repositoryName}", $content);
        
        // replace repository path
        $content = str_ireplace("RepositoryPath", $repositoryPath, $content);

        // replace model path
        $content = str_ireplace("ModelPath", $this->info['modelPath'], $content);

        // replace resource path
        $content = str_ireplace("ResourcePath", $this->info['resourcePath'], $content);

        // repository name 
        $content = str_ireplace('repo-name', $this->info['repositoryName'], $content);

        $dataList = '';

        if (! empty($this->info['data'])) {
            if (in_array('id', $this->info['data'])) {
                $this->info['data'] = Arr::remove('id', $this->info['data']);
            }
            
            $dataList = "'" . implode("', '", $this->info['data']) . "'";
        }

        // replace repository data
        $content = str_ireplace("DATA_LIST", $dataList, $content);

        $repositoryDirectory = base_path("app/Repositories/$repositoryPath");

        if (! File::isDirectory($repositoryDirectory)) {
            File::makeDirectory($repositoryDirectory, 0755, true);
        }

        // create the file
        $this->createFile("$repositoryDirectory/{$repositoryName}Repository.php", $content, 'Repository');  
    }

    /**
     * Create the model file
     * 
     * @return void
     */
    protected function createModel()
    {
        $model = $this->info['model'];

        $modelName = basename($model);

        $modelPath = dirname($model);

        $modelPath = array_map(function ($segment) {
            return Str::singular($segment);
        }, explode('\\', $modelPath));

        $modelPath = implode('\\', $modelPath);

        $content = File::get($this->path("Models/model.php"));        

        // make it singular 
        $modelName = Str::singular($modelName);

        // replace model name
        $content = str_ireplace("ModelName", "{$modelName}", $content);
        
        // replace model path
        $content = str_ireplace("ModelPath", $modelPath, $content);

        $modelDirectory = base_path("app/Models/$modelPath");

        if (! File::isDirectory($modelDirectory)) {
            File::makeDirectory($modelDirectory, 0755, true);
        }

        $this->info['modelPath'] = $modelPath . '\\' . $modelName;    
        // create the file
        $this->createFile("$modelDirectory/{$modelName}.php", $content, 'Model');
    }

    /**
     * Get relative path to base path
     * 
     * @param  string $path
     * @return string 
     */
    protected function path($path) 
    {
        return $this->root . '/module/' . $path;
    }

    /**
     * Create module model
     * 
     * @return void
     */
    protected function initModel() 
    {
        $this->setData('model');
    }

    /**
     * Create module resource
     * 
     * @return void
     */
    protected function initResource() 
    {
        $this->setData('resource');
    }
    
    /**
     * Create module repository
     * 
     * @return void
     */
    protected function initRepository() 
    {
        $this->setData('repository');

        $this->info['repositoryName'] = strtolower(basename($this->info['repository']));
    }

    /**
     * Set to the data container the value of the given option
     *
     * @param  string $option
     * @return void
     */
    protected function setData($option) 
    {
        // repository
        $optionValue = $this->option($option);

        $module = ucfirst($this->module);

        if (! $optionValue) {
            // get it from the module name
            $optionValue = "{$module}\\{$module}";
        }

        $this->info[$option] = str_replace('/', '\\', $optionValue);
    }
}