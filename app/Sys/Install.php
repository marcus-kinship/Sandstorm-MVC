<?
class Install
{
    private $actions = [];
    private $controller = "";
    private $routes = [];
    public $status = "";

    public function setup($arguments)
    {
        // Lägg till alla argument i actions array
        $arguments = func_get_args();
        $this->actions = $arguments;
    }

    public function controller($controllerAddress)
    {
        // Lägg till en controller-adress i controllers array
        $this->controller = $controllerAddress;
    }

    public function make()
    {
        // Loopa igenom actions och anropa config::get('setup.$value')
        foreach ($this->actions as $value) {
            $configValue = config::get("setup.$value");

            // bygga upp och installera app
            $data = $configValue->build($this->controller);

            // spara resultatet och fortsätt loopa
            if ($data !== false) {
                $this->setRoutes($data);
                $this->status = $value;
            } else {
                $this->status = $value;
                // Om det inte går, returnera false och avsluta loopen
                return false;
            }
        }
        // Om loopen slutfördes utan problem, returnera true
        return true;
    }
    private function setRoutes($routes)
    {
        // lägga till dem i $this->routes
        $this->routes = array_merge($this->routes, $routes);
    }

    public function getRoutes()
    {
        // Returnera sparade routes
        return $this->routes;
    }

    public function getActions()
    {
        // Returnera actions array
        return $this->actions;
    }

    public function getController()
    {
        // Returnera controllers array
        return $this->controller;
    }
}
