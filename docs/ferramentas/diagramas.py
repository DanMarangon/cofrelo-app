"""Gera os diagramas da documentação em SVG e PNG.

  * caso de uso (UML), fluxo do sistema, modelo do banco (DER) e arquitetura do CI/CD.

Uso: python docs/ferramentas/diagramas.py [pasta de saída]   (padrão: docs/diagramas)
"""

from __future__ import annotations

import sys
from html import escape
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from navegador import Navegador  # noqa: E402

SAIDA = Path(sys.argv[1] if len(sys.argv) > 1 else Path(__file__).resolve().parents[1] / "evidencias" / "diagramas")

VERDE = "#1D704F"
VERDE_CLARO = "#E8F2E6"
LIMA = "#DBEEA3"
TEXTO = "#182D26"
CINZA = "#758079"
LINHA = "#5C6B64"
FONTE = "Segoe UI, Arial, sans-serif"


class Svg:
    def __init__(self, largura: int, altura: int):
        self.largura, self.altura = largura, altura
        self.partes: list[str] = []

    def add(self, trecho: str) -> None:
        self.partes.append(trecho)

    def texto(self, x, y, conteudo, tamanho=15, peso=400, cor=TEXTO, ancora="middle", italico=False):
        linhas = conteudo.split("\n")
        y0 = y - (len(linhas) - 1) * tamanho * 0.6
        for i, linha in enumerate(linhas):
            estilo = ' font-style="italic"' if italico else ""
            self.add(f'<text x="{x}" y="{y0 + i * tamanho * 1.2:.1f}" font-family="{FONTE}" font-size="{tamanho}" '
                     f'font-weight="{peso}" fill="{cor}" text-anchor="{ancora}" dominant-baseline="middle"{estilo}>{escape(linha)}</text>')

    def linha(self, x1, y1, x2, y2, cor=LINHA, largura=1.6, tracejada=False, seta=False):
        traco = ' stroke-dasharray="7 5"' if tracejada else ""
        marcador = ' marker-end="url(#seta)"' if seta else ""
        self.add(f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{cor}" stroke-width="{largura}"{traco}{marcador}/>')

    def caminho(self, d, cor=LINHA, largura=1.6, seta=True, tracejada=False):
        marcador = ' marker-end="url(#seta)"' if seta else ""
        traco = ' stroke-dasharray="7 5"' if tracejada else ""
        self.add(f'<path d="{d}" fill="none" stroke="{cor}" stroke-width="{largura}"{traco}{marcador}/>')

    def retangulo(self, x, y, w, h, fundo="#FFFFFF", borda=VERDE, raio=8, largura=1.6):
        self.add(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{raio}" fill="{fundo}" stroke="{borda}" stroke-width="{largura}"/>')

    def elipse(self, cx, cy, rx, ry, rotulo, fundo="#FFFFFF", borda=VERDE, tamanho=14):
        self.add(f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" fill="{fundo}" stroke="{borda}" stroke-width="1.6"/>')
        self.texto(cx, cy, rotulo, tamanho)

    def losango(self, cx, cy, w, h, rotulo, tamanho=14):
        pontos = f"{cx},{cy - h / 2} {cx + w / 2},{cy} {cx},{cy + h / 2} {cx - w / 2},{cy}"
        self.add(f'<polygon points="{pontos}" fill="#FFF8E6" stroke="#C98A0B" stroke-width="1.6"/>')
        self.texto(cx, cy, rotulo, tamanho)

    def ator(self, x, y, nome):
        self.add(f'<circle cx="{x}" cy="{y}" r="15" fill="#FFFFFF" stroke="{TEXTO}" stroke-width="2"/>')
        self.linha(x, y + 15, x, y + 58, TEXTO, 2)
        self.linha(x - 26, y + 32, x + 26, y + 32, TEXTO, 2)
        self.linha(x, y + 58, x - 22, y + 92, TEXTO, 2)
        self.linha(x, y + 58, x + 22, y + 92, TEXTO, 2)
        self.texto(x, y + 112, nome, 15, 600)

    def montar(self) -> str:
        defs = (f'<defs><marker id="seta" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">'
                f'<path d="M0,0 L10,5 L0,10 z" fill="{LINHA}"/></marker>'
                f'<marker id="triangulo" viewBox="0 0 12 12" refX="11" refY="6" markerWidth="14" markerHeight="14" orient="auto-start-reverse">'
                f'<path d="M0,0 L12,6 L0,12 z" fill="#FFFFFF" stroke="{TEXTO}" stroke-width="1.2"/></marker></defs>')
        return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{self.largura}" height="{self.altura}" '
                f'viewBox="0 0 {self.largura} {self.altura}"><rect width="100%" height="100%" fill="#FFFFFF"/>'
                + defs + "".join(self.partes) + "</svg>")


def caso_de_uso() -> Svg:
    s = Svg(1500, 1080)
    s.retangulo(330, 40, 1000, 1010, fundo="#FBFDFA", borda=VERDE, raio=14, largura=2)
    s.texto(830, 72, "Sistema Cofrelo", 20, 700, VERDE)

    coluna1 = ["Cadastrar-se", "Entrar no sistema", "Sair", "Ver a visão geral do mês", "Registrar transação",
               "Registrar transferência", "Registrar assinatura", "Consultar e filtrar\ntransações por período",
               "Editar ou excluir\ntransação", "Exportar transações (CSV)", "Gerenciar contas",
               "Gerenciar assinaturas", "Ver notificações"]
    coluna2 = ["Criar e editar metas", "Guardar dinheiro na meta", "Simular meta (calculadora)",
               "Ver relatórios do período", "Ver mapa de gastos diários", "Exportar relatório (CSV)",
               "Atualizar perfil e orçamento", "Trocar senha", "Excluir a própria conta", "Consultar a ajuda",
               "Lançar cobranças\ndas assinaturas", "Gerar notificações"]
    x1, x2, topo, passo, rx, ry = 600, 1060, 125, 72, 140, 31
    pos1 = {nome: (x1, topo + i * passo) for i, nome in enumerate(coluna1)}
    pos2 = {nome: (x2, topo + i * passo) for i, nome in enumerate(coluna2)}

    visitante = (130, 150)
    usuario = (130, 560)
    usuario_direita = (1430, 330)
    sistema = (1430, 880)

    # Linhas primeiro, para ficarem atrás das elipses.
    for nome in ["Cadastrar-se", "Entrar no sistema"]:
        x, y = pos1[nome]
        s.linha(visitante[0] + 30, visitante[1] + 40, x - rx, y)
    for nome in coluna1[2:]:
        x, y = pos1[nome]
        s.linha(usuario[0] + 30, usuario[1] + 40, x - rx, y)
    for nome in coluna2[:10]:
        x, y = pos2[nome]
        s.linha(usuario_direita[0] - 30, usuario_direita[1] + 40, x + rx, y)
    for nome in coluna2[10:]:
        x, y = pos2[nome]
        s.linha(sistema[0] - 30, sistema[1] + 40, x + rx, y)

    # Usuário é um visitante que entrou no sistema (generalização).
    s.add(f'<line x1="{usuario[0]}" y1="{usuario[1] - 20}" x2="{visitante[0]}" y2="{visitante[1] + 128}" stroke="{TEXTO}" stroke-width="1.6" marker-end="url(#triangulo)"/>')

    for nome, (x, y) in {**pos1, **pos2}.items():
        sistema_auto = nome in coluna2[10:]
        s.elipse(x, y, rx, ry, nome, fundo=VERDE_CLARO if sistema_auto else "#FFFFFF", tamanho=13.5)

    # Extensões
    xa, ya = pos1["Registrar assinatura"]
    xt, yt = pos1["Registrar transação"]
    s.caminho(f"M{xa + rx - 10},{ya - 8} C{xa + rx + 70},{ya - 40} {xt + rx + 70},{yt + 40} {xt + rx - 4},{yt + 10}", tracejada=True)
    s.texto(xa + rx + 92, (ya + yt) / 2 - 36, "«extend»", 12, 400, CINZA, italico=True)
    xe, ye = pos1["Exportar transações (CSV)"]
    xc, yc = pos1["Consultar e filtrar\ntransações por período"]
    s.caminho(f"M{xe + rx - 10},{ye - 8} C{xe + rx + 70},{ye - 50} {xc + rx + 70},{yc + 50} {xc + rx - 4},{yc + 12}", tracejada=True)
    s.texto(xe + rx + 92, (ye + yc) / 2, "«extend»", 12, 400, CINZA, italico=True)

    s.ator(*visitante, "Visitante")
    s.ator(*usuario, "Usuário")
    s.ator(*usuario_direita, "Usuário")
    s.ator(*sistema, "Sistema\n(automação)")
    s.texto(830, 1030, "O ator Usuário aparece dos dois lados só para as linhas não se cruzarem. "
                       "Elipses verdes: casos de uso automáticos.", 13, 400, CINZA)
    return s


def fluxo() -> Svg:
    s = Svg(1500, 1500)

    def caixa(cx, cy, rotulo, w=260, h=56, fundo="#FFFFFF"):
        s.retangulo(cx - w / 2, cy - h / 2, w, h, fundo=fundo)
        s.texto(cx, cy, rotulo, 14)

    def terminal(cx, cy, rotulo):
        s.add(f'<rect x="{cx - 90}" y="{cy - 26}" width="180" height="52" rx="26" fill="{VERDE}" stroke="{VERDE}"/>')
        s.texto(cx, cy, rotulo, 15, 700, "#FFFFFF")

    c = 750
    terminal(c, 50, "Início")
    s.linha(c, 76, c, 112, seta=True)
    caixa(c, 140, "Abrir o Cofrelo no navegador")
    s.linha(c, 168, c, 205, seta=True)
    s.losango(c, 250, 230, 90, "Tem conta?")

    # Não tem conta: cadastro
    s.linha(c - 115, 250, 420, 250, seta=False)
    s.linha(420, 250, 420, 312, seta=True)
    s.texto(530, 236, "Não", 13, 600, CINZA)
    caixa(420, 340, "Preencher o cadastro\n(nome, e-mail, senha)", 270, 64)
    s.linha(420, 372, 420, 410, seta=True)
    s.losango(420, 455, 230, 90, "Dados válidos?")
    s.caminho("M305,455 H215 V340 H285")
    s.texto(240, 440, "Não: mostra o erro", 12, 400, CINZA, "start")
    s.linha(420, 500, 420, 560, seta=False)
    s.texto(440, 528, "Sim", 13, 600, CINZA, "start")

    # Tem conta: login
    s.linha(c + 115, 250, 1080, 250, seta=False)
    s.linha(1080, 250, 1080, 312, seta=True)
    s.texto(970, 236, "Sim", 13, 600, CINZA)
    caixa(1080, 340, "Entrar com e-mail e senha", 270)
    s.linha(1080, 368, 1080, 410, seta=True)
    s.losango(1080, 455, 250, 90, "Credenciais\ncorretas?")
    s.caminho("M1205,455 H1290 V340 H1215")
    s.texto(1300, 455, "Não: aviso\nde erro", 12, 400, CINZA, "start")
    s.linha(1080, 500, 1080, 560, seta=False)
    s.texto(1100, 528, "Sim", 13, 600, CINZA, "start")

    s.linha(420, 560, 1080, 560, seta=False)
    s.linha(c, 560, c, 600, seta=True)
    caixa(c, 630, "Visão geral do mês\n(indicadores, gráficos, notificações)", 330, 64, VERDE_CLARO)
    s.linha(c, 662, c, 705, seta=True)
    s.losango(c, 755, 250, 100, "O que deseja\nfazer?")

    opcoes = [("Registrar\ntransação", 160), ("Consultar e\nfiltrar transações", 400), ("Minhas contas\ne transferências", 640),
              ("Metas: guardar\ne simular", 880), ("Relatórios e\nexportar CSV", 1120), ("Configurações\ne ajuda", 1350)]
    s.linha(160, 870, 1350, 870, seta=False)
    s.linha(c, 805, c, 870, seta=False)
    for rotulo, x in opcoes:
        s.linha(x, 870, x, 905, seta=True)
        caixa(x, 940, rotulo, 200, 64)

    # Detalhe de "Registrar transação"
    s.linha(160, 972, 160, 1015, seta=True)
    caixa(160, 1045, "Preencher o modal\n(tipo, valor, categoria)", 230, 64)
    s.linha(160, 1077, 160, 1115, seta=True)
    s.losango(160, 1160, 200, 90, "Válido?")
    s.caminho("M60,1160 H30 V1045 H45")
    s.texto(18, 1100, "Não", 12, 600, CINZA, "start")
    s.linha(160, 1205, 160, 1245, seta=True)
    s.texto(178, 1225, "Sim", 12, 600, CINZA, "start")
    caixa(160, 1275, "Salvar no MySQL\n(API PHP)", 230, 60)
    s.linha(160, 1305, 160, 1345, seta=True)
    caixa(160, 1375, "Atualizar as telas\ne as notificações", 230, 60)

    # Depois de qualquer ação: continuar usando (volta à visão geral) ou sair.
    for _, x in opcoes[1:]:
        s.linha(x, 972, x, 1030, seta=False)
    s.linha(400, 1030, 1350, 1030, seta=False)
    s.linha(c, 1030, c, 1128, seta=True)
    s.losango(c, 1180, 260, 100, "Continuar\nusando?")
    s.caminho("M275,1375 H560 V1180 H618")
    s.caminho(f"M{c + 130},1180 H1450 V630 H917")
    s.texto(1170, 1164, "Sim: volta para a visão geral", 12, 400, CINZA)
    s.linha(c, 1230, c, 1278, seta=True)
    s.texto(c + 18, 1252, "Não", 12, 600, CINZA, "start")
    caixa(c, 1305, "Sair (encerra a sessão)", 240, 52)
    s.linha(c, 1331, c, 1372, seta=True)
    terminal(c, 1398, "Fim")
    return s


def der() -> Svg:
    s = Svg(1500, 980)
    tabelas = {
        "usuario": (60, 60, ["PK id INT", "nome VARCHAR(120)", "email VARCHAR(160) UNIQUE", "senha_hash VARCHAR(255)",
                             "orcamento_mensal DECIMAL(12,2)", "criado_em DATETIME"]),
        "conta": (560, 40, ["PK id INT", "FK usuario_id INT", "nome VARCHAR(80)", "tipo ENUM", "saldo_inicial DECIMAL(12,2)",
                            "descricao VARCHAR(160)", "ativa TINYINT"]),
        "meta": (60, 470, ["PK id INT", "FK usuario_id INT", "nome VARCHAR(80)", "descricao VARCHAR(120)",
                           "valor_objetivo DECIMAL(12,2)", "valor_atual DECIMAL(12,2)", "data_inicio DATE", "data_limite DATE",
                           "icone VARCHAR(20)"]),
        "meta_deposito": (60, 800, ["PK id INT", "FK meta_id INT", "valor DECIMAL(12,2)", "data DATE"]),
        "transacao": (560, 400, ["PK id INT", "FK usuario_id INT", "FK conta_id INT", "FK conta_destino_id INT",
                                 "FK categoria_id INT", "FK assinatura_id INT", "tipo ENUM", "descricao VARCHAR(120)",
                                 "valor DECIMAL(12,2)", "data DATE", "forma_pagamento VARCHAR(20)", "status ENUM",
                                 "observacao VARCHAR(255)"]),
        "assinatura": (1060, 60, ["PK id INT", "FK usuario_id INT", "FK conta_id INT", "FK categoria_id INT",
                                  "descricao VARCHAR(120)", "valor DECIMAL(12,2)", "dia_cobranca TINYINT", "aviso_dias TINYINT",
                                  "ativa TINYINT", "inicio DATE"]),
        "categoria": (1060, 560, ["PK id INT", "nome VARCHAR(60)", "tipo ENUM", "cor CHAR(7)"]),
    }
    largura, linha_altura = 330, 26
    caixas = {}
    for nome, (x, y, colunas) in tabelas.items():
        altura = 38 + linha_altura * len(colunas) + 10
        caixas[nome] = (x, y, largura, altura)

    def borda(nome, lado, fracao=0.5):
        x, y, w, h = caixas[nome]
        return {"esq": (x, y + h * fracao), "dir": (x + w, y + h * fracao), "topo": (x + w * fracao, y), "base": (x + w * fracao, y + h)}[lado]

    relacoes = [
        ("usuario", "dir", 0.35, "conta", "esq", 0.35),
        ("usuario", "base", 0.5, "meta", "topo", 0.5),
        ("meta", "base", 0.5, "meta_deposito", "topo", 0.5),
        ("conta", "base", 0.5, "transacao", "topo", 0.5),
        ("usuario", "dir", 0.8, "transacao", "esq", 0.2),
        ("conta", "dir", 0.3, "assinatura", "esq", 0.35),
        ("categoria", "esq", 0.5, "transacao", "dir", 0.7),
        ("assinatura", "base", 0.5, "categoria", "topo", 0.5),
        ("assinatura", "esq", 0.8, "transacao", "dir", 0.25),
    ]
    for a, la, fa, b, lb, fb in relacoes:
        x1, y1 = borda(a, la, fa)
        x2, y2 = borda(b, lb, fb)
        meio_x = (x1 + x2) / 2
        if la in ("dir", "esq"):
            d = f"M{x1},{y1} H{meio_x} V{y2} H{x2}"
        else:
            meio_y = (y1 + y2) / 2
            d = f"M{x1},{y1} V{meio_y} H{x2} V{y2}"
        s.caminho(d, seta=False)
        s.texto(x1 + (12 if la == "dir" else -12 if la == "esq" else 12), y1 + (-10 if la in ("dir", "esq") else 14), "1", 13, 700, VERDE)
        s.texto(x2 + (-14 if lb == "esq" else 14 if lb == "dir" else 14), y2 + (-10 if lb in ("dir", "esq") else -10), "N", 13, 700, VERDE)

    for nome, (x, y, colunas) in tabelas.items():
        _, _, w, h = caixas[nome]
        s.retangulo(x, y, w, h, fundo="#FFFFFF")
        s.add(f'<path d="M{x},{y + 8} a8,8 0 0 1 8,-8 h{w - 16} a8,8 0 0 1 8,8 v30 h-{w} z" fill="{VERDE}"/>')
        s.texto(x + w / 2, y + 20, nome, 15, 700, "#FFFFFF")
        for i, coluna in enumerate(colunas):
            chave = coluna.split(" ")[0]
            cor = VERDE if chave in ("PK", "FK") else TEXTO
            peso = 700 if chave == "PK" else 400
            s.texto(x + 14, y + 52 + i * linha_altura, coluna, 13, peso, cor, "start")
    s.texto(750, 955, "Relações 1:N. conta_destino_id liga a transferência à conta que recebe o dinheiro.", 13, 400, CINZA)
    return s


def arquitetura() -> Svg:
    s = Svg(1600, 780)

    def bloco(x, y, w, h, titulo, itens, fundo="#FFFFFF", cor_titulo=VERDE):
        s.retangulo(x, y, w, h, fundo=fundo)
        s.texto(x + w / 2, y + 26, titulo, 15, 700, cor_titulo)
        for i, item in enumerate(itens):
            s.texto(x + w / 2, y + 58 + i * 24, item, 13, 400, TEXTO)

    bloco(30, 60, 220, 190, "Desenvolvimento", ["branch feature/*", "commits padronizados", "testes locais", "Pull Request"])
    bloco(300, 60, 250, 190, "GitHub (GitFlow)", ["feature/* → develop", "release/* → main", "tag vX.Y.Z", "revisão no PR"])
    bloco(600, 30, 420, 250, "CI: GitHub Actions", ["1. Validação e testes", "checkout, PHP 8.3, Node 22, Composer", "sintaxe, testes JS, PHPUnit + MySQL",
                                                   "2. Segurança: composer audit, Gitleaks", "3. Imagem Docker: build, teste com",
                                                   "MySQL, Trivy, publicação no GHCR"], fundo=VERDE_CLARO)
    bloco(1070, 60, 230, 190, "GHCR", ["ghcr.io/danmarangon/", "cofrelo-app", "tags: X.Y.Z, latest,", "develop, sha-…"])
    bloco(1350, 60, 220, 190, "Release (tag)", ["publicar a imagem", "anexar à release", "notas da versão", "homologação"])

    for x1, x2 in [(250, 300), (550, 600), (1020, 1070), (1300, 1350)]:
        s.linha(x1, 155, x2, 155, seta=True)

    s.retangulo(30, 360, 1540, 380, fundo="#FBFDFA", borda=VERDE, raio=14, largura=2)
    s.texto(800, 390, "Ambiente de execução (Docker Compose)", 17, 700, VERDE)
    bloco(70, 420, 330, 260, "scripts/implantar.ps1", ["1. obtém a imagem da versão", "(GHCR ou release do GitHub)", "2. recreate do container",
                                                         "3. espera o /health (60 s)", "4. falhou? volta para a", "versão anterior (rollback)"])
    bloco(470, 420, 300, 260, "app (PHP 8.3 + Apache)", ["porta 8080, usuário www-data", "/health  ·  /metrics", "logs em JSON",
                                                           "HEALTHCHECK do Docker"], fundo=VERDE_CLARO)
    bloco(840, 420, 260, 260, "banco (MySQL 8.4)", ["volume dados-mysql", "senhas no .env", "healthcheck mysqladmin"])
    bloco(1170, 420, 360, 260, "Observabilidade", ["Prometheus (porta 9090)", "coleta /metrics a cada 15 s", "4 regras de alerta",
                                                    "Grafana (porta 3000)", "painel provisionado"])
    s.linha(400, 550, 470, 550, seta=True)
    s.linha(770, 550, 840, 550, seta=True)
    s.caminho("M1350,680 V712 H620 V684", tracejada=True)
    s.texto(985, 700, "o Prometheus coleta o /metrics da aplicação", 12, 400, CINZA)
    s.caminho("M1460,250 V330 H235 V420")
    s.texto(850, 318, "a versão publicada é implantada pelo script", 13, 400, CINZA)
    return s


def gerar() -> list[Path]:
    SAIDA.mkdir(parents=True, exist_ok=True)
    diagramas = {"caso-de-uso": caso_de_uso(), "fluxo-do-sistema": fluxo(), "modelo-do-banco": der(), "arquitetura-cicd": arquitetura()}
    arquivos = []
    with Navegador(1600, 1600, escala=2) as nav:
        for nome, desenho in diagramas.items():
            svg = SAIDA / f"{nome}.svg"
            svg.write_text(desenho.montar(), encoding="utf-8")
            pagina = SAIDA / f"_{nome}.html"
            pagina.write_text(f'<!doctype html><html><body style="margin:0;background:#fff">{desenho.montar()}</body></html>', encoding="utf-8")
            nav.definir_tamanho(desenho.largura, desenho.altura)
            nav.largura, nav.altura = desenho.largura, desenho.altura
            nav.abrir(pagina.as_uri(), pausa=0.2)
            arquivos.append(nav.capturar(SAIDA / f"{nome}.png", pagina_inteira=False))
            pagina.unlink()
    return arquivos


if __name__ == "__main__":
    for arquivo in gerar():
        print(arquivo)
