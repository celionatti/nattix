<?php

declare(strict_types=1);

/**
 * Framework Title: Natti-X
 * Author: Celio Natti
 * Copyright: X, 2024
 * Version: 1.0.0
 */

function add_action($hook, $callback, $priority = 10): void
{
    global $x_actions;

    $x_actions[$hook][] = array(
        'callback' => $callback,
        'priority' => $priority
    );
}

/**
 * Execute actions attached to a specific hook and return their return values.
 *
 * @param string $hook The name of the hook.
 * @param mixed ...$args Optional. Arguments passed to the callback functions.
 * @return array An array containing the return values of each callback function.
 */
function do_action(string $hook, ...$args): array
{
    global $x_actions;
    $return_values = [];

    if (isset($x_actions[$hook])) {
        $actions = $x_actions[$hook];

        // Sort actions by priority
        usort($actions, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        foreach ($actions as $action) {
            $return_values[] = call_user_func_array($action['callback'], $args);
        }
    }

    return $return_values;
}