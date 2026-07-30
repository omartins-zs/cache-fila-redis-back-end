<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFileJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    /**
     * Projeto 2 - Recebe o form-data do Projeto 1, guarda os arquivos
     * enviados e despacha o Job para a FILA do Redis. Retorna 200 na hora
     * (processamento e assincrono).
     */
    public function process(Request $request): JsonResponse
    {
        Log::info('🟢 Recebendo requisição para processar Arquivo', $request->except(['txt_file', 'csv_file']));

        try {
            $validated = $request->validate([
                'nome'     => 'required|string',
                'email'    => 'required|email',
                'txt_file' => 'required|file|mimes:txt',
                'csv_file' => 'required|file|mimes:csv,txt',
            ]);

            // Os arquivos temporarios do upload NAO sobrevivem ate o Job rodar
            // na fila, entao guardamos agora e passamos os caminhos + nomes
            // originais para o Job.
            $files = [
                'txt' => [
                    'path'          => $request->file('txt_file')->store('uploads'),
                    'original_name' => $request->file('txt_file')->getClientOriginalName(),
                ],
                'csv' => [
                    'path'          => $request->file('csv_file')->store('uploads'),
                    'original_name' => $request->file('csv_file')->getClientOriginalName(),
                ],
            ];

            // 🚀 Despacha para a FILA do Redis (QUEUE_CONNECTION=redis)
            ProcessFileJob::dispatch($validated['nome'], $validated['email'], $files);

            Log::info('🚀 [FileController@process] Job despachado para a fila do Redis');

            return response()->json([
                'status'  => 'success',
                'message' => 'Arquivos recebidos. Processamento enfileirado com sucesso.',
                'data'    => null,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('🔴 Erro de validação na entrada', ['erros' => $e->errors()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Dados inválidos.',
                'data'    => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('❌ FileController@process erro', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Erro ao processar: ' . $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }
}
