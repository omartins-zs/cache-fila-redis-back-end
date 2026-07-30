# Como Executar Localmente — Cache & Fila com Redis (Back-end)

Guia para rodar **sem Docker**, no **Laragon**, **XAMPP** ou com `php artisan serve`.

> **Não quer instalar PHP, Composer ou Redis?** Use [COMO_EXECUTAR_DOCKER.md](COMO_EXECUTAR_DOCKER.md) — basta Docker Desktop.

---

## Requisitos

O framework entra no projeto com `composer install`. Laragon, XAMPP ou `php artisan serve` são apenas formas de subir o ambiente.

| Ferramenta | Obrigatório? | Versão mínima |
| --- | --- | --- |
| **Composer** | Sim | 2.x |
| **PHP** | Sim | 8.3+ |
| **Redis** | Sim (fila + cache) | 6+ |

Extensões PHP necessárias: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` e `redis` (phpredis).

### Ambiente de referência (máquina de desenvolvimento)

Stack usada na elaboração deste projeto — **não é requisito fixo**, só referência do que já foi testado:

| Ferramenta | Versão |
| --- | --- |
| **Laragon** | **6.0.0** |
| PHP (via Laragon) | 8.4.6 |
| Composer | 2.8.12 |
| Laravel (no projeto) | 12.3.0 |
| **Node.js** | **22.14.0** |
| **NPM** | **11.4.2** |

Para conferir no seu computador:

```bash
php -v
php artisan --version
composer --version
```

---

## 1) Preparar ambiente

### 1.1 Acessar o projeto

```bash
cd c:/laragon/www/cache-fila-redis-back-end
```

### 1.2 Copiar variáveis de ambiente

```bash
cp .env.example .env
```

No PowerShell:

```powershell
Copy-Item .env.example .env
```

### 1.3 Ativar o ambiente local

Deixe o bloco `LOCAL` ativo e o bloco `DOCKER` comentado no `.env`:

```env
# LOCAL
APP_URL=http://127.0.0.1:8001
REDIS_HOST=127.0.0.1
EXTERNAL_API_URL=http://127.0.0.1:8002/api/external

# DOCKER
# APP_URL=http://localhost:8081
# REDIS_HOST=redis
# EXTERNAL_API_URL=http://host.docker.internal:8082/api/external
```

### 1.4 Subir o Redis

Com Docker:

```bash
docker run -d --name redis -p 6379:6379 redis:latest
```

Ou use o Redis do Laragon/serviço local na porta `6379`.

---

## 2) Instalar dependências

```bash
composer install
```

Este projeto não possui assets de front-end (é uma API), então `npm install` não é necessário.

---

## 3) Inicialização e migrations

O banco padrão é **SQLite** (arquivo `database/database.sqlite`). O `.env.example` do projeto já vem com `DB_CONNECTION=sqlite`.

```bash
php artisan key:generate
php artisan migrate
```

Para recriar todo o banco:

```bash
php artisan migrate:fresh
```

> `migrate:fresh` apaga os dados existentes.

---

## 4) Rodar aplicação

```bash
php artisan serve --port=8001
```

Aplicação: http://127.0.0.1:8001

---

## 5) Filas e workers

Este projeto **usa fila no Redis** — o worker é obrigatório para processar os arquivos:

```bash
php artisan queue:work redis
```

---

## 6) Acessos

| Recurso | URL |
| --- | --- |
| Endpoint de processamento (POST) | http://127.0.0.1:8001/api/process-files |
| Log Viewer | http://127.0.0.1:8001/log-viewer |

### Credenciais de teste

Este projeto **não possui tela de login** — o endpoint é público. Para testar, envie os arquivos pelo Front-end (Projeto 1) ou via `curl`.

---

## 7) Comandos úteis

```bash
php artisan optimize:clear
php artisan route:list
php artisan queue:work redis
php artisan about
php artisan test
```

---

## 8) Problemas comuns

### Redis não conecta

Confirme que o Redis está no ar na porta `6379` e que `REDIS_HOST=127.0.0.1` no bloco LOCAL.

### Alterações do `.env` não foram aplicadas

```bash
php artisan optimize:clear
```

### Chave não configurada

```bash
php artisan key:generate
```

### Job não processa

Confirme que o worker está rodando (`php artisan queue:work redis`) e que a API (Projeto 3) está no ar na porta `8002`.

---

## Próximo passo

Para ambiente containerizado, consulte [COMO_EXECUTAR_DOCKER.md](COMO_EXECUTAR_DOCKER.md).
