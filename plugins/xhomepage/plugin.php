<?php

declare(strict_types=1);

/**
 * File: X-Homepage Plugin
 */

global $x_actions;


add_action("x_header", "x_header");

function x_header($data): void
{
    x_die($data);
}