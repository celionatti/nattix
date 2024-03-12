<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

namespace Nattix\controllers;

use X\Controller;

class SiteController extends Controller
{
    public function onConstruct(): void
    {
        $this->view->addStyleSheet("assets/css/style.css");
        $this->view->addScript("assets/js/scripts.js");
    }

    public function index(): void
    {
        $data = [
            'name' => "Celio Natti",
            'age' => 27,
            'job' => "Web Dev"
        ];
        $this->view->assign('view', $data);

        $this->view->render('welcome');
    }

    public function users(): void
    {
        $this->view->render('welcome');
    }
}