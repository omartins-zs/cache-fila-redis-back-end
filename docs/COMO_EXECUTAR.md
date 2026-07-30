# Como Executar — Cache & Fila com Redis (Back-end)

Escolha **um** guia conforme seu ambiente:

| Guia | Quando usar | Requisitos no PC |
| --- | --- | --- |
| **[COMO_EXECUTAR_DOCKER.md](COMO_EXECUTAR_DOCKER.md)** | Executar em qualquer máquina com containers | Docker Desktop |
| **[COMO_EXECUTAR_LOCAL.md](COMO_EXECUTAR_LOCAL.md)** | Desenvolver com Laragon, XAMPP ou Artisan | PHP, Composer e Redis |

Este é o **Projeto 2 (Back-end)** de uma cadeia de 3 microserviços. Ele recebe os arquivos do Front-end, processa em **fila (queue) no Redis** e envia para a API. Precisa da **API (Projeto 3)** no ar para o fluxo completo.

---

## Início rápido

### Local — Laragon ou XAMPP

Ative o bloco `LOCAL` no `.env` e execute:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve --port=8001
```

Em outro terminal, suba o **worker** da fila:

```bash
php artisan queue:work redis
```

Aplicação: http://127.0.0.1:8001 · Logs: http://127.0.0.1:8001/log-viewer

### Docker

Ative o bloco `DOCKER` no `.env` e execute:

```bash
cp .env.example .env
docker compose up -d --build
```

Aplicação: http://localhost:8081 · Logs: http://localhost:8081/log-viewer

---

## Logins demo

Este projeto **não possui tela de login**. O endpoint `POST /api/process-files` é público (exercício de estudo local).

---

## URLs principais

| Área | Local | Docker |
| --- | --- | --- |
| Endpoint de processamento (POST) | http://127.0.0.1:8001/api/process-files | http://localhost:8081/api/process-files |
| Log Viewer | http://127.0.0.1:8001/log-viewer | http://localhost:8081/log-viewer |

---

## Outros documentos

- [README.md](../README.md) — Visão geral do Back-end e do fluxo dos 3 projetos
