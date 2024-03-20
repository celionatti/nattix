<?php

declare(strict_types=1);

global $x;

use Nattix\controllers\SiteController;
use Nattix\Plugins\xhomepage\controllers\XHomepageController;
use X\X;

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */


$x->router::get("/", [XHomepageController::class, 'index']);