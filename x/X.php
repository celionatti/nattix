<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
*/

namespace X;

use Throwable;
use X\Container\Container;
use X\Database\Database;
use X\HTTP\Request;
use X\HTTP\Response;
use X\Resolver\PathResolver;
use X\Router\Router;

class X
{
    public static X $x;
    public ?Controller $controller;
    public PathResolver $pathResolver;
    public PathResolver $assetsResolver;
    public Config $config;

    public Session $session;

    public Container $container;
    public Request $request;
    public Response $response;
    public Router $router;

    public Database $database;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->require_files();
        self::$x = $this;
        $this->pathResolver = new PathResolver(dirname(__DIR__));
        $this->assetsResolver = new PathResolver("nattix.test");
        $this->config = Config::getInstance();
        $this->session = Session::getInstance();
        $this->container = new Container();
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);

        $this->container->singleton("Database", function () {
            return new Database();
        });

        $this->database = $this->container->make("Database");
        var_dump($this->database);
    }

    private function require_files(): void
    {
        require __DIR__ . "/Configs/functions.php";
        $files = [
            __DIR__ . "/Configs/load.php",
            __DIR__ . "/Configs/plugins.php",
            __DIR__ . "/Configs/plugins-functions.php",
            get_root_dir() . "/configs/load.php",
            get_root_dir() . "/utils/functions.php"
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                require $file;
            } else {
                echo "<h1>Warning: File $file is missing or not found. Skipping...\n</h1>" . "<br>";
            }
        }
    }

    /**
     * @throws Throwable
     */
    public function run(): void
    {
        $this->router->resolve();
    }
}
