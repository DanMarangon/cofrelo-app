"""Gera o PDF de evidências de DevOps (Integração DevOps) a partir dos dados coletados do GitHub e dos logs.

Antes: python docs/ferramentas/coletar_evidencias.py (dados do GitHub) e as demonstrações de implantação
(docs/evidencias/implantacao) e de observabilidade (docs/evidencias/observabilidade).

Uso: python docs/ferramentas/gerar_evidencias_devops.py
Saída: docs/entregas/Evidências DevOps - Cofrelo.pdf
"""

from __future__ import annotations

import html
import json
import sys
import tempfile
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import conteudo as c  # noqa: E402
from navegador import Navegador  # noqa: E402

PIPELINE = c.EVIDENCIAS / "pipeline"
IMPLANTACAO = c.EVIDENCIAS / "implantacao"
OBSERVABILIDADE = c.EVIDENCIAS / "observabilidade"

CSS = """
@page { size: A4; margin: 16mm 15mm 16mm 15mm; }
* { box-sizing: border-box; }
body { font-family: Inter, "Segoe UI", Arial, sans-serif; color: #182D26; font-size: 10.2pt; line-height: 1.45; margin: 0; }
h1, h2, h3 { color: #165A3F; line-height: 1.2; }
h1 { font-size: 21pt; margin: 0 0 6px; }
h2 { font-size: 14.5pt; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #DBEEA3; break-after: avoid; }
h3 { font-size: 11.5pt; margin: 14px 0 6px; break-after: avoid; }
p { margin: 0 0 7px; }
a { color: #1D704F; word-break: break-all; }
code, pre { font-family: Consolas, "Cascadia Mono", monospace; }
code { background: #EEF4EC; padding: 1px 4px; border-radius: 4px; font-size: 9pt; }
pre { background: #F4F8F2; border: 1px solid #DDE7DA; border-left: 4px solid #1D704F; border-radius: 6px; padding: 8px 10px;
      font-size: 8.2pt; line-height: 1.35; white-space: pre-wrap; word-break: break-word; margin: 6px 0 10px; break-inside: avoid; }
table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; font-size: 8.8pt; break-inside: auto; }
th, td { border: 1px solid #D5E0D2; padding: 4px 6px; text-align: left; vertical-align: top; }
th { background: #E8F2E6; color: #165A3F; }
tr { break-inside: avoid; }
.capa { height: 260mm; display: flex; flex-direction: column; justify-content: space-between; break-after: page; }
.capa .faixa { background: #1D704F; color: #fff; border-radius: 14px; padding: 26px 28px; }
.capa .faixa h1 { color: #fff; font-size: 26pt; }
.capa .faixa p { color: #DBEEA3; font-size: 12pt; margin: 0; }
.capa dl { display: grid; grid-template-columns: 150px 1fr; gap: 6px 14px; font-size: 10.5pt; }
.capa dt { color: #616A64; } .capa dd { margin: 0; font-weight: 600; }
.pendente { color: #9A3412; font-weight: 700; }
.figura { margin: 8px 0 14px; text-align: center; break-inside: avoid; }
.figura img { max-width: 100%; max-height: 190mm; border: 1px solid #D5E0D2; border-radius: 6px; }
.figura figcaption { font-size: 8.6pt; color: #616A64; margin-top: 3px; }
.ok { color: #1D704F; font-weight: 700; } .falha { color: #B42318; font-weight: 700; } .pulado { color: #616A64; }
.nota { background: #FFF8E6; border: 1px solid #F1D9A6; border-radius: 6px; padding: 7px 10px; margin: 8px 0; font-size: 9.4pt; }
.caixa { background: #F4F8F2; border: 1px solid #DDE7DA; border-radius: 8px; padding: 8px 12px; margin: 8px 0; }
.colunas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.sumario li { margin: 2px 0; }
"""


def e(texto) -> str:
    return html.escape(str(texto if texto is not None else ""))


def link(url: str, texto: str | None = None) -> str:
    return f'<a href="{e(url)}">{e(texto or url)}</a>'


def figura(caminho: Path, legenda: str) -> str:
    if not caminho.exists():
        return f'<p class="nota">Imagem ainda não gerada: {e(caminho.name)}</p>'
    return f'<figure class="figura"><img src="{caminho.resolve().as_uri()}"><figcaption>{e(legenda)}</figcaption></figure>'


def arquivo_texto(caminho: Path, maximo: int = 60) -> str:
    if not caminho.exists():
        return f'<p class="nota">Log ainda não gerado: {e(caminho.name)}</p>'
    linhas = caminho.read_text(encoding="utf-8-sig").splitlines()
    if len(linhas) > maximo:
        linhas = linhas[:maximo] + [f"... ({len(linhas) - maximo} linhas omitidas)"]
    return f"<pre>{e(chr(10).join(linhas))}</pre>"


def conclusao(valor: str | None) -> str:
    classes = {"success": ("ok", "sucesso"), "failure": ("falha", "falha"), "skipped": ("pulado", "pulado"),
               "cancelled": ("pulado", "cancelado")}
    classe, texto = classes.get(valor or "", ("pulado", valor or "—"))
    return f'<span class="{classe}">{texto}</span>'


def tabela(cabecalho: list[str], linhas: list[list[str]]) -> str:
    th = "".join(f"<th>{e(h)}</th>" for h in cabecalho)
    corpo = "".join("<tr>" + "".join(f"<td>{celula}</td>" for celula in linha) + "</tr>" for linha in linhas)
    return f"<table><thead><tr>{th}</tr></thead><tbody>{corpo}</tbody></table>"


def gerar() -> Path:
    p = c.projeto()
    dados = json.loads((PIPELINE / "dados.json").read_text(encoding="utf-8")) if (PIPELINE / "dados.json").exists() else {}
    repo = p["repositorio"]
    dupla = c.dupla_devops()
    destaques = dados.get("destaques", {})
    partes: list[str] = []

    # ------------------------------------------------------------------ capa
    nomes = "".join(
        f'<dd class="{"pendente" if pessoa.get("pendente") else ""}">{e(c.nome_com_ra(pessoa))}</dd>' if i == 0 else
        f'<dt></dt><dd class="{"pendente" if pessoa.get("pendente") else ""}">{e(c.nome_com_ra(pessoa))}</dd>'
        for i, pessoa in enumerate(dupla))
    partes.append(f"""
<section class="capa">
  <div class="faixa"><p>{e(p['devops']['disciplina'])} · projeto final</p><h1>Evidências do projeto: {e(p['projeto'])}</h1>
  <p>{e(p['subtitulo'])}</p></div>
  <dl>
    <dt>Instituição</dt><dd>{e(p['instituicao'])} · {e(p['curso'])}</dd>
    <dt>Disciplina</dt><dd>{e(p['devops']['disciplina'])}{(' · Prof. ' + e(p['devops']['professor'])) if p['devops']['professor'] else ''}</dd>
    <dt>Dupla</dt>{nomes}
    <dt>Repositório</dt><dd>{link(repo)}</dd>
    <dt>Imagem Docker</dt><dd><code>{e(p['imagem_docker'])}</code></dd>
    <dt>Gerado em</dt><dd>{datetime.now().strftime('%d/%m/%Y %H:%M')}</dd>
  </dl>
  <ol class="sumario">
    <li>Aplicação</li><li>Integrantes e colaboração</li><li>Tecnologias</li><li>Versionamento e branches</li>
    <li>Pipeline de CI</li><li>Execuções com sucesso e com falha</li><li>Testes automatizados</li><li>Artefato</li>
    <li>Entrega e implantação</li><li>Infraestrutura e configuração</li><li>Segurança</li><li>Monitoramento e observabilidade</li>
  </ol>
</section>""")

    # ------------------------------------------------------------------ 1 aplicação
    funcionalidades = "".join(f"<li><b>{e(n)}</b>: {e(d)}</li>" for n, d in c.FUNCIONALIDADES)
    partes.append(f"""
<h2>1. Aplicação</h2>
<p>{e(c.DESCRICAO_CURTA)}</p>
<p><b>Problema:</b> {e(c.PROBLEMA)}</p><p><b>Objetivo:</b> {e(c.OBJETIVO)}</p>
<ul>{funcionalidades}</ul>
<h3>API REST</h3>
<p>O mesmo PHP que monta as páginas responde JSON nas rotas <code>/api/...</code>, com sessão, token CSRF e códigos HTTP corretos
(200, 201, 204, 401, 403, 404, 409, 422 e 429). Exemplo para testar:</p>
<pre>curl -c cookies.txt -H "Content-Type: application/json" \\
     -d '{{"email":"mariana@cofrelo.app","senha":"cofrelo123"}}' http://localhost:8080/api/auth/entrar
curl -b cookies.txt http://localhost:8080/api/resumo
curl http://localhost:8080/health</pre>
{tabela(["Recurso", "Rotas"], [
        ["Autenticação", "<code>POST /api/auth/cadastro</code>, <code>POST /api/auth/entrar</code>, <code>POST /api/auth/sair</code>, <code>GET /api/auth/eu</code>"],
        ["Transações", "<code>GET/POST /api/transacoes</code>, <code>GET/PUT/DELETE /api/transacoes/{{id}}</code>, <code>GET /api/transacoes/exportar</code>"],
        ["Contas, metas e assinaturas", "<code>/api/contas</code>, <code>/api/metas</code> (com <code>POST /api/metas/{{id}}/depositos</code>), <code>/api/assinaturas</code>"],
        ["Painéis", "<code>GET /api/resumo</code>, <code>GET /api/relatorio</code>, <code>GET /api/notificacoes</code>, <code>GET /api/categorias</code>"],
        ["Operação", "<code>GET /health</code> e <code>GET /metrics</code>"],
    ])}""")

    # ------------------------------------------------------------------ 2 integrantes
    prs = dados.get("prs", [])
    pendente = any(pessoa.get("pendente") for pessoa in dupla)
    aviso = ('<p class="nota"><b>Pendente:</b> o nome do segundo integrante da dupla ainda não foi informado. Ele deve ser '
             'preenchido em <code>docs/ferramentas/projeto.json</code> e este PDF gerado de novo. A colaboração dele '
             '(revisão de Pull Requests e commits próprios) também precisa aparecer no repositório.</p>') if pendente else ""
    partes.append(f"""
<h2>2. Integrantes e colaboração</h2>
{tabela(["Integrante", "RA"], [[e(pessoa['nome']), e(pessoa.get('ra') or '—')] for pessoa in dupla])}
{aviso}
<p>Todo o trabalho entrou por branches e Pull Requests: {len(prs)} Pull Requests até a coleta de {e(dados.get('coletado_em', '')[:10])}, cada um com o CI rodando antes do merge.
Os commits gerados com apoio de IA levam a linha <code>Co-Authored-By</code>, para o histórico deixar isso claro.</p>""")

    # ------------------------------------------------------------------ 3 tecnologias
    partes.append(f"<h2>3. Tecnologias</h2>{tabela(['Camada', 'Tecnologia'], [[e(a), e(b)] for a, b in c.TECNOLOGIAS])}")

    # ------------------------------------------------------------------ 4 versionamento
    linhas_pr = [[f'<span style="white-space:nowrap">{link(pr["url"], f"#{pr['number']}")}</span>', e(pr["title"]), f"<code>{e(pr['headRefName'])}</code> → <code>{e(pr['baseRefName'])}</code>",
                  e({"MERGED": "mesclado", "OPEN": "aberto", "CLOSED": "fechado"}.get(pr["state"], pr["state"])), e(pr["autor"])]
                 for pr in prs]
    partes.append(f"""
<h2>4. Versionamento e branches</h2>
<p><b>Estratégia: GitFlow.</b> A <code>main</code> só recebe versões entregues, sempre vindas de uma <code>release/*</code> e marcadas com uma tag
<code>vX.Y.Z</code>; a <code>develop</code> integra o que está pronto; cada funcionalidade nasce numa <code>feature/*</code>; correções urgentes iriam
numa <code>hotfix/*</code>. Escolhemos o GitFlow porque a disciplina pede versões rastreáveis (a mesma versão na tag, na release e na imagem) e
porque ele separa o que está em desenvolvimento do que já foi entregue. Os commits seguem o padrão <code>tipo: descrição</code>
(<code>feat</code>, <code>fix</code>, <code>ci</code>, <code>docs</code>, <code>chore</code>) e os merges preservam os commits das branches.</p>
<p>Branches no repositório: {', '.join(f'<code>{e(b)}</code>' for b in dados.get('branches', []))}.
Tags: {', '.join(f'<code>{e(t)}</code>' for t in dados.get('tags', []))}.</p>
{tabela(["PR", "Título", "Branches", "Situação", "Autor"], linhas_pr)}""")

    # ------------------------------------------------------------------ 5 pipeline
    resumo = dados.get("resumo_ci", {})
    partes.append(f"""
<h2>5. Pipeline de CI</h2>
<p>O workflow <code>.github/workflows/ci.yml</code> roda sozinho em todo <code>push</code> e em todo Pull Request para <code>develop</code> e
<code>main</code>. São três jobs; o da imagem só roda se a validação e a segurança passarem.</p>
{tabela(["Job", "Etapas automáticas"], [
        ["Validação e testes", "checkout; preparar PHP 8.3 e Node 22; <code>composer validate</code>; instalar dependências com cache; verificar a sintaxe de todo arquivo PHP (<code>php -l</code>); análise estática com PHPStan nível 6; testes do JavaScript; PHPUnit com um MySQL de serviço; publicar o resultado dos testes e guardar os relatórios JUnit"],
        ["Verificações de segurança", "<code>composer audit</code>; Gitleaks no histórico; zizmor nos workflows; hadolint no Dockerfile"],
        ["Imagem Docker", "construir a imagem de produção; subir com um MySQL e testar <code>/health</code>, login e API; Trivy (vulnerabilidade crítica com correção bloqueia); publicar no GHCR nos pushes da <code>develop</code> e da <code>main</code>"],
    ])}
<p>O workflow <code>.github/workflows/release.yml</code> roda quando uma tag <code>vX.Y.Z</code> é criada: publica a imagem versionada, cria a
release com a imagem anexada e homologa a versão publicada (seção 8).</p>
<p class="caixa">Histórico do CI até a coleta: <b>{resumo.get('total', '—')}</b> execuções, <span class="ok">{resumo.get('sucesso', '—')} com sucesso</span>,
<span class="falha">{resumo.get('falha', '—')} com falha</span> e {resumo.get('cancelada', '—')} canceladas (um push novo na mesma branch cancela a
execução anterior). Lista completa: {link(repo + '/actions')}</p>""")

    # ------------------------------------------------------------------ 6 sucesso e falha
    def bloco_execucao(titulo: str, execucao: dict | None) -> str:
        if not execucao:
            return f'<p class="nota">{e(titulo)}: execução não encontrada na coleta.</p>'
        jobs_html = "".join(f"<li>{e(j['nome'])}: {conclusao(j['conclusao'])}</li>" for j in execucao.get("jobs", []))
        return (f"<h3>{e(titulo)}</h3><p>{link(execucao['url'])} · branch <code>{e(execucao['headBranch'])}</code> · "
                f"{e(execucao['event'])} · {conclusao(execucao['conclusion'])}</p>" + (f"<ul>{jobs_html}</ul>" if jobs_html else ""))

    falha = destaques.get("falha_controlada")
    correcao = destaques.get("correcao")
    partes.append(f"""
<h2>6. Execuções com sucesso e com falha</h2>
{bloco_execucao("Execução com sucesso (versão na main)", destaques.get("sucesso_main"))}
{bloco_execucao("Falha controlada (PR #11, primeiro commit)", falha)}
<div class="caixa">
<p><b>O que foi feito:</b> a funcionalidade de previsão de conclusão das metas (versão 1.1.0) foi enviada primeiro com um erro real e
proposital no cálculo: <code>intdiv($falta, $ritmo)</code>, uma divisão inteira que arredonda para baixo.</p>
<p><b>Como foi identificada:</b> o job "Validação e testes" ficou vermelho. Dois testes falharam: o unitário
<code>testPrevisaoArredondaParaCimaQuandoSobraUmRestinho</code> (faltando R$ 8.000 e guardando R$ 1.500 por mês, esperava 6 meses e recebeu 5) e o de
integração <code>testMetasTrazemAPrevisaoNoRitmoDosUltimosTresMeses</code> (esperava 7 e recebeu 6). Como a validação falhou, o job da imagem foi pulado e
nenhuma imagem com o erro foi publicada. O resultado aparece no check "Resultado dos testes" do Pull Request.</p>
<p><b>Correção:</b> o commit seguinte arredonda para cima, <code>intdiv($falta + $ritmo - 1, $ritmo)</code>, porque o resto que sobra só entra no mês
seguinte. O CI voltou a ficar verde e o Pull Request foi mesclado.</p>
</div>
<p>Trecho do log da execução com falha:</p>
{arquivo_texto(PIPELINE / "falha-controlada-log.txt")}
{bloco_execucao("Execução com a correção (PR #11, segundo commit)", correcao)}
<h3>Outras falhas reais corrigidas durante o projeto</h3>
{tabela(["Falha", "Causa", "Correção"], [
        [link(destaques['falha_gitleaks']['url'], 'Gitleaks (PR #1)') if destaques.get('falha_gitleaks') else 'Gitleaks (PR #1)',
         "A action do Gitleaks recebeu 403 ao listar os commits do Pull Request: o token do job não tinha permissão de leitura de Pull Requests.",
         "<code>pull-requests: read</code> no job de segurança."],
        [link(destaques['falha_trivy_tag']['url'], 'Trivy (PR #5)') if destaques.get('falha_trivy_tag') else 'Trivy (PR #5)',
         "A tag <code>0.28.0</code> da action do Trivy deixou de existir (as tags foram refeitas com o prefixo v depois de um ataque à cadeia de suprimentos).",
         "Uso da tag <code>v0.36.0</code> e, depois, das actions fixadas pelo SHA do commit."],
        ["Dependabot (PRs #13 a #15)", "Execuções do Dependabot não recebem os segredos do Actions; sem <code>MYSQL_SENHA_TESTES</code>, o MySQL de testes não sobe.",
         "Cadastrar o mesmo segredo em Settings → Secrets and variables → Dependabot."],
    ])}""")

    # ------------------------------------------------------------------ 7 testes
    partes.append(f"""
<h2>7. Testes automatizados</h2>
<p>81 casos, todos rodando no CI a cada push. Se um teste falhar, o job falha e a imagem não é publicada (como mostra a seção 6).</p>
{tabela(["Conjunto", "Framework", "Casos", "O que cobre"], [
        ["<code>tests/Unitario</code>", "PHPUnit 11", "39", "validação de transações, metas (aporte e previsão), indicadores, formatação, roteador, limite de tentativas de login e proteção do CSV"],
        ["<code>tests/Integracao</code>", "PHPUnit 11 + MySQL", "26", "repositórios, dados de demonstração, endpoints da API (login, CSRF, CRUD, 401, 404, 405, 409, 422, 429) e páginas"],
        ["<code>tests/js</code>", "node --test", "16", "formatação, máscara de valores, cálculos do simulador, sugestão de categoria, datas e força da senha"],
    ])}
<p>O resultado aparece no GitHub Actions de três formas: a saída do PHPUnit no log (<code>--testdox</code>), o check "Resultado dos testes" com cada caso
(action-junit-report) e os relatórios JUnit guardados como artefato da execução.</p>""")

    # ------------------------------------------------------------------ 8 artefato
    releases = dados.get("releases", [])
    linhas_rel = []
    for rel in releases:
        arquivos = "<br>".join(f"{link(a['url'], a['nome'])} ({a['tamanho'] / 1048576:.1f} MB)" for a in rel.get("assets", []))
        linhas_rel.append([link(rel.get("url", ""), rel.get("tagName", "")), e((rel.get("publishedAt") or "")[:10]), arquivos])
    workflow_rel = dados.get("releases_workflow", [])
    linhas_wf = [[link(r["url"], r["headBranch"]), conclusao(r["conclusion"]),
                  "<br>".join(f"{e(j['nome'])}: {conclusao(j['conclusao'])}" for j in r.get("jobs", []))] for r in workflow_rel]
    versoes = dados.get("imagem", {}).get("versoes", [])
    linhas_img = [[", ".join(f"<code>{e(t)}</code>" for t in v["tags"]) or "—", f"<code>{e((v['digest'] or '')[:19])}…</code>", e((v["criada"] or "")[:10])]
                  for v in versoes if v["tags"]][:12]
    partes.append(f"""
<h2>8. Artefato</h2>
<p>O artefato é a <b>imagem Docker</b> da aplicação, construída uma única vez pelo pipeline a partir do <code>Dockerfile</code> (multi-stage, estágio
<code>producao</code>) e publicada no GitHub Container Registry: <code>{e(p['imagem_docker'])}</code>.</p>
<ul>
<li><b>Rastreabilidade:</b> tags <code>X.Y.Z</code>, <code>X.Y</code> e <code>latest</code> nas releases, <code>develop</code> e <code>main</code> nos pushes e <code>sha-&lt;commit&gt;</code> em todas;
a versão e o commit ficam na imagem (labels OCI e variáveis <code>APP_VERSION</code> e <code>APP_COMMIT</code>) e aparecem no <code>/health</code>.</li>
<li><b>Release do GitHub:</b> cada versão anexa a imagem (<code>docker save</code>), o arquivo <code>imagem-X.Y.Z.txt</code> com digest, ID, commit e link da
execução, o <code>SHA256SUMS</code> e os arquivos de implantação.</li>
</ul>
{tabela(["Release", "Publicada", "Arquivos anexados"], linhas_rel) if linhas_rel else '<p class="nota">Releases não encontradas na coleta.</p>'}
<h3>Execuções do workflow Release</h3>
{tabela(["Tag", "Resultado", "Jobs"], linhas_wf) if linhas_wf else '<p class="nota">Sem execuções do workflow Release na coleta.</p>'}
{('<h3>Versões da imagem no GHCR</h3>' + tabela(["Tags", "Digest", "Criada"], linhas_img)) if linhas_img else ''}
{''.join(f"<h3>Identificação da imagem {e(r.get('tagName', ''))} (imagem-{e(r.get('tagName', '').removeprefix('v'))}.txt, anexado à release)</h3><pre>{e(r.get('imagem_txt', '').strip())}</pre>" for r in releases if r.get('imagem_txt'))}""")

    # ------------------------------------------------------------------ 9 implantação
    partes.append(f"""
<h2>9. Entrega e implantação</h2>
<p><b>Estratégia: recreate com verificação de saúde e rollback automático</b>, em ambiente local com Docker Compose (a nuvem não é obrigatória).
O banco fica no volume <code>dados-mysql</code> e não é recriado nas trocas de versão.</p>
{tabela(["Etapa", "Onde acontece", "O que faz"], [
        ["Construção", "GitHub Actions", "a imagem é construída uma vez a partir do commit"],
        ["Teste", "GitHub Actions", "testes no código (CI), teste de fumaça na imagem e homologação da versão publicada"],
        ["Entrega", "GHCR e release do GitHub", "a imagem versionada fica disponível no registro e anexada à release"],
        ["Implantação", "computador de execução", "<code>scripts/implantar.ps1</code> coloca uma versão no ar e confere o <code>/health</code>"],
    ])}
<p><b>Como o artefato chega ao ambiente:</b> o script tenta <code>docker pull</code> no GHCR; sem acesso (o pacote é privado), baixa a imagem anexada à
release com o GitHub CLI, faz <code>docker load</code> e confere se o ID é o mesmo que o pipeline publicou. Depois troca o container
(<code>docker compose up -d --no-build --force-recreate app</code>), espera o <code>/health</code> responder <code>ok</code> com a versão certa por até 60 s e, se a
versão nova não ficar saudável, volta sozinho para a anterior. Cada implantação fica registrada em <code>.deploy/historico.log</code>.</p>
<p><b>Atualizar:</b> <code>.\\scripts\\implantar.ps1 -Versao 1.1.0</code>. <b>Voltar (rollback manual):</b> <code>.\\scripts\\implantar.ps1 -Voltar</code>.
<b>Simular uma versão quebrada:</b> <code>-SimularFalha</code> liga <code>SIMULAR_FALHA</code>, que faz o <code>/health</code> responder 503.</p>
<h3>1) Primeira implantação: versão 1.0.0</h3>{arquivo_texto(IMPLANTACAO / "01-implantacao-1.0.0.txt")}
<h3>2) Versão 1.1.0 quebrada: rollback automático</h3>{arquivo_texto(IMPLANTACAO / "02-falha-e-rollback-automatico.txt")}
<h3>3) Atualização para a versão 1.1.0</h3>{arquivo_texto(IMPLANTACAO / "03-atualizacao-1.1.0.txt")}
<h3>4) Rollback manual para a 1.0.0 e volta para a 1.1.0</h3>{arquivo_texto(IMPLANTACAO / "04-rollback-manual.txt")}
<h3>Histórico de implantações</h3>{arquivo_texto(IMPLANTACAO / "historico-de-implantacoes.txt")}""")

    # ------------------------------------------------------------------ 10 infraestrutura
    partes.append(f"""
<h2>10. Infraestrutura e configuração</h2>
<p>Para rodar: Docker 24 ou mais novo com Compose v2, cerca de 1 GB de memória livre e as portas 8080 (aplicação), 9090 (Prometheus) e 3000
(Grafana). O <code>docker-compose.yml</code> sobe o MySQL 8.4 e a aplicação juntos (perfis extras: <code>testes</code> e <code>observabilidade</code>), com
verificação de saúde nos dois e a aplicação esperando o banco ficar saudável.</p>
{tabela(["Variável", "Para que serve"], [
        ["<code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_ROOT_PASS</code>", "conexão com o MySQL"],
        ["<code>COFRELO_VERSAO</code>, <code>COFRELO_IMAGEM</code>", "versão e imagem usadas pelo Compose e pelo script de implantação"],
        ["<code>SEED_DEMO</code>, <code>AUTO_MIGRATE</code>", "dados de demonstração e criação automática das tabelas"],
        ["<code>LOG_DESTINO</code>", "logs no stderr (padrão) ou em arquivo"],
        ["<code>SIMULAR_FALHA</code>", "só para a demonstração do rollback"],
        ["<code>GRAFANA_SENHA</code>", "senha do administrador do Grafana"],
    ])}
<p>Toda configuração fica fora do código: o <code>.env</code> está no <code>.gitignore</code> e o <code>.env.example</code> mostra as variáveis com valores de exemplo.</p>""")

    # ------------------------------------------------------------------ 11 segurança
    partes.append(f"""
<h2>11. Segurança</h2>
{tabela(["Prática", "Como protege"], [
        ["Nenhum segredo no repositório", "<code>.env</code> fora do Git; a senha do MySQL de testes do CI fica no GitHub Secrets; o GHCR usa o <code>GITHUB_TOKEN</code> automático. O Gitleaks confere o histórico em todo push."],
        ["Dependências e imagem", "<code>composer audit</code> em todo push; Trivy na imagem (crítica com correção bloqueia); Dependabot mensal para Composer, actions e imagem base; imagem base fixada pelo digest."],
        ["Pipeline", "actions fixadas pelo SHA do commit (uma tag pode ser trocada por código malicioso); permissões mínimas por job; checkout sem guardar o token; release construída sem cache; zizmor confere os workflows."],
        ["Container", "Apache como <code>www-data</code> (sem root) na porta 8080; sem ferramentas de compilação; <code>expose_php</code> e assinatura do servidor desligados; hadolint confere o Dockerfile."],
        ["Aplicação", "bcrypt nas senhas; consultas preparadas (sem SQL injection); texto escapado (sem XSS); token CSRF; cookie HttpOnly e SameSite; login bloqueado por 15 min depois de 5 senhas erradas (429); CSV protegido contra fórmulas; Content-Security-Policy."],
    ])}
<p>Juntas, essas práticas impedem que uma senha vaze pelo Git, que uma dependência ou imagem vulnerável chegue à produção sem aviso, que uma
action comprometida rode no pipeline e que os ataques web mais comuns funcionem.</p>""")

    # ------------------------------------------------------------------ 12 observabilidade
    partes.append(f"""
<h2>12. Monitoramento e observabilidade</h2>
<ul>
<li><b><code>/health</code></b>: responde <code>status</code>, <code>versao</code>, <code>commit</code> e o estado do banco, ou 503 quando algo falha. É usado pelo
<code>HEALTHCHECK</code> do Docker, pelo pipeline e pelo script de implantação.</li>
<li><b>Logs em JSON</b>, uma linha por evento, com o id da requisição: <code>http.requisicao</code>, <code>autenticacao.falha</code>,
<code>autenticacao.bloqueada</code>, <code>transacao.criada</code>, <code>banco.erro</code>, <code>saude.falha</code>…</li>
<li><b><code>/metrics</code></b> no formato do Prometheus, e o perfil <code>observabilidade</code> com Prometheus (4 regras de alerta) e Grafana (painel pronto).</li>
</ul>
{figura(OBSERVABILIDADE / "normal-grafana-painel.png", "Painel do Grafana com a aplicação saudável")}
{figura(OBSERVABILIDADE / "normal-prometheus-alertas.png", "Regras de alerta do Prometheus, todas inativas com a aplicação saudável")}
<h3>Como identificar uma falha depois da implantação</h3>
<p>Demonstração: com a versão 1.1.0 no ar, o MySQL foi parado (<code>docker compose stop banco</code>). Em segundos, o <code>/health</code> passou a responder 503,
o log registrou <code>saude.falha</code> e <code>banco.erro</code>, o Docker marcou o container como <code>unhealthy</code> e o Prometheus disparou o alerta
<code>BancoIndisponivel</code>. Com o banco de volta (<code>docker compose start banco</code>), tudo voltou ao normal sozinho. O alerta
<code>MuitosErrosInternos</code> também aparece disparado: ele contou as respostas 503 da versão quebrada da seção 9 e da queda do banco.</p>
{arquivo_texto(OBSERVABILIDADE / "deteccao-de-falha.txt", maximo=70)}
{figura(OBSERVABILIDADE / "falha-prometheus-alertas.png", "Alerta BancoIndisponivel disparado no Prometheus durante a falha")}
{figura(OBSERVABILIDADE / "falha-grafana-painel.png", "Painel do Grafana mostrando o banco indisponível")}""")

    corpo = "\n".join(partes)
    documento = (f'<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Evidências DevOps - {e(p["projeto"])}</title>'
                 f"<style>{CSS}</style></head><body>{corpo}</body></html>")
    pagina = Path(tempfile.gettempdir()) / "cofrelo-evidencias-devops.html"  # tem caminhos locais: fica fora do repositório
    pagina.write_text(documento, encoding="utf-8")
    pdf = c.ENTREGAS / f"Evidências DevOps - {p['projeto']}.pdf"
    pdf.parent.mkdir(parents=True, exist_ok=True)
    with Navegador(1200, 900) as nav:
        nav.abrir(pagina.resolve().as_uri(), pausa=1.5)
        nav.pdf(pdf, displayHeaderFooter=True, headerTemplate="<span></span>",
                footerTemplate=('<div style="font-size:8px;width:100%;text-align:center;color:#616A64;font-family:Arial">'
                                f'{e(p["projeto"])} · Evidências de DevOps · página <span class="pageNumber"></span> de '
                                '<span class="totalPages"></span></div>'))
    return pdf


if __name__ == "__main__":
    print(gerar())
