# Como Executar com Docker — Cache & Fila com Redis (Back-end)

Guia para executar o sistema utilizando Docker Desktop.

---

## Stack e containers

| Container | Função | Porta |
| --- | --- | --- |
| nginx | Servidor web | 8081 |
| app | Laravel com PHP-FPM | Interna |
| redis | Fila e cache | Interna |
| worker | Processa a fila (queue:work) | Interna |

Este projeto usa **SQLite** (arquivo), então não há container de banco nem PHPMyAdmin.

---

## 1) Preparar ambiente

```bash
cp .env.example .env
```

Deixe o bloco `DOCKER` ativo e o bloco `LOCAL` comentado:

```env
# LOCAL
# APP_URL=http://127.0.0.1:8001
# REDIS_HOST=127.0.0.1
# EXTERNAL_API_URL=http://127.0.0.1:8002/api/external

# DOCKER
APP_URL=http://localhost:8081
REDIS_HOST=redis
EXTERNAL_API_URL=http://host.docker.internal:8082/api/external
```

> Dentro do Docker, o Redis é acessado pelo nome do serviço `redis`. A API (Projeto 3) é acessada pelo host via `host.docker.internal:8082` (rode o Projeto 3 em Docker na porta 8082 para o fluxo completo).

---

## 2) Subir containers

```bash
docker compose up -d --build
docker compose ps
```

---

## 3) Inicialização e migrations

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

---

## 4) Desenvolvimento e cache

```bash
docker compose exec app php artisan optimize:clear
```

---

## 5) Acessos

| Recurso | URL |
| --- | --- |
| Endpoint de processamento (POST) | http://localhost:8081/api/process-files |
| Log Viewer | http://localhost:8081/log-viewer |

### Credenciais de teste

Este projeto **não possui tela de login** — o endpoint é público.

---

## 6) Logs e diagnóstico

```bash
docker compose logs -f
docker compose logs -f app
docker compose logs -f worker
docker compose exec app php artisan about
```

---

## 7) Parar ou reconstruir

```bash
docker compose down
docker compose up -d --build
```

Para apagar também os volumes (dados do Redis):

```bash
docker compose down -v
```

> O comando `docker compose down -v` apaga o volume do Redis.
