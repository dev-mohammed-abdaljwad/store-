<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function resolvePerPage(Request $request, int $default = 10, int $max = 200): int
    {
        $perPage = (int) $request->query('per_page', $default);

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }
}
