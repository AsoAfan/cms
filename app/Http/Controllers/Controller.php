<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithTables;

abstract class Controller
{
    use InteractsWithTables;
}
