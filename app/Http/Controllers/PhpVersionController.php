<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PhpVersionController extends Controller
{
    public function index()
    {
        return view('php.index');
    }

    public function show(string $version)
    {
        $viewName = 'php.' . str_replace('.', '_', $version);

        if (!view()->exists($viewName)) {
            abort(404);
        }

        $examples = [];

        if ($version === '8.0') {
            $examples = $this->getPhp80Examples();
        }

        return view($viewName, compact('examples'));
    }

    /**
     * Выполнение кода фич PHP 8.0
     */
    private function getPhp80Examples(): array
    {
        $statusCode = 200;

        $statusMessage = match ($statusCode) {
            200, 300 => 'Успех или Редирект',
            400, 404 => 'Ошибка клиента',
            500 => 'Ошибка сервера',
            default => 'Неизвестный статус',
        };

        return [
            'match_expression' => [
                'input' => $statusCode,
                'result' => $statusMessage,
            ]
        ];
    }
}
