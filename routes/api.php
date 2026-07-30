<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Projeto 2 - Back-end (Route API)
|--------------------------------------------------------------------------
| Recebe o form-data do Projeto 1 (2 arquivos + nome + email), guarda os
| arquivos e despacha o ProcessFileJob para a FILA do Redis.
*/
Route::post('/process-files', [FileController::class, 'process']);
