# Guia do código do Cofrelo

Este guia é para quem vai abrir o projeto pela primeira vez. Ele mostra onde fica cada coisa e o caminho que uma ação do usuário percorre, do clique no navegador até o banco de dados e de volta. Não é preciso ler o código inteiro: siga um dos caminhos abaixo e abra os arquivos citados.

## 1. Mapa das pastas

| Pasta | O que tem |
|---|---|
| `public/` | A única pasta que o navegador enxerga. `index.php` recebe todas as requisições; `assets/` tem o CSS, o JavaScript e as fontes |
| `src/` | O PHP do sistema, dividido em camadas (veja a seção 3) |
| `views/` | O HTML das páginas, escrito em PHP: `layout.php` é a moldura (menu, topo), `paginas/` tem o miolo de cada tela e `parciais/` os pedaços reaproveitados, como os modais |
| `database/` | `schema.sql`, com as tabelas do MySQL e as categorias padrão |
| `tests/` | Testes automatizados do PHP (`Unitario`, `Integracao`) e do JavaScript (`js`) |
| `docker/` | Configuração do Apache, do PHP, do Prometheus e do Grafana usada pelos containers |
| `scripts/` | Script de implantação com rollback (`implantar.ps1` no Windows, `implantar.sh` no Linux) |
| `.github/workflows/` | O pipeline: `ci.yml` (a cada push) e `release.yml` (a cada versão) |
| `docs/` | Documentos de entrega, evidências e as ferramentas que os geram |

As pastas `src/`, `public/assets/js/`, `views/` e `tests/` têm um `LEIA-ME.md` com o papel de cada subpasta.

## 2. Caminho 1: abrir uma página (por exemplo, `/transacoes`)

1. **Apache** recebe o pedido. Como `/transacoes` não é um arquivo de verdade, ele entrega o pedido para `public/index.php` (regra `FallbackResource` em `docker/apache/cofrelo.conf`).
2. **`public/index.php`** carrega `src/bootstrap.php` (que ensina o PHP a achar as classes e lê o `.env`) e chama `Aplicacao::executar()`.
3. **`src/Aplicacao.php`** é o maestro. O método `processar()` faz, em ordem:
   1. pergunta ao **roteador** (`src/Http/Roteador.php`) qual controlador atende `GET /transacoes`. As rotas estão listadas em `registrarRotasDaApi()` e `registrarRotasDasPaginas()`;
   2. prepara o banco (cria as tabelas na primeira vez) e a sessão;
   3. confere se a pessoa está logada. Se não estiver, manda para `/login`;
   4. chama o controlador.
4. **`src/Controlador/PaginaControlador.php`** monta a página: junta `views/layout.php` com `views/paginas/transacoes.php` (por meio de `src/Suporte/Visao.php`) e devolve uma `Resposta` com o HTML.
5. A resposta ganha os **cabeçalhos de segurança** (`src/Suporte/CabecalhosSeguranca.php`), a requisição vai para o **log** e o HTML segue para o navegador.
6. No navegador, o HTML carrega dois scripts: `public/assets/js/app.js` (menu, notificações, atalhos, comum a todas as páginas) e `public/assets/js/paginas/transacoes.js` (só desta tela).
7. **`transacoes.js`** pede os dados à API (`GET /api/transacoes`) e monta a tabela na tela. As páginas chegam "vazias" e o JavaScript as preenche com os dados.

## 3. Caminho 2: salvar uma transação

1. A pessoa preenche o modal de nova transação. O código está em `public/assets/js/componentes/modal-transacao.js`.
2. Ao salvar, o JavaScript envia os dados em JSON com `POST /api/transacoes`, junto com o **token CSRF**, que prova que o pedido saiu do próprio Cofrelo (`public/assets/js/nucleo/api.js`).
3. No servidor, o caminho é o mesmo do Caminho 1 até o roteador, que agora escolhe **`TransacaoControlador::criar()`** (`src/Controlador/Api/`).
4. O PHP trabalha em **camadas**, e cada uma tem um único papel:

   | Camada | Pasta | Papel | Exemplo |
   |---|---|---|---|
   | Controlador | `src/Controlador` | Recebe o pedido e devolve a resposta; não tem regra de negócio nem SQL | `TransacaoControlador` |
   | Serviço | `src/Servico` | As regras de negócio (RN01 a RN14) | `ValidadorTransacao` confere valor, data, conta e categoria |
   | Repositório | `src/Repositorio` | O único lugar com SQL; fala com o MySQL por consultas preparadas | `TransacaoRepositorio::criar()` faz o `INSERT` |
   | Modelo | `src/Modelo` | Representa um registro do banco como objeto | `Transacao`, `Meta`, `Conta` |

5. Se a validação encontra erros, o controlador lança um erro 422 com a mensagem de cada campo. `Aplicacao::respostaDoErro()` transforma isso em JSON, e o JavaScript mostra as mensagens embaixo dos campos (`componentes/formulario.js`).
6. Se está tudo certo, o repositório grava, o controlador responde `201 Created` com a transação, e a tela se atualiza.

## 4. Onde ficam as regras de negócio

| Regra | Onde está |
|---|---|
| Senha forte (RN01) e validação de contas, metas e assinaturas | `src/Servico/ValidadorCadastros.php` |
| Validação da transação (RN03 e RN07) | `src/Servico/ValidadorTransacao.php` |
| Conta com histórico não pode ser excluída (RN04) | `src/Controlador/Api/ContaControlador.php` |
| Saldo só com transações pagas (RN05) | `src/Repositorio/ContaRepositorio.php` |
| Metas: situação, aporte mensal e previsão (RN08, RN09 e RN14) | `src/Servico/CalculadoraMeta.php` |
| Taxa de economia e indicadores (RN10) | `src/Servico/Indicadores.php` |
| Cobrança automática das assinaturas (RN11) | `src/Servico/Assinaturas.php` |
| Avisos do sino (RN12) | `src/Servico/Notificacoes.php` |
| Limite de tentativas de login | `src/Suporte/LimiteDeTentativas.php` |

O JavaScript repete apenas os cálculos que precisam aparecer enquanto a pessoa digita, como o simulador de metas em `public/assets/js/lib/calculos.js`. A palavra final é sempre do PHP.

## 5. JavaScript sem framework

Cada página tem o seu arquivo em `public/assets/js/paginas/`, e as peças reaproveitadas ficam em `componentes/` (modais, gráficos, calendário) e `lib/` (cálculos, datas e formatação, todos testados). Duas funções aparecem em todo lugar:

- `api.get()` e `api.post()` (`nucleo/api.js`): conversam com o PHP e já tratam erros e o token CSRF;
- `el()` (`nucleo/dom.js`): cria um elemento HTML com o texto já protegido contra XSS (usa `textContent`, nunca `innerHTML`).

No código, os oito usos de JavaScript pedidos na disciplina estão marcados com comentários `[DW 10.a]` a `[DW 10.h]`.

## 6. Banco de dados

As tabelas estão em `database/schema.sql`: `usuario`, `conta`, `categoria`, `transacao`, `assinatura`, `meta` e `meta_deposito`. Na primeira execução, `src/Banco/Migrador.php` cria as tabelas sozinho, e `src/Banco/DadosDeExemplo.php` cria a usuária de demonstração com um ano de movimentações. A conexão fica em `src/Banco/Conexao.php`, com os dados lidos das variáveis de ambiente (`.env`).

## 7. Testes

- `tests/Unitario`: testam uma classe sozinha, sem banco. Exemplo: `CalculadoraMetaTest` confere o cálculo do aporte e da previsão.
- `tests/Integracao`: sobem o sistema de verdade contra um MySQL de testes e chamam a API como o navegador faria (`ApiTest`).
- `tests/js`: testam as funções de cálculo e formatação do JavaScript.

Para rodar: `docker compose run --rm testes` (PHP) e `npm test` (JavaScript).

## 8. Do código ao ar: Docker, pipeline e implantação

1. **Docker:** o `Dockerfile` monta uma imagem com PHP, Apache e o código. O `docker-compose.yml` sobe essa imagem junto com o MySQL, e o mesmo comando (`docker compose up -d`) funciona em qualquer computador.
2. **CI** (`.github/workflows/ci.yml`): a cada push, o GitHub Actions confere a sintaxe, roda o PHPStan e todos os testes, verifica a segurança, constrói a imagem e testa a imagem com um banco de verdade. Se algo falhar, o pipeline fica vermelho e a imagem não é publicada.
3. **Release** (`.github/workflows/release.yml`): quando uma versão recebe uma tag (`v1.1.0`), a imagem é publicada no GitHub Container Registry, anexada a uma release e testada de novo num ambiente de homologação.
4. **Implantação** (`scripts/implantar.ps1`): baixa a imagem da versão pedida, troca o container e confere o `/health`. Se a versão nova não ficar saudável em 60 segundos, volta sozinha para a anterior.
5. **Monitoramento:** `/health` diz se a aplicação e o banco estão bem, os logs saem em JSON e `/metrics` alimenta o Prometheus e o Grafana.

## 9. Glossário

| Termo | O que quer dizer aqui |
|---|---|
| Rota | A combinação de método e endereço (`POST /api/transacoes`) ligada a um método de controlador |
| API REST | Endereços `/api/...` que recebem e devolvem JSON em vez de HTML |
| Consulta preparada | SQL com marcadores (`:valor`) preenchidos pelo PDO; impede SQL injection |
| Token CSRF | Código secreto da sessão enviado em toda alteração, para provar que o pedido veio do próprio site |
| Sessão | Como o PHP lembra quem está logado entre uma requisição e outra (por um cookie) |
| Migração | Criar ou atualizar as tabelas do banco automaticamente |
| Container | Um "pacote" com o programa e tudo de que ele precisa, que roda igual em qualquer máquina |
| Imagem | O modelo de um container, versionado (`cofrelo-app:1.1.0`) |
| Pipeline | A sequência automática de verificações que roda a cada envio de código |
| Rollback | Voltar para a versão anterior quando a nova dá problema |
