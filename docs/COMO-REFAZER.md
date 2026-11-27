# Como refazer as evidências e os documentos

Todos os documentos de entrega são gerados por scripts. Para mudar um nome, trocar a dupla de DevOps ou refazer as capturas, edite os dados e rode de novo. Nada precisa ser montado à mão.

## O que fica em cada pasta

| Pasta | Conteúdo |
|---|---|
| `docs/entregas` | Documentos finais: documentação ABNT (Word e PDF), briefing, PDF de evidências de DevOps e slides das duas apresentações |
| `docs/evidencias` | Material que os documentos usam: capturas de tela, diagramas, wireframes, logs de implantação, observabilidade e dados do GitHub |
| `docs/ferramentas` | Scripts que geram tudo e o arquivo `projeto.json` com os dados do grupo |
| `docs/prototipo` | Telas do protótipo no Figma |

## Preparar o computador (uma vez)

- Python 3.11 ou mais novo: `python -m pip install -r docs/ferramentas/requirements.txt`
- Microsoft Edge (capturas e PDF de evidências), Word e PowerPoint (exportar os PDFs da documentação e dos slides)
- Docker Desktop, para a aplicação, e GitHub CLI autenticado (`gh auth login`), para a coleta dos dados do GitHub

## Trocar nomes, dupla de DevOps, link do Figma ou referências

1. Edite `docs/ferramentas/projeto.json`:
   - `devops.dupla`: nome e RA do segundo integrante da dupla. Enquanto estiver vazio, os documentos de DevOps mostram um aviso em vermelho.
   - `dw.integrantes`: nomes completos e RAs do grupo de DW.
   - `figma`: link do protótipo. Com o link preenchido, a documentação passa a citá-lo.
   - `referencias_extras`: o livro-texto da disciplina ou outras referências, no formato ABNT.
2. Gere os documentos de novo:

   ```powershell
   python docs/ferramentas/gerar_tudo.py --so-documentos
   ```

## Refazer tudo, inclusive as capturas

```powershell
docker compose up -d
docker compose --profile observabilidade up -d
python docs/ferramentas/gerar_tudo.py
```

O script captura as telas, desenha os diagramas e os wireframes, roda a auditoria de acessibilidade, captura o Prometheus e o Grafana, coleta os dados do GitHub e gera os documentos.

## Refazer as demonstrações de implantação e de falha

Elas trocam a versão em execução e param o banco por alguns segundos. Por isso não fazem parte do `gerar_tudo.py`.

```powershell
.\scripts\implantar.ps1 -Versao 1.0.0               # deixa a 1.0.0 no ar
.\docs\ferramentas\demonstrar_implantacao.ps1       # versão quebrada com rollback automático, atualização e rollback manual
python docs/ferramentas/demonstrar_falha.py         # para o MySQL, mostra o 503, os logs e o alerta, e religa
python docs/ferramentas/gerar_tudo.py --so-documentos
```

Os logs vão para `docs/evidencias/implantacao` e `docs/evidencias/observabilidade`.

## Montar o pacote de entrega de DW (Moodle)

```powershell
python docs/ferramentas/gerar_tudo.py --so-documentos --zip
```

O arquivo `Cofrelo - Grupo 2 - Entrega DW.zip` vai para a pasta Downloads. Ele traz a documentação, o briefing, os slides e o código da aplicação.

## O que cada script faz

| Script | Faz |
|---|---|
| `gerar_tudo.py` | Roda os scripts abaixo na ordem certa |
| `capturar_telas.py` | Entra com a usuária de demonstração e captura as 15 telas |
| `diagramas.py` | Diagramas de caso de uso, de fluxo, do banco de dados e da arquitetura de CI/CD |
| `wireframes.py` | Wireframes das 10 páginas |
| `auditar_acessibilidade.py` | Auditoria WCAG 2.2 AA com o axe-core em todas as páginas, no computador e no celular |
| `capturar_observabilidade.py` | Capturas do Prometheus e do painel do Grafana |
| `coletar_evidencias.py` | Pull Requests, execuções do GitHub Actions e releases (`docs/evidencias/pipeline/dados.json`) |
| `demonstrar_implantacao.ps1` | Demonstrações do script de implantação |
| `demonstrar_falha.py` | Demonstração de como uma falha é identificada |
| `gerar_briefing.py` | Briefing (Word e PDF) |
| `gerar_documentacao.py` | Documentação do sistema no padrão ABNT (Word e PDF) |
| `gerar_evidencias_devops.py` | PDF de evidências de DevOps |
| `gerar_tecnologias.py` | Documento das tecnologias e do que cada uma soma ao projeto (`docs/TECNOLOGIAS.md` e PDF) |
| `gerar_slides.py` | Slides das apresentações de DW e de DevOps (PowerPoint e PDF), com notas do apresentador |
| `conteudo.py` | Textos compartilhados: requisitos, regras de negócio, briefing e uso do JavaScript |
| `abnt.py`, `navegador.py` | Formatação ABNT no Word e controle do Edge |
