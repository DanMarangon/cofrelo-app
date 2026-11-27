# src: o PHP do Cofrelo

Comece por `Aplicacao.php`: ele recebe cada requisição e mostra, em ordem, as etapas por onde ela passa.

| Pasta | Papel | Exemplo |
|---|---|---|
| `Http/` | O pedido que chegou (`Requisicao`), a resposta que sai (`Resposta`) e o roteador, que liga cada endereço a um controlador | `Roteador.php` |
| `Controlador/` | Recebe o pedido e devolve a resposta. `Api/` responde JSON; `PaginaControlador` monta as páginas | `Api/TransacaoControlador.php` |
| `Servico/` | As regras de negócio (RN01 a RN14) | `ValidadorTransacao.php` |
| `Repositorio/` | O único lugar com SQL; fala com o MySQL | `TransacaoRepositorio.php` |
| `Modelo/` | Um registro do banco como objeto | `Transacao.php` |
| `Banco/` | Conexão, criação das tabelas e dados de demonstração | `Migrador.php` |
| `Suporte/` | Ajudantes usados por todos: sessão, log, métricas, formatação, segurança | `Sessao.php` |

Regra para não se perder: o controlador chama o serviço, o serviço chama o repositório, e só o repositório escreve SQL.
