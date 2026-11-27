# Histórico de versões

Todas as mudanças importantes do Cofrelo ficam registradas aqui. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [Versionamento Semântico](https://semver.org/lang/pt-BR/): `MAIOR.MENOR.CORREÇÃO`.

Cada versão tem uma tag `vX.Y.Z` na `main`, uma release no GitHub e a imagem `ghcr.io/danmarangon/cofrelo-app:X.Y.Z`.

## [1.1.1] - 2026-11-27

### Adicionado

- Documentos de entrega em `docs/entregas`: documentação do sistema no padrão ABNT, briefing, PDF de evidências de DevOps e slides das duas apresentações.
- Evidências em `docs/evidencias` (implantações, rollback, detecção de falha, Prometheus, Grafana e dados do GitHub) e as ferramentas que geram tudo em `docs/ferramentas`, com o passo a passo em `docs/COMO-REFAZER.md`.

## [1.1.0] - 2026-11-27

### Adicionado

- Previsão de conclusão de cada meta no ritmo dos últimos 3 meses (RN14): o cartão da meta diz em quantos meses ela fica pronta e em que mês, e a API devolve `ritmo_mensal`, `previsao_meses` e `previsao_rotulo` em `/api/metas`.

### Corrigido

- O script de implantação no Windows (`implantar.ps1`) agora segue para a imagem anexada à release quando o GHCR recusa o download, em vez de parar com erro.

## [1.0.0] - 2026-11-26

Primeira versão entregue.

### Adicionado

- Cadastro com senha forte, login, sessão e proteção CSRF; a conta principal é criada no cadastro.
- Visão geral do mês: saldo, receitas e despesas com variação, fluxo de caixa de 6 meses, gastos por categoria, orçamento, últimas transações e meta em destaque.
- Transações (cadastrar, consultar, alterar e excluir) com filtros, calendário de período, abas por tipo, ordenação, paginação e exportação em CSV.
- Assinaturas como um tipo de transação, com a cobrança lançada sozinha todo mês e aviso antes do vencimento.
- Minhas contas com saldo calculado, patrimônio e transferências.
- Metas com depósitos ("guardar dinheiro") e simulador de quanto guardar por mês, com rendimento.
- Relatórios com evolução mensal, comparação por categoria, taxa de economia, mapa de gastos no estilo do GitHub e CSV.
- Notificações, configurações de perfil, orçamento e senha, e central de ajuda.
- API REST em `/api/...` com respostas JSON e códigos HTTP corretos.
- Banco MySQL 8.4 com criação automática das tabelas e dados de demonstração.
- Testes com PHPUnit (unitários e de integração com MySQL) e testes do JavaScript com `node --test`.
- CI no GitHub Actions: validação, testes, `composer audit`, Gitleaks, construção da imagem, teste de fumaça e Trivy.
- Imagem Docker publicada no GHCR, Docker Compose com perfis de testes e de observabilidade.
- `/health`, `/metrics`, logs em JSON, Prometheus com regras de alerta e painel do Grafana.
- Release versionada com homologação automática e script de implantação com rollback automático.
- Auditoria de acessibilidade com axe-core: todas as páginas atendem à WCAG 2.2 AA.

### Segurança

- Login bloqueado por 15 minutos depois de 5 senhas erradas do mesmo e-mail e IP (resposta 429 com `Retry-After`).
- Exportação em CSV protegida contra injeção de fórmula no Excel.
- Pipeline com PHPStan, zizmor e hadolint; actions fixadas pelo SHA do commit, permissões mínimas e imagem base fixada pelo digest.

[1.1.1]: https://github.com/DanMarangon/cofrelo-app/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/DanMarangon/cofrelo-app/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/DanMarangon/cofrelo-app/releases/tag/v1.0.0
