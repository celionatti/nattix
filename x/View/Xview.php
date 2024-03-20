<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace X\View;

use Exception;
use X\Exception\XException;
use X\Resolver\PathResolver;

class Xview
{
    private array $data = [];
    private string $layout = 'default';
    private array $partials = [];
    private array $assets = ['css' => [], 'js' => []];
    private PathResolver $path;
    private PathResolver $urlPath;

    protected array $plugins = [];

    public function __construct(private readonly string $templatePath = 'templates/', private readonly string $layoutPath = 'layouts/')
    {
        $this->path = new PathResolver(get_root_dir());
        $this->urlPath = new PathResolver(URL_ROOT);
    }

    public function assign($key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function useLayout($layout): void
    {
        $this->layout = $layout;
    }

    public function addPartial($name, $partial): void
    {
        $this->partials[$name] = $partial;
    }

    public function addStyleSheet($path): void
    {
        $this->assets['css'][] = $path;
    }

    public function addScript($path): void
    {
        $this->assets['js'][] = $path;
    }

    /**
     * @throws Exception
     */
    public function render($template): void
    {
        $templatePath = $this->path->resolve() . $this->templatePath . $template . ".php";
        $layoutPath = $this->path->resolve() . $this->templatePath . $this->layoutPath . $this->layout . ".php";

        ob_start();
        extract($this->data);
        include $templatePath;
        $content = ob_get_clean();

        // Load the specified layout or use the default one
        if (file_exists($layoutPath)) {
            $layout = file_get_contents($layoutPath);

            // Replace {{content}} with the actual content
            $layout = str_replace('{{content}}', $content, $layout);

            // Replace {{partials}}
            foreach ($this->partials as $name => $partial) {
                $layout = str_replace('{{' . $name . '}}', $partial, $layout);
            }

            // Add stylesheets and scripts
            $layout = $this->addAssets($layout);

            echo $layout;
        } else {
            // Fallback to a default layout if the specified one is not found
            echo $content;
        }
    }

    private function addAssets($output): array|string
    {
        $cssLinks = '';
        foreach ($this->assets['css'] as $css) {
            $cssLinks .= '<link rel="stylesheet" href="' . $this->urlPath->resolve() . $css . '">';
        }

        $jsScripts = '';
        foreach ($this->assets['js'] as $js) {
            $jsScripts .= '<script src="' . $js . '"></script>';
        }

        // Replace {{stylesheets}} and {{scripts}} in the layout
        $output = str_replace('{{stylesheets}}', $cssLinks, $output);
        $output = str_replace('{{scripts}}', $jsScripts, $output);

        return $output;
    }

}