# JavaScript do Cofrelo

| Pasta | O que tem |
|---|---|
| `app.js` | O que vale para todas as páginas internas: menu, notificações, botão "Nova transação" e atalhos |
| `paginas/` | Um arquivo por tela (`transacoes.js` é a tela de Transações) |
| `componentes/` | Peças reaproveitadas: modais, gráficos, calendário, avisos |
| `lib/` | Funções puras de cálculo, datas e formatação, todas testadas em `tests/js` |
| `nucleo/` | `api.js` conversa com o PHP; `dom.js` cria os elementos da tela com o texto protegido |

Cada página do PHP carrega `app.js` e o arquivo da própria tela. Os oito usos de JavaScript pedidos pela disciplina estão marcados com `[DW 10.a]` a `[DW 10.h]`.
