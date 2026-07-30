# ⚙️ Cache & Fila com Redis — Projeto 2 (Back-end · Laravel + Redis Queue)

Projeto de **estudo** de **Cache com Redis** e **Fila (Queue) com Redis** dividido em 3 microserviços. Este é o **Back-end**, onde acontece a **Fila com Redis**.

> Fluxo geral: [Front-end](https://github.com/omartins-zs/cache-fila-redis-vue-front-end) → **Back-end (este)** → [API (cache/Redis)](https://github.com/omartins-zs/cache-fila-redis-api)

## Responsabilidade
Recebe o `multipart/form-data` do Projeto 1, guarda os arquivos e **despacha um Job para a Fila do Redis**. O processamento inteiro roda de forma **assíncrona** no worker.

Contém: **1 Route API**, **1 Controller** e **1 Job**.

| Peça | Arquivo |
|------|---------|
| Route API | `routes/api.php` → `POST /api/process-files` |
| Controller | `app/Http/Controllers/FileController.php` |
| Job (fila) | `app/Jobs/ProcessFileJob.php` |

## As 5 funções do Job
`processFile` (orquestra e loga cada passo) chama, em ordem:
1. **validateFiles** — valida e formata o conteúdo (minúsculo + sem acentos) e gera o nome com timestamp + string aleatória.
2. **saveToFile** — salva no disco `s3_local` (filesystem estilo S3, mas gravando localmente).
3. **readFiles** — lê os arquivos salvos para montar o payload.
4. **sendToExternalApi** — envia o payload para o Projeto 3.

## Redis (Fila **e** Cache)
- `QUEUE_CONNECTION=redis` (no `.env`) — a fila usa Redis.
- `CACHE_STORE=redis` (no `.env`) — em `sendToExternalApi` a resposta da API
  externa é cacheada por um hash do payload (`Cache::store('redis')`). Payload
  idêntico → **Cache HIT** (não chama a API de novo); payload novo → **Cache MISS**.
- Suba o Redis com Docker:
```bash
docker compose up -d
```

## Como rodar
```bash
composer install
php artisan serve --port=8001
php artisan queue:work redis
```
O comando `queue:work redis` roda em um terminal separado — é ele que consome a fila.
A URL da API externa (Projeto 3) é configurável via `EXTERNAL_API_URL` no `.env`.
