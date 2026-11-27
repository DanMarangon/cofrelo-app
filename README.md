# Cofrelo

**Finanças em equilíbrio.** O Cofrelo é um sistema web de controle financeiro pessoal: reúne as contas da pessoa, registra receitas, despesas, transferências e assinaturas, acompanha metas de economia e mostra relatórios do que entra e do que sai.

Projeto final das disciplinas **Desenvolvimento Web** (Prof. Valdemir S. Silva) e **Integração DevOps** do curso de Ciência da Computação do CEUB, 2º semestre de 2026.

| | |
|---|---|
| Aplicação | PHP 8.3, MySQL 8.4, HTML, CSS e JavaScript (sem framework) |
| API | REST com JSON, sessão e proteção CSRF |
| Testes | PHPUnit (unitários e de integração com MySQL) e `node --test` para o JavaScript |
| CI/CD | GitHub Actions, imagem Docker no GitHub Container Registry, releases versionadas |
| Execução | Docker e Docker Compose (ou XAMPP) |
| Observabilidade | `/health`, logs em JSON, `/metrics`, Prometheus e Grafana |

## Integrantes

- Dan Ramos Marques Marangon (RA 22502303)
- Marcelo Henrique
- Rafael Hideki
- Gustavo Henrique
- Henrique de Figueiredo

## Sumário

1. [A aplicação](#1-a-aplicação)
2. [Como executar](#2-como-executar)
3. [API REST](#3-api-rest)
4. [Versionamento e branches](#4-versionamento-e-branches)
5. [Pipeline de CI/CD](#5-pipeline-de-cicd)
6. [Testes automatizados](#6-testes-automatizados)
7. [Artefato: imagem Docker](#7-artefato-imagem-docker)
8. [Implantação, atualização e rollback](#8-implantação-atualização-e-rollback)
9. [Infraestrutura e configuração](#9-infraestrutura-e-configuração)
10. [Segurança](#10-segurança)
11. [Monitoramento e observabilidade](#11-monitoramento-e-observabilidade)
12. [Evidências do GitHub Actions](#12-evidências-do-github-actions)
13. [Documentos e como refazer as evidências](#13-documentos-e-como-refazer-as-evidências)

## 1. A aplicação

O problema: muita gente não sabe para onde vai o próprio dinheiro, esquece cobranças recorrentes e não consegue guardar para objetivos. O Cofrelo junta tudo em um só lugar.

Funcionalidades principais:

- **Visão geral** do mês: saldo total, receitas e despesas com a variação em relação ao mês anterior, fluxo de caixa dos últimos 6 meses, onde mais se gastou, orçamento usado, últimas transações e a meta mais adiantada.
- **Transações** com filtros (busca por descrição ou valor, categoria, conta e período escolhido num calendário), abas por tipo, ordenação, paginação, edição, exclusão e exportação em CSV.
- **Assinaturas**: uma despesa pode virar cobrança mensal. O sistema lança a cobrança sozinho em cada mês e avisa antes de ela acontecer.
- **Minhas contas**: patrimônio, saldo de cada conta e transferências entre elas.
- **Metas e objetivos**: progresso, "guardar dinheiro", previsão de conclusão no ritmo dos últimos 3 meses e um simulador que calcula quanto guardar por mês, com rendimento.
- **Relatórios**: evolução mensal, comparação por categoria com o mês anterior, taxa de economia e um mapa de gastos diários no estilo do calendário do GitHub.
- **Notificações** de contas pendentes, cobranças chegando, orçamento quase no fim e metas com prazo próximo.
- **Configurações** de perfil, orçamento e senha, e **Central de ajuda** com busca.

O visual segue o protótipo feito no Figma ([`docs/prototipo`](docs/prototipo)). Todas as páginas passam na auditoria de acessibilidade WCAG 2.2 AA do axe-core, no computador e no celular (`python docs/ferramentas/auditar_acessibilidade.py`).

## 2. Como executar

### Com Docker (recomendado)

Requisitos: Docker Desktop (ou Docker Engine com o plugin Compose).

```bash
git clone https://github.com/DanMarangon/cofrelo-app.git
cd cofrelo-app
cp .env.example .env        # no Windows: copy .env.example .env  (troque as senhas)
docker compose up -d --build
```

Abra http://localhost:8080 e entre com a conta de demonstração **mariana@cofrelo.app** e a senha **cofrelo123**. Na primeira execução, o sistema cria as tabelas e 12 meses de dados de exemplo sozinho.

Comandos úteis:

```bash
docker compose ps                         # situação dos containers
docker compose logs -f app                # logs da aplicação (JSON)
docker compose exec app php bin/banco.php recriar   # recria o banco com os dados de exemplo
docker compose down                       # para tudo (os dados ficam no volume)
```

### Com XAMPP

1. Copie a pasta para `C:\xampp\htdocs\cofrelo-app` e ligue o Apache e o MySQL no painel do XAMPP.
2. Crie um arquivo `.env` com `DB_HOST=127.0.0.1`, `DB_USER=root` e `DB_PASS=` (vazio). O `.env.example` traz esse exemplo no final.
3. Abra http://localhost/cofrelo-app. O banco `cofrelo` e as tabelas são criados no primeiro acesso.

## 3. API REST

A mesma aplicação responde as páginas e a API. Todas as rotas da API, menos as de login e saúde, exigem a sessão. As que alteram dados também exigem o cabeçalho `X-CSRF-Token`, devolvido no login.

| Método | Rota | O que faz |
|---|---|---|
| GET | `/health` | Saúde: versão, commit, ambiente e estado do banco (200 ou 503) |
| GET | `/metrics` | Métricas no formato do Prometheus |
| GET | `/api` | Lista dos endpoints |
| POST | `/api/auth/entrar` | Login com `{"email","senha"}`; devolve o usuário e o token CSRF |
| POST | `/api/auth/cadastro` | Cria usuário (e a conta principal dele) |
| POST | `/api/auth/sair` · GET `/api/auth/eu` | Sair · usuário logado |
| GET | `/api/resumo?mes=AAAA-MM` | Números da Visão geral |
| GET | `/api/relatorios?inicio=…&fim=…` · `/api/relatorios/exportar` | Relatório do período · CSV |
| GET | `/api/notificacoes` · `/api/categorias` | Avisos · categorias |
| GET, POST | `/api/contas` | Lista com saldos · cria conta |
| GET, PUT, DELETE | `/api/contas/{id}` | Consulta, altera, exclui (não exclui conta com transações) |
| GET, POST | `/api/transacoes` | Lista com filtros e paginação · cria transação ou assinatura |
| GET, PUT, DELETE | `/api/transacoes/{id}` | Consulta, altera, exclui |
| GET | `/api/transacoes/exportar` | CSV das transações do filtro |
| GET · PUT, DELETE | `/api/assinaturas` · `/api/assinaturas/{id}` | Lista · altera ou pausa, exclui |
| GET, POST | `/api/metas` | Lista com resumo · cria meta |
| GET, PUT, DELETE | `/api/metas/{id}` | Consulta, altera, exclui |
| POST | `/api/metas/{id}/depositos` | Guardar dinheiro na meta |

Testando com o `curl`:

```bash
curl -c cookies.txt -H "Content-Type: application/json" \
     -d '{"email":"mariana@cofrelo.app","senha":"cofrelo123"}' http://localhost:8080/api/auth/entrar
curl -b cookies.txt "http://localhost:8080/api/resumo?mes=2026-11"
curl -b cookies.txt "http://localhost:8080/api/transacoes?tipo=despesa&por_pagina=5"
curl -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: <token do login>" \
     -d '{"tipo":"despesa","valor":42.5,"descricao":"Padaria","categoria_id":2,"conta_id":1,"data":"2026-11-26"}' \
     http://localhost:8080/api/transacoes
```

Erros voltam em JSON com a mensagem e, na validação (422), os campos: `{"erro":"Confira os campos destacados.","status":422,"campos":{"valor":"Informe um valor maior que zero."}}`.

## 4. Versionamento e branches

Usamos **GitFlow**, que o grupo já tinha começado no repositório original:

| Branch | Para que serve |
|---|---|
| `main` | Só versões entregues. Cada merge vem de uma `release/*` e ganha uma tag `vX.Y.Z` |
| `develop` | Integração do que está pronto para a próxima versão |
| `feature/*` | Uma funcionalidade por branch, criada a partir da `develop` |
| `release/*` | Preparação de uma versão (changelog), saindo da `develop` para a `main` |
| `hotfix/*` | Correção urgente na `main` |

Toda mudança entra por **Pull Request** com o CI verde, e o merge preserva os commits da branch (`--merge`). As mensagens de commit seguem o padrão `tipo: descrição` (`feat`, `fix`, `ci`, `docs`, `test`, `chore`).

Escolhemos o GitFlow porque a disciplina pede versões entregues com rastreabilidade (tag, release e imagem com a mesma versão) e porque ele separa o que está em desenvolvimento do que já foi entregue. Em repositório público, o script `scripts/proteger-branches.sh` liga a proteção da `main` e da `develop` (PR obrigatório e CI verde). O plano gratuito do GitHub não oferece proteção de branches em repositório privado.

## 5. Pipeline de CI/CD

**CI** (`.github/workflows/ci.yml`): roda em todo push e em todo Pull Request para `develop` e `main`.

| Job | Etapas |
|---|---|
| Validação e testes | checkout, preparar PHP 8.3 e Node 22, `composer validate`, instalar dependências (com cache), verificar a sintaxe de todos os arquivos PHP, análise estática com PHPStan (nível 6), testes do JavaScript, PHPUnit com um MySQL de serviço, publicar o resultado dos testes no Actions e guardar os relatórios JUnit |
| Verificações de segurança | `composer audit` (dependências com vulnerabilidades), Gitleaks (senhas e chaves no histórico), zizmor (falhas de segurança nos próprios workflows) e hadolint (boas práticas do Dockerfile) |
| Imagem Docker | construir a imagem de produção, subir com um MySQL e testar `/health`, login e API, Trivy (vulnerabilidades críticas com correção bloqueiam) e publicar no GHCR nos pushes da `develop` e da `main` |

**CD** (`.github/workflows/release.yml`): roda quando uma tag `vX.Y.Z` é criada na `main`.

| Job | Etapas |
|---|---|
| Publicar a versão | construir e publicar a imagem com as tags `X.Y.Z`, `X.Y`, `latest` e `sha-…`, empacotar a imagem (`docker save`), gerar as notas da versão e criar a release no GitHub com a imagem e os arquivos de implantação anexados |
| Homologação | baixar a imagem publicada, subir com Docker Compose, conferir se o `/health` responde a versão certa e fazer testes de fumaça (login, resumo, criar e excluir transação, métricas) |

Construção, teste, entrega e implantação ficam separados assim:

1. **Construção:** a imagem é gerada uma vez no pipeline, a partir do commit.
2. **Teste:** os testes rodam no código (CI) e na imagem pronta (job Imagem Docker e homologação).
3. **Entrega:** a imagem versionada vai para o GHCR e para a release do GitHub.
4. **Implantação:** o script `scripts/implantar.ps1` coloca uma versão escolhida no ar, no ambiente de execução.

## 6. Testes automatizados

81 casos no total, todos rodando no CI. Se qualquer um falhar, o pipeline fica vermelho e a imagem não é publicada.

| Conjunto | Casos | O que cobre |
|---|---|---|
| `tests/Unitario` (PHPUnit) | 39 | Validação de transações (RN03 a RN07), cálculo das metas (aporte e previsão), indicadores, formatação, roteador, limite de tentativas de login e proteção do CSV contra fórmulas |
| `tests/Integracao` (PHPUnit + MySQL) | 26 | Repositórios, dados de exemplo, endpoints da API (login, CSRF, CRUD, previsão das metas, 401, 404, 405, 409, 422 e 429) e páginas |
| `tests/js` (`node --test`) | 16 | Formatação, máscara de valores, cálculos do simulador, sugestão de categoria, datas e força da senha |

Rodando localmente:

```bash
docker compose run --rm testes     # PHPUnit contra um MySQL de testes
npm test                           # testes do JavaScript (Node 22 ou mais novo)
composer analisar                  # análise estática com PHPStan
```

## 7. Artefato: imagem Docker

O artefato é a imagem Docker gerada pelo pipeline:

- Registro: `ghcr.io/danmarangon/cofrelo-app` ([pacote no GitHub](https://github.com/DanMarangon/cofrelo-app/pkgs/container/cofrelo-app)).
- Tags: `X.Y.Z` e `latest` nas releases, `develop` e `main` nos pushes, e `sha-<commit>` em todas, para saber de qual commit cada imagem veio.
- A imagem guarda a versão e o commit (variáveis `APP_VERSION` e `APP_COMMIT` e labels OCI), mostrados no `/health`.
- Cada release do GitHub anexa a imagem (`cofrelo-imagem-X.Y.Z.tar.gz`), o arquivo `imagem-X.Y.Z.txt` (digest, ID, commit e link da execução) e o `SHA256SUMS`.

Rodando a imagem publicada sem o código-fonte:

```bash
docker pull ghcr.io/danmarangon/cofrelo-app:1.0.0
# ou, sem acesso ao registro: gh release download v1.0.0 -p "cofrelo-imagem-*" && docker load -i cofrelo-imagem-1.0.0.tar.gz
COFRELO_VERSAO=1.0.0 docker compose up -d --no-build
```

## 8. Implantação, atualização e rollback

**Estratégia: recreate com verificação de saúde e rollback automático**, em ambiente local com Docker Compose. O banco fica no volume `dados-mysql` e não é recriado nas trocas de versão.

```powershell
.\scripts\implantar.ps1 -Versao 1.0.0                 # primeira implantação
.\scripts\implantar.ps1 -Versao 1.1.0                 # atualização de versão
.\scripts\implantar.ps1 -Versao 1.1.0 -SimularFalha   # nova versão quebrada: rollback automático
.\scripts\implantar.ps1 -Voltar                       # rollback manual para a versão anterior
.\scripts\implantar.ps1 -Situacao                     # versão em execução e /health
```

O que o script faz:

1. Garante que a imagem da versão está no computador (GHCR ou release do GitHub, conferindo o ID publicado).
2. Troca o container da aplicação pela versão pedida (`docker compose up -d --no-build --force-recreate app`).
3. Consulta o `/health` por até 60 segundos e exige `status: ok` com a versão certa.
4. Se a versão nova não ficar saudável, sobe de novo a versão anterior e confere o `/health` dela (**ROLLBACK OK**).
5. Registra cada implantação e cada rollback em `.deploy/historico.log`.

`-SimularFalha` liga a variável `SIMULAR_FALHA`, que faz o `/health` responder 503. Ela imita uma versão quebrada para demonstrar o rollback sem estragar o código. No Linux, o equivalente é `./scripts/implantar.sh`.

## 9. Infraestrutura e configuração

Para rodar: Docker 24 ou mais novo com Compose v2, cerca de 1 GB de RAM livre e as portas 8080 (aplicação), 9090 (Prometheus) e 3000 (Grafana). Sem Docker: PHP 8.1 ou mais novo com `pdo_mysql` e `mbstring`, Apache com `mod_rewrite` e MySQL 8.

Toda configuração fica fora do código, em variáveis de ambiente (lidas do ambiente ou do arquivo `.env`):

| Variável | Para que serve |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_ROOT_PASS` | Conexão com o MySQL |
| `APP_PORTA` | Porta da aplicação no computador (padrão 8080) |
| `SEED_DEMO` | Cria a usuária de demonstração quando o banco está vazio |
| `AUTO_MIGRATE` | Cria as tabelas automaticamente (padrão `true`) |
| `COFRELO_VERSAO`, `COFRELO_IMAGEM` | Versão e imagem usadas pelo Compose e pelo script de implantação |
| `LOG_DESTINO` | `stderr` (padrão, aparece no `docker compose logs`) ou caminho de um arquivo |
| `SIMULAR_FALHA` | Só para a demonstração do rollback |
| `GRAFANA_SENHA` | Senha do administrador do Grafana |

## 10. Segurança

- **Nenhum segredo no repositório:** o `.env` está no `.gitignore` e o `.env.example` só tem valores de exemplo. A senha do MySQL de testes do CI fica no GitHub Secrets (`MYSQL_SENHA_TESTES`), e a publicação no GHCR usa o `GITHUB_TOKEN` automático.
- **Verificações automáticas no CI:** `composer audit`, Gitleaks em todo o histórico, Trivy na imagem (vulnerabilidade crítica com correção bloqueia o pipeline), zizmor nos workflows, hadolint no Dockerfile, PHPStan no código e Dependabot mensal para Composer, actions e imagem base.
- **Cadeia de suprimentos do pipeline:** toda action está fixada pelo SHA do commit (uma tag pode ser trocada por código malicioso, como aconteceu com a `trivy-action` em 2026), cada job recebe só as permissões que usa, o checkout não deixa o token gravado no disco e a release é construída sem cache.
- **Na aplicação:** senhas com `password_hash` (bcrypt) e nunca devolvidas pela API; consultas preparadas do PDO em todo acesso ao banco; texto sempre escapado nas páginas (`htmlspecialchars` no PHP, `textContent` no JavaScript); token CSRF em toda alteração; cookie de sessão HttpOnly e SameSite; id de sessão renovado no login; login bloqueado por 15 minutos depois de 5 senhas erradas do mesmo e-mail e IP (ou 20 do mesmo IP), com resposta 429 e `Retry-After`; células do CSV exportado que começam com `=`, `+`, `-` ou `@` neutralizadas (injeção de fórmula no Excel); e cabeçalhos `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` e `Referrer-Policy`.
- **Na imagem:** imagem base fixada pelo digest, Apache rodando como `www-data` (sem root) na porta 8080, sem ferramentas de compilação, `expose_php` e assinatura do servidor desligadas, e só o código necessário (`.dockerignore`).

Juntas, essas práticas impedem que uma senha vaze pelo Git, que uma dependência vulnerável chegue à produção sem aviso e que os ataques mais comuns em sistemas web (SQL injection, XSS, CSRF e roubo de sessão) funcionem.

## 11. Monitoramento e observabilidade

- **`/health`** responde `{"status":"ok","versao":"1.0.0","commit":"…","banco":"ok",…}` ou 503 quando o banco cai. É usado pelo `HEALTHCHECK` do Docker, pelo pipeline e pelo script de implantação.
- **Logs em JSON**, uma linha por evento, com o id da requisição: `http.requisicao` (método, rota, status, duração, usuário), `autenticacao.falha`, `transacao.criada`, `banco.erro`, `saude.falha`… Veja com `docker compose logs -f app`.
- **`/metrics`** no formato do Prometheus: versão em execução, banco disponível, totais de usuários, transações e metas, requisições por rota e status, tempo de resposta e erros 5xx.
- **Prometheus e Grafana:** `docker compose --profile observabilidade up -d` sobe o Prometheus (http://localhost:9090), com 4 regras de alerta, e o Grafana (http://localhost:3000), com o painel "Cofrelo: saúde da aplicação" já configurado.

Como identificar uma falha depois da implantação: o script de implantação já confere o `/health`. Depois disso, `docker compose ps` mostra o container como `unhealthy`, o `/health` responde 503 com o motivo, o log registra `saude.falha` ou `banco.erro`, e o Prometheus dispara o alerta `BancoIndisponivel` ou `CofreloForaDoAr`.

## 12. Evidências do GitHub Actions

| Evidência | Execução |
|---|---|
| Sucesso na `main` (versão 1.1.0) | [run 45971991589](https://github.com/DanMarangon/cofrelo-app/actions/runs/45971991589) |
| **Falha controlada** (PR #11, primeiro commit) | [push](https://github.com/DanMarangon/cofrelo-app/actions/runs/45969983018) e [Pull Request](https://github.com/DanMarangon/cofrelo-app/actions/runs/45969991182) |
| Correção da falha (PR #11, segundo commit) | [run 45970373901](https://github.com/DanMarangon/cofrelo-app/actions/runs/45970373901) |
| Release 1.0.0: publicação e homologação | [run 45970280748](https://github.com/DanMarangon/cofrelo-app/actions/runs/45970280748) |
| Release 1.1.0: publicação e homologação | [run 45971999544](https://github.com/DanMarangon/cofrelo-app/actions/runs/45971999544) |
| Falha real: Gitleaks sem permissão de ler o Pull Request | [run 45911772755](https://github.com/DanMarangon/cofrelo-app/actions/runs/45911772755) |
| Falha real: tag da action do Trivy que deixou de existir | [run 45915430973](https://github.com/DanMarangon/cofrelo-app/actions/runs/45915430973) |

**A falha controlada:** a previsão de conclusão das metas (versão 1.1.0) foi enviada primeiro com `intdiv($falta, $ritmo)`, uma divisão inteira que arredonda para baixo. O job "Validação e testes" ficou vermelho: o teste `testPrevisaoArredondaParaCimaQuandoSobraUmRestinho` esperava 6 meses e recebeu 5, e o teste de integração das metas esperava 7 e recebeu 6. Como a validação falhou, o job da imagem foi pulado e nenhuma imagem com o erro foi publicada. A correção arredonda para cima (`intdiv($falta + $ritmo - 1, $ritmo)`), o CI voltou a ficar verde e o PR foi mesclado.

As duas falhas reais da tabela foram corrigidas com `pull-requests: read` no job de segurança e, depois, com as actions fixadas pelo SHA do commit. O passo a passo completo, com os logs, está no PDF de evidências (`docs/entregas`).

## 13. Documentos e como refazer as evidências

A pasta [`docs`](docs) guarda os documentos entregues e as ferramentas que os geram:

- [`docs/entregas`](docs/entregas): documentação do sistema no padrão ABNT (Word e PDF), briefing, PDF de evidências de DevOps e slides das duas apresentações.
- [`docs/evidencias`](docs/evidencias): capturas de tela, diagramas, wireframes, logs das implantações e da demonstração de falha, e os dados coletados do GitHub.
- [`docs/prototipo`](docs/prototipo): telas do protótipo no Figma.
- [`docs/ferramentas`](docs/ferramentas): scripts que capturam as telas e geram os documentos.
- [`docs/COMO-REFAZER.md`](docs/COMO-REFAZER.md): passo a passo para gerar de novo as evidências e os documentos.
