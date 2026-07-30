# 🔐 Acessos e Dados de Teste

> **Importante:** este projeto (Back-end) **não possui tela de login nem painel administrativo**. É um microserviço de API: recebe arquivos, processa em fila (Redis) e envia para a API. Os endpoints são **públicos** (exercício de estudo local).

## 1. Acesso ao Sistema (Usuários de Teste)

O `DatabaseSeeder` cria um usuário padrão (herdado do starter kit do Laravel), mas ele **não é usado por nenhuma rota** — não há autenticação neste projeto.

| Perfil | E-mail / Usuário | Senha | Permissão / Detalhes |
| --- | --- | --- | --- |
| Usuário do seeder (não utilizado) | `test@example.com` | `password` | Criado via `User::factory()`; não há login para utilizá-lo |

## 2. URLs Principais

Não há rota `/login`. As URLs reais são o endpoint de processamento e o Log Viewer.

| Ambiente | Endpoint (POST) | Log Viewer |
| --- | --- | --- |
| **Docker** | `http://localhost:8081/api/process-files` | `http://localhost:8081/log-viewer` |
| **Local** (`php artisan serve`) | `http://127.0.0.1:8001/api/process-files` | `http://127.0.0.1:8001/log-viewer` |

## 3. Vitrine Pública / Páginas para Clientes

Este projeto é um microserviço de API (sem vitrine pública). Endpoints disponíveis:

| Item | Link (Exemplo Docker) |
| --- | --- |
| Processar arquivos (POST, multipart) | `http://localhost:8081/api/process-files` |
| Log Viewer (visualização dos logs) | `http://localhost:8081/log-viewer` |

Exemplo de teste via `curl` (envie os arquivos de exemplo do Front-end):

```bash
curl -X POST http://127.0.0.1:8001/api/process-files \
  -H "Accept: application/json" \
  -F "nome=Gabriel" -F "email=gabriel@teste.com" \
  -F "txt_file=@exemplo.txt" -F "csv_file=@exemplo.csv"
```

## 4. Validação do Acesso

| Verificação | Resultado Esperado |
| --- | --- |
| Containers (`app`, `nginx`, `redis`, `worker`) | Saudáveis / Rodando |
| `POST /api/process-files` com arquivos válidos | HTTP `200` com `status: success` |
| Worker consumindo a fila | Job `ProcessFileJob` com status `DONE` no log |

## 5. Carregar Dados de Teste

**Com Docker:**

```bash
docker compose exec app php artisan migrate:fresh --seed
```

**Rodando Localmente (Sem Docker):**

```bash
php artisan migrate:fresh --seed
```

---

### 📝 Observações:

- O fluxo de processamento não depende do banco: os dados trafegam via **fila no Redis** e são enviados para a API (Projeto 3).
- Use estas informações **apenas** em ambiente local ou Docker de desenvolvimento.
