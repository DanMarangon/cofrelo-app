# views: o HTML das páginas

| Pasta ou arquivo | O que tem |
|---|---|
| `layout.php` | A moldura das páginas internas: menu lateral, topo, notificações e os modais |
| `paginas/` | O miolo de cada tela, encaixado dentro do `layout.php` |
| `parciais/` | Pedaços reaproveitados: ícones e modais (nova transação, depósito, confirmação) |
| `autenticacao/` | As telas de entrar e criar conta, que têm uma moldura própria |
| `erro.php` | A página de erro (404, 500...) |

Todo texto que vem do usuário passa pela função `e()`, que impede que ele vire HTML.
