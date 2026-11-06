<?php
declare(strict_types=1);

namespace App\Controllers;

/** Thrown when a menu item points to a screen that is designed but not yet built. */
final class PlannedException extends \RuntimeException
{
    public function __construct(public readonly array $item)
    {
        parent::__construct('planned');
    }
}
