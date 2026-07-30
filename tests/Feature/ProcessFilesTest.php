<?php

namespace Tests\Feature;

use App\Jobs\ProcessFileJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessFilesTest extends TestCase
{
    public function test_valida_e_despacha_o_job_para_a_fila(): void
    {
        Queue::fake();
        Storage::fake('local');

        $response = $this->postJson('/api/process-files', [
            'nome'     => 'Gabriel',
            'email'    => 'gabriel@exemplo.com',
            'txt_file' => UploadedFile::fake()->createWithContent('dados.txt', "OLA"),
            'csv_file' => UploadedFile::fake()->createWithContent('dados.csv', "nome,email"),
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');
        Queue::assertPushed(ProcessFileJob::class);
    }

    public function test_rejeita_quando_faltam_arquivos(): void
    {
        $this->postJson('/api/process-files', [
            'nome'  => 'Gabriel',
            'email' => 'gabriel@exemplo.com',
        ])->assertStatus(422)->assertJsonPath('status', 'error');
    }

    public function test_job_formata_arquivos_e_envia_payload_para_a_api(): void
    {
        Storage::fake('local');
        Storage::fake('s3_local');
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        // simula os arquivos já salvos pelo controller (disco local)
        Storage::disk('local')->put('uploads/a.txt', "OLÁ MÚNDO");
        Storage::disk('local')->put('uploads/b.csv', "NOME,EMAIL");

        $files = [
            'txt' => ['path' => 'uploads/a.txt', 'original_name' => 'a.txt'],
            'csv' => ['path' => 'uploads/b.csv', 'original_name' => 'b.csv'],
        ];

        (new ProcessFileJob('Gabriel', 'gabriel@exemplo.com', $files))->handle();

        // salvou os arquivos formatados no disco "S3" (local)
        $this->assertNotEmpty(Storage::disk('s3_local')->allFiles('formatted'));

        // enviou para a API externa com conteúdo minúsculo e sem acento
        Http::assertSent(function ($request) {
            return $request->url() === config('services.external_api.url')
                && $request['nome'] === 'Gabriel'
                && str_contains($request['txt_data'], 'ola mundo');
        });
    }
}
