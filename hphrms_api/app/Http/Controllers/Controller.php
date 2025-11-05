<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Support\Validator;

abstract class Controller
{
    protected function json($data, int $status = 200): Response
    {
        return new Response($data, $status);
    }

    protected function validate(Request $request, array $rules): ?Response
    {
        $errors = Validator::make($request->all(), $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        return null;
    }
}
