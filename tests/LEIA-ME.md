# tests: testes automatizados

| Pasta | O que testa | Como rodar |
|---|---|---|
| `Unitario/` | Uma classe sozinha, sem banco (cálculos, validações, formatação) | `docker compose run --rm testes` |
| `Integracao/` | O sistema de verdade contra um MySQL de testes, chamando a API como o navegador | `docker compose run --rm testes` |
| `js/` | As funções de cálculo e formatação do JavaScript | `npm test` |

O nome de cada teste diz o que ele confere, por exemplo `testPrevisaoArredondaParaCimaQuandoSobraUmRestinho`. Todos rodam no GitHub Actions a cada envio de código.
