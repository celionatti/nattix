<?php

namespace Nattix\Plugins\xhomepage\controllers;

use Exception;
use X\Controller;

class XHomepageController extends Controller
{
    public function onConstruct(): void
    {
        $this->view->addStyleSheet("assets/css/style.css");
        $this->view->addScript("assets/js/scripts.js");
    }

    /**
     * @throws Exception
     */
    public function index(): void
    {
        $data = [

        ];
        $this->view->assign('view', $data);

        $this->view->render('welcome');
    }
}