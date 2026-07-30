<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Projeto 2 - Job processado na FILA do Redis (QUEUE_CONNECTION=redis).
 *
 * Fluxo (processFile orquestra e loga cada passo):
 *   validateFiles -> saveToFile -> readFiles -> sendToExternalApi
 *
 * Duas formatacoes auxiliares:
 *   formatContent   -> deixa o conteudo minusculo e sem acentos
 *   generateFileName-> gera nome com timestamp + string aleatoria
 */
class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    /** Disco que simula o S3 salvando localmente. */
    private const DISK = 's3_local';

    public function __construct(
        private string $nome,
        private string $email,
        private array $files, // ['txt' => ['path','original_name'], 'csv' => [...]]
    ) {
    }

    public function handle(): void
    {
        Log::info('🚀 [ProcessFileJob] Iniciando processamento na fila', ['nome' => $this->nome]);

        try {
            $this->processFile();
            Log::info('✅ [ProcessFileJob] Processamento concluído', ['nome' => $this->nome]);
        } catch (\Throwable $e) {
            Log::error('❌ [ProcessFileJob] Falha no processamento', [
                'nome'    => $this->nome,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e; // relança para o worker registrar a falha (failed_jobs)
        }
    }

    /**
     * 1/5 - Orquestrador: chama todas as outras funções e loga cada passo.
     */
    private function processFile(): void
    {
        $validated = $this->validateFiles();          // valida + formata
        $saved     = $this->saveToFile($validated);   // salva no "S3" (local)
        $data      = $this->readFiles($saved);         // lê os arquivos salvos
        $this->sendToExternalApi($data);               // envia para o Projeto 3
    }

    /**
     * 2/5 - Valida os arquivos recebidos e devolve o conteúdo já formatado
     * (minúsculo/sem acentos) com um novo nome (timestamp + aleatório).
     */
    private function validateFiles(): array
    {
        Log::info('🟡 Validando arquivos...');

        $result = [];

        foreach ($this->files as $type => $file) {
            $rawPath      = $file['path'];
            $originalName = $file['original_name'];

            if (! Storage::exists($rawPath)) {
                Log::error('🔴 Erro na validação', ['erro' => "Arquivo não encontrado: {$rawPath}"]);
                throw new \RuntimeException("Arquivo não encontrado: {$rawPath}");
            }

            $formattedContent = $this->formatContent(Storage::get($rawPath));
            $formattedName    = $this->generateFileName($originalName);

            Log::info("📄 Arquivo recebido: {$originalName} -> Formatado: {$formattedName}");

            $result[$type] = [
                'content' => $formattedContent,
                'name'    => $formattedName,
            ];
        }

        Log::info('✅ Arquivos validados e no formato correto.');

        return $result;
    }

    /**
     * 3/5 - Salva o conteúdo formatado num filesystem estilo S3 (salvando
     * localmente). Retorna caminho + url de cada arquivo.
     */
    private function saveToFile(array $validated): array
    {
        $saved = [];

        foreach ($validated as $type => $file) {
            $path = 'formatted/' . $file['name'];

            Storage::disk(self::DISK)->put($path, $file['content']);
            $url = Storage::disk(self::DISK)->url($path);

            Log::info('✅ Arquivo salvo localmente com sucesso.', ['url' => $url]);

            $saved[$type] = ['path' => $path, 'url' => $url];
        }

        return $saved;
    }

    /**
     * 4/5 - Lê os arquivos salvos para extrair os dados que serão enviados.
     */
    private function readFiles(array $saved): array
    {
        Log::info('📦 Lendo arquivos salvos para montar o payload...');

        return [
            'txt_data' => Storage::disk(self::DISK)->get($saved['txt']['path']),
            'csv_data' => Storage::disk(self::DISK)->get($saved['csv']['path']),
        ];
    }

    /**
     * 5/5 - Monta o payload e envia para a API externa (Projeto 3).
     */
    private function sendToExternalApi(array $data): void
    {
        $url = config('services.external_api.url');

        $payload = [
            'nome'     => $this->nome,
            'email'    => $this->email,
            'txt_data' => $data['txt_data'],
            'csv_data' => $data['csv_data'],
        ];

        // CACHE COM REDIS: se um payload idêntico já foi enviado recentemente,
        // reaproveitamos a resposta guardada no Redis e evitamos chamar a API
        // externa de novo.
        // Usa o store padrão de cache (CACHE_STORE=redis no .env -> Redis).
        $cacheKey = 'external_api_response:' . md5(json_encode($payload));

        if (Cache::has($cacheKey)) {
            $resposta = Cache::get($cacheKey);
            Log::info('🔁 Cache HIT (Redis) - resposta da API externa reaproveitada', ['key' => $cacheKey]);
            Log::info('✅ Arquivos enviados para API externa com sucesso.', ['resposta' => $resposta]);

            return;
        }

        Log::info('🟡 Cache MISS (Redis) - chamando a API externa', ['key' => $cacheKey]);
        Log::info('🚀 Enviando dados para API externa...', ['url' => $url]);

        try {
            $response = Http::acceptJson()->timeout(30)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('[ProcessFileJob] Exceção ao chamar API externa', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Erro de comunicação com a API externa.');
        }

        if (! $response->successful()) {
            Log::error('🔴 Erro ao chamar API externa', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("API externa retornou HTTP {$response->status()}.");
        }

        $resposta = $response->json();

        // Guarda a resposta no cache (Redis) por 10 minutos.
        Cache::put($cacheKey, $resposta, now()->addMinutes(10));

        Log::info('✅ Arquivos enviados para API externa com sucesso.', ['resposta' => $resposta]);
    }

    /**
     * Formatação 1 - deixa tudo minúsculo e remove os acentos.
     */
    private function formatContent(string $string): string
    {
        $comAcentos = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ';
        $semAcentos = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBaaaaaaaceeeeiiiionoooooouuuuyy';

        $semAcento = strtr($string, array_combine(
            preg_split('//u', $comAcentos, -1, PREG_SPLIT_NO_EMPTY),
            preg_split('//u', $semAcentos, -1, PREG_SPLIT_NO_EMPTY)
        ));

        return mb_strtolower($semAcento);
    }

    /**
     * Formatação 2 - gera o nome do arquivo com timestamp + string aleatória,
     * preservando a extensão original.
     */
    private function generateFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'txt';

        return now()->format('Ymd_His') . '_' . Str::random(10) . '.' . $extension;
    }
}
