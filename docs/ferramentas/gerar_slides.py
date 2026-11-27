"""Gera os slides das duas apresentações (Desenvolvimento Web e DevOps) em PowerPoint e PDF.

Cada slide tem notas do apresentador com o que falar, para cada integrante saber explicar a sua parte.

Uso: python docs/ferramentas/gerar_slides.py [dw|devops]   (sem argumento, gera os dois)
Saída: docs/entregas/Apresentação DW - Cofrelo.pptx e .pdf, Apresentação DevOps - Cofrelo.pptx e .pdf
Requisitos: python-pptx (pip install python-pptx) e o PowerPoint instalado para exportar o PDF.
"""

from __future__ import annotations

import json
import re
import struct
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from pptx import Presentation  # noqa: E402
from pptx.chart.data import CategoryChartData  # noqa: E402
from pptx.dml.color import RGBColor  # noqa: E402
from pptx.enum.chart import XL_CHART_TYPE, XL_LABEL_POSITION  # noqa: E402
from pptx.enum.shapes import MSO_SHAPE  # noqa: E402
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN  # noqa: E402
from pptx.util import Emu, Inches, Pt  # noqa: E402

import conteudo as c  # noqa: E402

VERDE, VERDE_ESCURO, LIMA = "1D704F", "123F2E", "DBEEA3"
TEXTO, TEXTO2, FUNDO, CARTAO, LINHA, BRANCO = "182D26", "5B655F", "FFFFFF", "F2F6F0", "DDE5DA", "FFFFFF"
FONTE = "Calibri"
LARGURA, ALTURA = 13.333, 7.5
TELAS, DIAGRAMAS, WIREFRAMES = c.EVIDENCIAS / "telas", c.EVIDENCIAS / "diagramas", c.EVIDENCIAS / "wireframes"
OBS, IMPL = c.EVIDENCIAS / "observabilidade", c.EVIDENCIAS / "implantacao"


def cor(hexa: str) -> RGBColor:
    return RGBColor.from_string(hexa)


class Deck:
    def __init__(self, rodape: str) -> None:
        self.prs = Presentation()
        self.prs.slide_width, self.prs.slide_height = Inches(LARGURA), Inches(ALTURA)
        self.rodape = rodape
        self.numero = 0

    # ------------------------------------------------------------------ peças
    def slide(self, escuro: bool = False):
        slide = self.prs.slides.add_slide(self.prs.slide_layouts[6])
        fundo = slide.background.fill
        fundo.solid()
        fundo.fore_color.rgb = cor(VERDE_ESCURO if escuro else FUNDO)
        self.numero += 1
        if not escuro:
            self.texto(slide, 0.6, 7.0, 8, 0.3, self.rodape, 10, TEXTO2)
            self.texto(slide, 11.73, 7.0, 1.0, 0.3, str(self.numero), 10, TEXTO2, alinhar="dir")
        return slide

    def texto(self, slide, x, y, w, h, conteudo, tamanho=16, cor_texto=TEXTO, negrito=False, alinhar="esq",
              ancora="topo", italico=False, espaco_depois=0, marcadores=False):
        caixa = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
        quadro = caixa.text_frame
        quadro.word_wrap = True
        quadro.margin_left = quadro.margin_right = quadro.margin_top = quadro.margin_bottom = 0
        quadro.vertical_anchor = {"topo": MSO_ANCHOR.TOP, "meio": MSO_ANCHOR.MIDDLE, "base": MSO_ANCHOR.BOTTOM}[ancora]
        paragrafos = conteudo if isinstance(conteudo, list) else [conteudo]
        for i, item in enumerate(paragrafos):
            p = quadro.paragraphs[0] if i == 0 else quadro.add_paragraph()
            p.alignment = {"esq": PP_ALIGN.LEFT, "centro": PP_ALIGN.CENTER, "dir": PP_ALIGN.RIGHT}[alinhar]
            p.space_after = Pt(espaco_depois)
            partes = item if isinstance(item, tuple) else (item,)
            if marcadores:
                partes = ("•  ",) + partes
            for parte in partes:
                negrito_parte = negrito
                marcado = re.fullmatch(r"[*][*](.+?)[*][*](\s*)", parte, re.S)
                if marcado:  # **texto** em negrito, mantendo o espaço que vem depois
                    parte, negrito_parte = marcado.group(1) + marcado.group(2), True
                run = p.add_run()
                run.text = parte
                fonte = run.font
                fonte.name, fonte.size, fonte.bold, fonte.italic = FONTE, Pt(tamanho), negrito_parte, italico
                fonte.color.rgb = cor(cor_texto)
        return caixa

    def titulo(self, slide, secao: str, titulo: str) -> None:
        self.texto(slide, 0.6, 0.42, 12, 0.3, secao.upper(), 12, VERDE, negrito=True)
        self.texto(slide, 0.6, 0.72, 12.1, 0.8, titulo, 32, TEXTO, negrito=True)

    def cartao(self, slide, x, y, w, h, preenchimento=BRANCO, borda=LINHA):
        forma = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(x), Inches(y), Inches(w), Inches(h))
        forma.adjustments[0] = 0.08
        forma.fill.solid()
        forma.fill.fore_color.rgb = cor(preenchimento)
        if borda:
            forma.line.color.rgb = cor(borda)
            forma.line.width = Pt(1)
        else:
            forma.line.fill.background()
        forma.shadow.inherit = False
        forma.text_frame.text = ""
        return forma

    def selo(self, slide, x, y, d, texto, preenchimento=VERDE, cor_texto=BRANCO, tamanho=16):
        forma = slide.shapes.add_shape(MSO_SHAPE.OVAL, Inches(x), Inches(y), Inches(d), Inches(d))
        forma.fill.solid()
        forma.fill.fore_color.rgb = cor(preenchimento)
        forma.line.fill.background()
        forma.shadow.inherit = False
        quadro = forma.text_frame
        quadro.margin_left = quadro.margin_right = quadro.margin_top = quadro.margin_bottom = 0
        quadro.vertical_anchor = MSO_ANCHOR.MIDDLE
        p = quadro.paragraphs[0]
        p.alignment = PP_ALIGN.CENTER
        run = p.add_run()
        run.text = texto
        run.font.name, run.font.size, run.font.bold = FONTE, Pt(tamanho), True
        run.font.color.rgb = cor(cor_texto)
        return forma

    def imagem(self, slide, caminho: Path, x, y, w, h, borda=True, alinhar="centro"):
        """Coloca a imagem inteira dentro da caixa (x, y, w, h), sem distorcer."""
        caminho = Path(caminho)
        if not caminho.exists():
            self.cartao(slide, x, y, w, h, CARTAO, None)
            self.texto(slide, x + 0.2, y + h / 2 - 0.2, w - 0.4, 0.4, f"[imagem não gerada: {caminho.name}]", 12, TEXTO2, alinhar="centro")
            return None
        with open(caminho, "rb") as arquivo:
            arquivo.read(16)
            largura_px, altura_px = struct.unpack(">II", arquivo.read(8))
        escala = min(w / largura_px, h / altura_px)
        lw, lh = largura_px * escala, altura_px * escala
        lx = x + (w - lw) / 2 if alinhar == "centro" else x
        ly = y + (h - lh) / 2
        figura = slide.shapes.add_picture(str(caminho), Inches(lx), Inches(ly), Inches(lw), Inches(lh))
        if borda:
            figura.line.color.rgb = cor(LINHA)
            figura.line.width = Pt(1)
        return figura

    def numero_grande(self, slide, x, y, w, valor, rotulo, cor_valor=VERDE, cor_rotulo=TEXTO2):
        self.texto(slide, x, y, w, 0.95, valor, 54, cor_valor, negrito=True)
        self.texto(slide, x, y + 0.95, w, 0.6, rotulo, 14, cor_rotulo)

    def tabela(self, slide, x, y, w, linhas: list[list[str]], larguras: list[float], tamanho=12, altura_linha=0.36):
        tabela = slide.shapes.add_table(len(linhas), len(linhas[0]), Inches(x), Inches(y), Inches(w),
                                        Inches(altura_linha * len(linhas))).table
        for j, largura in enumerate(larguras):
            tabela.columns[j].width = Inches(largura)
        for i, linha in enumerate(linhas):
            for j, valor in enumerate(linha):
                celula = tabela.cell(i, j)
                celula.fill.solid()
                celula.fill.fore_color.rgb = cor(LIMA if i == 0 else (BRANCO if i % 2 else CARTAO))
                celula.margin_left = celula.margin_right = Inches(0.08)
                celula.margin_top = celula.margin_bottom = Inches(0.03)
                quadro = celula.text_frame
                quadro.word_wrap = True
                p = quadro.paragraphs[0]
                p.text = ""
                run = p.add_run()
                run.text = valor
                run.font.name, run.font.size, run.font.bold = FONTE, Pt(tamanho), i == 0
                run.font.color.rgb = cor(TEXTO)
        return tabela

    def notas(self, slide, texto: str) -> None:
        slide.notes_slide.notes_text_frame.text = texto

    def salvar(self, caminho: Path, autor: str = "", titulo: str = "") -> Path:
        propriedades = self.prs.core_properties  # o modelo do python-pptx traz o nome do autor da biblioteca
        propriedades.author = propriedades.last_modified_by = autor
        propriedades.title, propriedades.subject, propriedades.comments, propriedades.keywords = titulo, "", "", ""
        caminho.parent.mkdir(parents=True, exist_ok=True)
        self.prs.save(str(caminho))
        return caminho


def powerpoint_para_pdf(pptx: Path, pdf: Path) -> Path:
    pptx, pdf = pptx.resolve(), pdf.resolve()
    script = f"""
$ErrorActionPreference = 'Stop'
$pp = New-Object -ComObject PowerPoint.Application
try {{
    $apresentacao = $pp.Presentations.Open('{pptx}', -1, 0, 0)
    $apresentacao.SaveAs('{pdf}', 32)
    $apresentacao.Close()
}} finally {{ $pp.Quit() }}
"""
    subprocess.run(["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", script], check=True,
                   capture_output=True, text=True, encoding="utf-8", errors="replace")
    return pdf


# ====================================================================== apresentação de DW
def deck_dw() -> list[Path]:
    p = c.projeto()
    dw = p["dw"]
    nomes = [i["nome"] for i in c.integrantes_dw()]
    d = Deck(f"{p['projeto']} · {dw['grupo']} · {dw['disciplina']}")

    # 1. abertura
    s = d.slide(escuro=True)
    d.texto(s, 0.8, 0.8, 8, 0.4, f"{dw['disciplina'].upper()} · PROJETO FINAL", 14, LIMA, negrito=True)
    d.texto(s, 0.8, 1.5, 8.5, 1.4, p["projeto"], 80, BRANCO, negrito=True)
    d.texto(s, 0.8, 2.95, 8.5, 0.6, "Finanças em equilíbrio: controle financeiro pessoal na web", 24, LIMA)
    d.texto(s, 0.8, 4.4, 6.5, 2.0, [f"{dw['grupo']}"] + nomes, 16, BRANCO, espaco_depois=2)
    d.texto(s, 0.8, 6.6, 8, 0.4, f"Prof. {dw['professor']} · {p['instituicao']} · {dw['apresentacao'].split(',')[0]}", 12, LIMA)
    d.imagem(s, TELAS / "15-celular-visao-geral.png", 9.2, 0.6, 3.4, 6.3, borda=False)
    d.notas(s, "Abertura (1 min). Apresente o grupo e diga em uma frase o que é o Cofrelo: um sistema web para a pessoa "
               "saber para onde vai o dinheiro, controlar assinaturas e guardar para metas. Cada integrante vai explicar uma parte.")

    # 2. cliente e briefing
    s = d.slide()
    d.titulo(s, "Briefing", "Um cliente com um problema bem claro")
    cartoes = [
        ("1", "Quem é", "Fintech fictícia de Brasília, sem site, que atende jovens de 18 a 35 anos."),
        ("2", "O problema", "As pessoas não sabem para onde vai o dinheiro e esquecem as assinaturas."),
        ("3", "O que pediu", "Clareza do mês, controle das assinaturas e metas que viram um plano mensal."),
        ("4", "Restrições", "Visual clean em verde, sem vermelho como cor principal, sem anúncios, PHP e MySQL."),
    ]
    for i, (numero, rotulo, texto) in enumerate(cartoes):
        x = 0.6 + i * 3.1
        d.cartao(s, x, 1.9, 2.85, 3.7)
        d.selo(s, x + 0.3, 2.2, 0.6, numero)
        d.texto(s, x + 0.3, 3.0, 2.3, 0.5, rotulo, 20, TEXTO, negrito=True)
        d.texto(s, x + 0.3, 3.55, 2.3, 1.9, texto, 15, TEXTO2)
    d.texto(s, 0.6, 5.95, 12, 0.6, "Briefing completo com as 18 perguntas do modelo da disciplina: Apêndice A da documentação.", 14, TEXTO2, italico=True)
    d.notas(s, "Briefing (1,5 min). Explique que criamos uma empresa cliente fictícia e aplicamos as 18 perguntas do Briefing "
               "Modelo. Destaque o público, o problema e as objeções: por isso o visual é verde e sem anúncios.")

    # 3. requisitos em números
    s = d.slide()
    d.titulo(s, "Requisitos", "O que o sistema precisa fazer")
    for i, (valor, rotulo) in enumerate([(str(len(c.REQUISITOS_FUNCIONAIS)), "requisitos funcionais"),
                                         (str(len(c.REQUISITOS_NAO_FUNCIONAIS)), "requisitos não funcionais"),
                                         (str(len(c.REGRAS_DE_NEGOCIO)), "regras de negócio")]):
        d.numero_grande(s, 0.6 + i * 2.2, 1.9, 2.1, valor, rotulo)
    exemplos = [("RF04", "Registrar despesa, receita ou transferência"), ("RF13", "Simular quanto guardar por mês"),
                ("RNF04", "Acessibilidade WCAG 2.2 AA"), ("RNF07", "Roda em qualquer PC com Docker"),
                ("RN04", "Conta com histórico não pode ser excluída"), ("RN11", "Assinatura lança a cobrança sozinha todo mês")]
    d.cartao(s, 7.3, 1.9, 5.4, 4.6)
    d.texto(s, 7.6, 2.1, 5.0, 0.4, "Alguns exemplos", 16, VERDE, negrito=True)
    for i, (codigo, texto) in enumerate(exemplos):
        d.selo(s, 7.6, 2.65 + i * 0.62, 0.48, codigo, LIMA, VERDE_ESCURO, 9)
        d.texto(s, 8.25, 2.72 + i * 0.62, 4.3, 0.4, texto, 15, TEXTO)
    d.texto(s, 0.6, 4.6, 6.4, 1.6, ["Os quadros seguem o modelo da disciplina: autor, revisão e data, e cada item com nome, código e descrição.",
                                    "Os requisitos de infraestrutura do DevOps entram nos não funcionais."], 15, TEXTO2, espaco_depois=8)
    d.notas(s, "Requisitos (1,5 min). Funcionais é o que o usuário faz; não funcionais, as qualidades e o ambiente; regras de "
               "negócio, o que o cliente exige. Leia dois ou três exemplos e mostre os quadros na documentação.")

    # 4. modelagem
    s = d.slide()
    d.titulo(s, "Modelagem", "Caso de uso e fluxo do sistema")
    d.imagem(s, DIAGRAMAS / "caso-de-uso.png", 0.6, 1.75, 6.6, 4.9)
    d.imagem(s, DIAGRAMAS / "fluxo-do-sistema.png", 7.5, 1.75, 5.2, 4.9)
    d.notas(s, "Modelagem (1,5 min). No caso de uso, cada elipse é uma funcionalidade; o visitante só se cadastra e entra. "
               "No fluxo, mostre o caminho: login, visão geral, escolher uma função, validar, gravar e continuar ou sair.")

    # 5. wireframe → protótipo → implementação
    s = d.slide()
    d.titulo(s, "Do rascunho ao sistema", "Wireframe, protótipo no Figma e página implementada")
    for i, (caminho, rotulo) in enumerate([(WIREFRAMES / "03-visao-geral.png", "1. Wireframe"),
                                           (c.PROTOTIPO / "01-visao-geral.png", "2. Protótipo (Figma)"),
                                           (TELAS / "03-visao-geral.png", "3. Implementado")]):
        x = 0.6 + i * 4.1
        d.selo(s, x, 1.85, 0.45, str(i + 1), tamanho=14)
        d.texto(s, x + 0.6, 1.9, 3.3, 0.4, rotulo.split(". ", 1)[1], 17, TEXTO, negrito=True)
        d.imagem(s, caminho, x, 2.5, 3.9, 3.9)
    d.notas(s, "Wireframe e protótipo (1,5 min). O wireframe definiu onde fica cada coisa, o protótipo definiu cores, fontes "
               "e ícones, e a página implementada segue o protótipo. Todas as dez telas estão na documentação.")

    # 6. comentários do protótipo
    s = d.slide()
    d.titulo(s, "Protótipo", "Os comentários do Figma viraram funcionalidades")
    for i, (caminho, rotulo, texto) in enumerate([
            (TELAS / "05-calendario-de-periodo.png", "Calendário de período", "Escolher o intervalo das transações"),
            (TELAS / "11-relatorios.png", "Calendário do GitHub", "Mapa de gastos de cada dia do ano"),
            (TELAS / "07-nova-assinatura.png", "Assinatura no modal", "Substituiu a tela de assinaturas")]):
        x = 0.6 + i * 4.1
        d.imagem(s, caminho, x, 1.8, 3.9, 3.6)
        d.texto(s, x, 5.55, 3.9, 0.4, rotulo, 17, TEXTO, negrito=True)
        d.texto(s, x, 5.95, 3.9, 0.5, texto, 14, TEXTO2)
    d.notas(s, "Comentários do protótipo (1 min). Mostre que ouvimos os três comentários: calendário em Transações, mapa no "
               "estilo do GitHub em Relatórios e a aba Assinatura no modal de nova transação.")

    # 7. JavaScript
    s = d.slide()
    d.titulo(s, "JavaScript", "Os oito usos pedidos no enunciado")
    curtos = {"a": "if/else, switch e laços decidem o que aparece", "b": "atalhos N, / e G+V; Esc e Tab no modal; busca ao digitar",
              "c": "map, filter, reduce e Intl para formatar", "d": "objetos guardam o estado dos filtros e do formulário",
              "e": "tabela de transações montada a partir da API", "f": "funções de cálculo testadas com node --test",
              "g": "gráficos com destaque ao passar o mouse; força da senha", "h": "notificações a cada minuto; categoria sugerida"}
    for i, (letra, titulo, _onde, _como) in enumerate(c.JAVASCRIPT):
        coluna, linha = i % 2, i // 2
        x, y = 0.6 + coluna * 6.2, 1.85 + linha * 1.2
        d.cartao(s, x, y, 5.95, 1.05)
        d.selo(s, x + 0.22, y + 0.24, 0.58, letra, tamanho=18)
        d.texto(s, x + 1.0, y + 0.14, 4.8, 0.4, titulo, 16, TEXTO, negrito=True)
        d.texto(s, x + 1.0, y + 0.52, 4.8, 0.45, curtos[letra], 13, TEXTO2)
    d.notas(s, "JavaScript (2 min). No código, cada uso está marcado com o comentário [DW 10.a] a [DW 10.h]. Abra um ou dois "
               "arquivos na apresentação: por exemplo componentes/modal-transacao.js (condicionais e automatização) e "
               "paginas/transacoes.js (tabela e evento de busca).")

    # 8. formulário calculado
    s = d.slide()
    d.titulo(s, "Formulário com campos calculados", "O simulador de metas calcula enquanto a pessoa digita")
    d.imagem(s, TELAS / "10-metas-e-simulador.png", 0.6, 1.75, 6.8, 4.9)
    d.cartao(s, 7.7, 1.75, 5.0, 4.9)
    d.texto(s, 8.0, 2.0, 4.4, 0.4, "Como funciona", 18, VERDE, negrito=True)
    d.texto(s, 8.0, 2.55, 4.4, 2.3, [("**Entra:** ", "objetivo, quanto já tem, prazo, rendimento e aporte."),
                                     ("**Sai:** ", "aporte necessário, saldo final, rendimento, total guardado e em quantos meses chega."),
                                     ("**Fórmula:** ", "valor futuro de uma série de depósitos.")], 15, TEXTO, espaco_depois=10)
    d.texto(s, 8.0, 5.05, 4.4, 1.4, "Na 1.1.0, cada meta também mostra a previsão de conclusão no ritmo dos últimos 3 meses.", 14, TEXTO2, italico=True)
    d.notas(s, "Formulário calculado (1,5 min). Digite valores ao vivo no simulador da página Metas e mostre os campos "
               "mudando a cada tecla. O cálculo fica em lib/calculos.js e é testado automaticamente.")

    # 9. PHP + MySQL
    s = d.slide()
    d.titulo(s, "PHP e banco de dados", "CRUD completo no MySQL, também como API REST")
    d.imagem(s, DIAGRAMAS / "modelo-do-banco.png", 0.6, 1.75, 6.4, 4.4)
    linhas = [["Entidade", "Cadastrar", "Consultar", "Alterar", "Excluir"]] + [[e, "✓", "✓", "✓", "✓"] for e in
                                                                               ["Usuário", "Transação", "Conta", "Meta", "Assinatura"]]
    d.tabela(s, 7.3, 1.85, 5.4, linhas, [1.6, 1.0, 1.0, 0.9, 0.9], tamanho=13, altura_linha=0.42)
    d.texto(s, 7.3, 4.6, 5.4, 1.6, ["Consultas preparadas (PDO) contra SQL injection, senhas com bcrypt, token CSRF e cada usuário só vê os próprios dados.",
                                    "Rotas /api/... respondem JSON para as páginas e para o curl."], 14, TEXTO2, espaco_depois=8)
    d.notas(s, "PHP e banco (2 min). Mostre o modelo: usuário, contas, transações, assinaturas e metas. Explique o caminho "
               "de uma transação: controlador valida, serviço aplica as regras, repositório grava com consulta preparada.")

    # 10. qualidade
    s = d.slide()
    d.titulo(s, "Qualidade", "Testado, acessível e fácil de rodar")
    for i, (valor, rotulo) in enumerate([("81", "testes automatizados a cada alteração"), ("0", "violações de acessibilidade (WCAG 2.2 AA)"),
                                         ("1", "comando para rodar: docker compose up -d"), ("9", "telas responsivas, do celular ao computador")]):
        x = 0.6 + i * 3.1
        d.cartao(s, x, 1.9, 2.85, 2.7)
        d.numero_grande(s, x + 0.3, 2.15, 2.4, valor, rotulo)
    d.imagem(s, TELAS / "14-notificacoes.png", 0.6, 4.85, 6.0, 1.9, alinhar="esq")
    d.texto(s, 6.9, 4.95, 5.8, 1.7, ["PHPUnit e node --test rodando no GitHub Actions.", "Auditoria do axe-core em todas as páginas, no computador e no celular.",
                                     "Atalhos de teclado e navegação completa sem mouse."], 14, TEXTO2, espaco_depois=6, marcadores=True)
    d.notas(s, "Qualidade (1 min). Cite os testes, a acessibilidade e o Docker: o sistema roda igual em qualquer computador, "
               "inclusive no dia da apresentação.")

    # 11. demonstração
    s = d.slide()
    d.titulo(s, "Demonstração", "Roteiro do sistema funcionando")
    passos = ["Entrar com a usuária de demonstração", "Visão geral do mês e notificações", "Nova transação, assinatura e categoria sugerida",
              "Filtros, calendário e exportação em CSV", "Metas: guardar dinheiro e usar o simulador", "Relatórios e mapa de gastos"]
    for i, passo in enumerate(passos):
        coluna, linha = i // 3, i % 3
        x, y = 0.6 + coluna * 6.2, 1.9 + linha * 1.25
        d.selo(s, x, y, 0.7, str(i + 1), tamanho=20)
        d.texto(s, x + 0.95, y + 0.17, 5.0, 0.5, passo, 18, TEXTO)
    d.cartao(s, 0.6, 5.75, 12.1, 0.85, CARTAO, None)
    d.texto(s, 0.9, 5.95, 11.5, 0.5, ("**Acesso:** ", "http://localhost:8080 · mariana@cofrelo.app · senha cofrelo123"), 17, TEXTO)
    d.notas(s, "Demonstração (4 min). Siga o roteiro com o sistema aberto. Antes da aula: docker compose up -d e conferir "
               "http://localhost:8080. Cada integrante pode conduzir uma parte da demonstração.")

    # 12. encerramento
    s = d.slide(escuro=True)
    d.texto(s, 0.8, 2.2, 11.5, 1.2, "Obrigado!", 66, BRANCO, negrito=True)
    d.texto(s, 0.8, 3.5, 11.5, 0.6, "Perguntas?", 28, LIMA)
    d.texto(s, 0.8, 5.2, 11.5, 0.5, p["repositorio"], 18, BRANCO)
    d.texto(s, 0.8, 5.7, 11.5, 0.5, f"{dw['grupo']}: " + ", ".join(nomes), 14, LIMA)
    d.notas(s, "Encerramento. Agradeça, abra para perguntas e deixe o link do repositório na tela.")

    pptx = d.salvar(c.ENTREGAS / f"Apresentação DW - {p['projeto']}.pptx", f"{dw['grupo']} – {dw['disciplina']}", f"{p['projeto']} – apresentação de {dw['disciplina']}")
    return [pptx, powerpoint_para_pdf(pptx, pptx.with_suffix(".pdf"))]


# ====================================================================== apresentação de DevOps
def deck_devops() -> list[Path]:
    p = c.projeto()
    dv = p["devops"]
    dupla = c.dupla_devops()
    dados_arquivo = c.EVIDENCIAS / "pipeline" / "dados.json"
    dados = json.loads(dados_arquivo.read_text(encoding="utf-8")) if dados_arquivo.exists() else {}
    prs = dados.get("prs", [])
    resumo = dados.get("resumo_ci", {})
    d = Deck(f"{p['projeto']} · {dv['disciplina']}")

    # 1. abertura
    s = d.slide(escuro=True)
    d.texto(s, 0.8, 0.8, 9, 0.4, f"{dv['disciplina'].upper()} · PROJETO FINAL", 14, LIMA, negrito=True)
    d.texto(s, 0.8, 1.5, 11, 1.4, p["projeto"], 80, BRANCO, negrito=True)
    d.texto(s, 0.8, 2.95, 11, 0.6, "Da branch à produção: CI, artefato, implantação com rollback e observabilidade", 24, LIMA)
    d.texto(s, 0.8, 4.5, 11, 1.2, ["Dupla"] + [pessoa["nome"] for pessoa in dupla], 16, BRANCO, espaco_depois=2)
    professor = f"Prof. {dv['professor']} · " if dv["professor"] else ""
    d.texto(s, 0.8, 6.6, 11, 0.4, f"{professor}{p['instituicao']}", 12, LIMA)
    d.notas(s, "Abertura. A dupla se apresenta. O Cofrelo é a aplicação; o foco aqui é o caminho do código até a "
               "aplicação rodando: Git, pipeline, testes, artefato, implantação, segurança e observabilidade.")

    # 2. aplicação
    s = d.slide()
    d.titulo(s, "1 · Aplicação", "Um sistema de finanças pessoais com API REST")
    d.texto(s, 0.6, 1.8, 5.6, 2.2, [c.PROBLEMA, "O Cofrelo reúne contas, transações, assinaturas, metas e relatórios."], 15, TEXTO2, espaco_depois=10)
    d.tabela(s, 0.6, 4.05, 5.6, [["Camada", "Tecnologia"], ["Back-end", "PHP 8.3 + API REST (JSON)"], ["Banco", "MySQL 8.4"],
                                 ["Front-end", "HTML, CSS e JavaScript"], ["Execução", "Docker e Docker Compose"]],
             [1.6, 4.0], tamanho=13, altura_linha=0.42)
    d.cartao(s, 6.6, 1.8, 6.1, 4.9, "16261F", None)
    d.texto(s, 6.9, 2.0, 5.6, 0.4, "Testando a API", 15, LIMA, negrito=True)
    d.texto(s, 6.9, 2.5, 5.6, 4.0, ["curl -c cookies.txt \\", "  -H 'Content-Type: application/json' \\",
                                    "  -d '{\"email\":\"mariana@cofrelo.app\",", "       \"senha\":\"cofrelo123\"}' \\",
                                    "  localhost:8080/api/auth/entrar", "", "curl -b cookies.txt localhost:8080/api/resumo",
                                    "curl localhost:8080/health"], 13, "E8F2E6")
    for forma in s.shapes:
        pass
    d.notas(s, "Aplicação (1,5 min). Problema, tecnologias e a API: o mesmo PHP responde JSON nas rotas /api/... com sessão, "
               "CSRF e códigos HTTP certos. Se der, rode o curl ao vivo.")

    # 3. git
    s = d.slide()
    d.titulo(s, "2 · Git e colaboração", "GitFlow com Pull Request para tudo")
    faixas = [("main", "versões entregues, com tag vX.Y.Z", VERDE_ESCURO), ("release/*", "preparação da versão (changelog)", VERDE),
              ("develop", "integração do que está pronto", "2F8A63"), ("feature/*", "uma funcionalidade por branch", "6FA588")]
    for i, (nome, texto, tom) in enumerate(faixas):
        y = 1.9 + i * 0.95
        forma = d.cartao(s, 0.6, y, 2.2, 0.7, tom, None)
        d.texto(s, 0.6, y + 0.17, 2.2, 0.4, nome, 16, BRANCO, negrito=True, alinhar="centro")
        d.texto(s, 3.05, y + 0.2, 4.0, 0.4, texto, 15, TEXTO)
    mesclados = sum(pr["state"] == "MERGED" for pr in prs)
    d.numero_grande(s, 7.6, 1.85, 2.4, str(len(prs) or "—"), "Pull Requests")
    d.numero_grande(s, 10.2, 1.85, 2.5, str(mesclados or "—"), "mesclados com CI verde")
    d.texto(s, 7.6, 3.75, 5.1, 2.4, ["Commits no padrão tipo: descrição (feat, fix, ci, docs).", "Tags v1.0.0 e v1.1.0 na main.",
                                     "Cada integrante abre e revisa Pull Requests."], 15, TEXTO2, espaco_depois=8, marcadores=True)
    d.notas(s, "Git (1,5 min). Explique o GitFlow e por que ele serve à disciplina: versões rastreáveis. Mostre a lista de "
               "Pull Requests no GitHub. Cada um da dupla fala da sua participação (branches, commits e revisões).")

    # 4. pipeline
    s = d.slide()
    d.titulo(s, "3 · Pipeline no GitHub Actions", "Tudo automático a cada push e Pull Request")
    d.imagem(s, DIAGRAMAS / "arquitetura-cicd.png", 0.6, 1.75, 12.1, 3.5)
    etapas = [("Validação e testes", "php -l, PHPStan, PHPUnit com MySQL e node --test"),
              ("Segurança", "composer audit, Gitleaks, zizmor e hadolint"),
              ("Imagem Docker", "build, teste de fumaça, Trivy e publicação no GHCR")]
    for i, (nome, texto) in enumerate(etapas):
        x = 0.6 + i * 4.1
        d.selo(s, x, 5.5, 0.55, str(i + 1), tamanho=16)
        d.texto(s, x + 0.75, 5.5, 3.2, 0.4, nome, 16, TEXTO, negrito=True)
        d.texto(s, x + 0.75, 5.9, 3.2, 0.7, texto, 13, TEXTO2)
    d.notas(s, "Pipeline (2 min). Três jobs; o da imagem só roda se validação e segurança passarem. Na tag, o workflow "
               "Release publica a versão e faz a homologação. Mostre uma execução aberta no Actions.")

    # 5. testes
    s = d.slide()
    d.titulo(s, "4 · Testes", "81 testes rodando no pipeline")
    dados_grafico = CategoryChartData()
    dados_grafico.categories = ["Unitários (PHPUnit)", "Integração (PHPUnit + MySQL)", "JavaScript (node --test)"]
    dados_grafico.add_series("Casos", (39, 26, 16))
    grafico = s.shapes.add_chart(XL_CHART_TYPE.BAR_CLUSTERED, Inches(0.6), Inches(1.8), Inches(6.6), Inches(4.6), dados_grafico).chart
    grafico.has_legend = False
    grafico.has_title = False
    grafico.category_axis.reverse_order = True  # de cima para baixo, na ordem da lista
    serie = grafico.plots[0]
    serie.gap_width = 60
    serie.has_data_labels = True
    serie.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
    serie.data_labels.font.size, serie.data_labels.font.bold = Pt(16), True
    serie.data_labels.font.color.rgb = cor(TEXTO)
    serie.series[0].format.fill.solid()
    serie.series[0].format.fill.fore_color.rgb = cor(VERDE)
    grafico.value_axis.visible = False
    grafico.value_axis.has_major_gridlines = False
    grafico.category_axis.tick_labels.font.size = Pt(13)
    grafico.category_axis.tick_labels.font.color.rgb = cor(TEXTO)
    grafico.category_axis.format.line.color.rgb = cor(LINHA)
    d.cartao(s, 7.6, 1.8, 5.1, 4.6)
    d.texto(s, 7.9, 2.05, 4.6, 0.4, "O que é testado", 17, VERDE, negrito=True)
    d.texto(s, 7.9, 2.6, 4.6, 3.6, ["Regras de negócio: transações, metas, indicadores.", "API: login, CSRF, CRUD, 401, 404, 409, 422, 429.",
                                    "Segurança: bloqueio de login e CSV.", "Um teste falhando deixa o pipeline vermelho e barra a imagem."],
             14, TEXTO2, espaco_depois=8, marcadores=True)
    d.notas(s, "Testes (1,5 min). Mostre o check 'Resultado dos testes' num Pull Request: cada caso aparece com o nome. "
               "Explique que o resultado também fica como artefato JUnit da execução.")

    # 6. sucesso e falha
    s = d.slide()
    d.titulo(s, "5 · Sucesso e falha", "Uma falha real, provocada de forma controlada")
    d.cartao(s, 0.6, 1.8, 3.7, 4.8)
    d.selo(s, 0.9, 2.05, 0.6, "✓", "2F8A63", BRANCO, 20)
    d.texto(s, 1.7, 2.15, 2.5, 0.4, "Sucesso", 20, TEXTO, negrito=True)
    d.texto(s, 0.9, 2.95, 3.2, 3.4, [f"{resumo.get('sucesso', '—')} execuções verdes no histórico.", "Na tag, publicar e homologar a versão também passam.",
                                     "Link de cada execução no PDF de evidências."], 14, TEXTO2, espaco_depois=8)
    d.cartao(s, 4.6, 1.8, 8.1, 4.8)
    d.selo(s, 4.9, 2.05, 0.6, "!", "B42318", BRANCO, 22)
    d.texto(s, 5.7, 2.15, 6.8, 0.4, "Falha controlada no PR #11", 20, TEXTO, negrito=True)
    d.texto(s, 4.9, 2.95, 7.5, 2.0, [("**Causa:** ", "a previsão das metas usava intdiv, que arredonda para baixo."),
                                     ("**Como vimos:** ", "2 testes falharam e o job da imagem foi pulado."),
                                     ("**Correção:** ", "arredondar para cima; o CI ficou verde e o PR entrou.")], 15, TEXTO, espaco_depois=8)
    d.cartao(s, 4.9, 4.95, 7.5, 1.4, "16261F", None)
    d.texto(s, 5.1, 5.1, 7.1, 1.2, ["Failed asserting that 5 is identical to 6.", "Failed asserting that 6 is identical to 7.",
                                    "FAILURES!  Tests: 65, Assertions: 187, Failures: 2."], 12, "FCA5A5")
    d.notas(s, "Sucesso e falha (2 min). Abra a execução vermelha do PR #11 e mostre o log; depois a execução verde do commit "
               "de correção. Reforce: a falha foi real (um teste que falha), e o pipeline impediu a publicação da imagem.")

    # 7. artefato
    s = d.slide()
    d.titulo(s, "6 · Artefato", "Uma imagem Docker versionada, construída pelo pipeline")
    d.cartao(s, 0.6, 1.8, 12.1, 1.05, "16261F", None)
    d.texto(s, 0.9, 2.08, 11.6, 0.5, f"docker pull {p['imagem_docker']}:1.1.0", 22, LIMA, negrito=True)
    blocos = [("Onde fica", "GitHub Container Registry e anexada à release do GitHub (docker save)"),
              ("Tags", "1.1.0, 1.1, latest, develop, main e sha-<commit>"),
              ("Rastreabilidade", "versão e commit na imagem (labels OCI) e no /health; digest e ID no imagem-X.Y.Z.txt")]
    for i, (rotulo, texto) in enumerate(blocos):
        x = 0.6 + i * 4.1
        d.cartao(s, x, 3.15, 3.85, 2.6)
        d.selo(s, x + 0.3, 3.4, 0.55, str(i + 1), tamanho=16)
        d.texto(s, x + 0.3, 4.1, 3.3, 0.4, rotulo, 18, TEXTO, negrito=True)
        d.texto(s, x + 0.3, 4.55, 3.3, 1.1, texto, 14, TEXTO2)
    d.texto(s, 0.6, 6.05, 12, 0.5, "Dockerfile multi-stage: a imagem de produção roda como www-data (sem root), com HEALTHCHECK.", 14, TEXTO2, italico=True)
    d.notas(s, "Artefato (1,5 min). Mostre a página da release v1.1.0 com os arquivos anexados e o pacote no GHCR. "
               "Explique que a mesma versão aparece na tag, na release, na imagem e no /health.")

    # 8. deploy
    s = d.slide()
    d.titulo(s, "7 · Deploy", "Recreate com verificação de saúde e rollback automático")
    fluxo = ["Construção", "Teste", "Entrega", "Implantação"]
    for i, etapa in enumerate(fluxo):
        x = 0.6 + i * 3.1
        d.cartao(s, x, 1.85, 2.75, 0.8, VERDE if i == 3 else CARTAO, None)
        d.texto(s, x, 2.08, 2.75, 0.4, etapa, 17, BRANCO if i == 3 else TEXTO, negrito=True, alinhar="centro")
    d.cartao(s, 0.6, 3.0, 6.3, 3.6, "16261F", None)
    d.texto(s, 0.85, 3.2, 5.9, 3.2, [".\\scripts\\implantar.ps1 -Versao 1.1.0 -SimularFalha", "  ... /health respondeu 503", "  ROLLBACK: voltando para a 1.0.0",
                                     "  ROLLBACK OK", "", ".\\scripts\\implantar.ps1 -Versao 1.1.0", "  Implantação concluída: versão 1.1.0",
                                     "", ".\\scripts\\implantar.ps1 -Voltar"], 13, "E8F2E6")
    d.texto(s, 7.2, 3.05, 5.5, 3.5, ["O script baixa a imagem (GHCR ou release) e confere o ID publicado.", "Troca o container e espera o /health por até 60 s.",
                                     "Se a versão nova não ficar saudável, volta sozinho para a anterior.", "O banco fica num volume e não é recriado."],
             15, TEXTO2, espaco_depois=10, marcadores=True)
    d.notas(s, "Deploy (2 min). Distinga construção, teste, entrega e implantação. Rode ao vivo, se possível: primeiro com "
               "-SimularFalha para mostrar o rollback automático, depois a atualização normal e o -Voltar.")

    # 9. segurança e observabilidade
    s = d.slide()
    d.titulo(s, "8 · Segurança e observabilidade", "Proteção no pipeline e falhas visíveis em segundos")
    seguranca = ["Segredos fora do Git (.env e GitHub Secrets)", "composer audit, Gitleaks, Trivy, zizmor, hadolint", "Actions fixadas por SHA e permissões mínimas",
                 "bcrypt, CSRF, CSP e bloqueio de login (429)"]
    d.texto(s, 0.6, 1.85, 5.2, 0.4, "Segurança", 18, VERDE, negrito=True)
    for i, item in enumerate(seguranca):
        d.selo(s, 0.6, 2.4 + i * 0.95, 0.5, str(i + 1), tamanho=14)
        d.texto(s, 1.3, 2.47 + i * 0.95, 4.6, 0.8, item, 15, TEXTO)
    imagem_alerta = OBS / "falha-grafana-painel.png"
    d.imagem(s, imagem_alerta if imagem_alerta.exists() else OBS / "normal-grafana-painel.png", 6.2, 1.85, 6.5, 4.1)
    d.texto(s, 6.2, 6.1, 6.5, 0.6, "/health, logs em JSON, /metrics, Prometheus com alertas e Grafana. Com o banco parado, o alerta BancoIndisponivel dispara.", 13, TEXTO2)
    d.notas(s, "Segurança e observabilidade (2 min). Explique como cada prática protege. Depois mostre o painel do Grafana "
               "e, se der, pare o banco (docker compose stop banco) para o /health responder 503 e o alerta disparar.")

    # 10. encerramento
    s = d.slide(escuro=True)
    d.texto(s, 0.8, 2.2, 11.5, 1.2, "Obrigado!", 66, BRANCO, negrito=True)
    d.texto(s, 0.8, 3.5, 11.5, 0.6, "Perguntas?", 28, LIMA)
    d.texto(s, 0.8, 5.0, 11.5, 0.5, p["repositorio"], 18, BRANCO)
    d.texto(s, 0.8, 5.5, 11.5, 0.5, f"Imagem: {p['imagem_docker']}", 14, LIMA)
    d.notas(s, "Encerramento. Deixe na tela o repositório e a imagem. Os dois da dupla respondem às perguntas.")

    pptx = d.salvar(c.ENTREGAS / f"Apresentação DevOps - {p['projeto']}.pptx", f"{p['projeto']} – {dv['disciplina']}", f"{p['projeto']} – apresentação de {dv['disciplina']}")
    return [pptx, powerpoint_para_pdf(pptx, pptx.with_suffix(".pdf"))]


if __name__ == "__main__":
    escolha = sys.argv[1] if len(sys.argv) > 1 else "todos"
    arquivos = (deck_dw() if escolha in ("dw", "todos") else []) + (deck_devops() if escolha in ("devops", "todos") else [])
    for arquivo in arquivos:
        print(arquivo)
