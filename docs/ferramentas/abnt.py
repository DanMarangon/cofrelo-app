"""Documento do Word no padrão ABNT (NBR 14724) com python-docx, e exportação para PDF pelo próprio Word.

Regras aplicadas: folha A4; margens de 3 cm (superior e esquerda) e 2 cm (inferior e direita); Arial 12 com
espaçamento 1,5, texto justificado e recuo de 1,25 cm na primeira linha; títulos das seções primárias em
maiúsculas e negrito, começando em página nova; legendas acima das figuras e quadros, com a fonte abaixo, em
tamanho 10; paginação contada a partir da folha de rosto e mostrada no canto superior direito a partir da
introdução; sumário e lista de ilustrações gerados pelo Word.
"""

from __future__ import annotations

import re
import struct
import subprocess
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

FONTE = "Arial"
LARGURA_UTIL_CM = 16.0  # 21 cm - 3 cm - 2 cm
ALTURA_MAX_FIGURA_CM = 18.5
PRETO = RGBColor(0, 0, 0)


def _tamanho_png(caminho: Path) -> tuple[int, int]:
    with open(caminho, "rb") as arquivo:
        arquivo.read(16)
        return struct.unpack(">II", arquivo.read(8))


def _campo(paragrafo, instrucao: str, texto: str = "1", tamanho: float | None = None, negrito: bool = False) -> None:
    """Insere um campo do Word (PAGE, SEQ, TOC...) que o Word calcula ao atualizar os campos."""
    def run_com(elemento):
        run = paragrafo.add_run()
        run.bold = negrito
        if tamanho:
            run.font.size = Pt(tamanho)
        run._r.append(elemento)
        return run

    inicio = OxmlElement("w:fldChar"); inicio.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText"); instr.set(qn("xml:space"), "preserve"); instr.text = f" {instrucao} "
    separa = OxmlElement("w:fldChar"); separa.set(qn("w:fldCharType"), "separate")
    fim = OxmlElement("w:fldChar"); fim.set(qn("w:fldCharType"), "end")
    run_com(inicio); run_com(instr); run_com(separa)
    run = paragrafo.add_run(texto); run.bold = negrito
    if tamanho:
        run.font.size = Pt(tamanho)
    run_com(fim)


def _fonte_fixa(estilo, fonte: str = FONTE) -> None:
    """Fixa a fonte do estilo e remove as fontes do tema, que teriam prioridade sobre ela."""
    estilo.font.name = fonte
    rfonts = estilo.element.get_or_add_rPr().get_or_add_rFonts()
    for atributo in ("w:asciiTheme", "w:hAnsiTheme", "w:eastAsiaTheme", "w:cstheme"):
        rfonts.attrib.pop(qn(atributo), None)
    for atributo in ("w:ascii", "w:hAnsi", "w:eastAsia", "w:cs"):
        rfonts.set(qn(atributo), fonte)


def _sombrear(celula, cor_hex: str) -> None:
    propriedades = celula._tc.get_or_add_tcPr()
    sombra = OxmlElement("w:shd")
    sombra.set(qn("w:val"), "clear"); sombra.set(qn("w:color"), "auto"); sombra.set(qn("w:fill"), cor_hex)
    propriedades.append(sombra)


def _repetir_cabecalho(linha) -> None:
    propriedades = linha._tr.get_or_add_trPr()
    repetir = OxmlElement("w:tblHeader"); repetir.set(qn("w:val"), "true")
    propriedades.append(repetir)


def _manter_com_proxima(linha) -> None:
    """Linha de cabeçalho não fica sozinha no pé da página: vai junto com a linha seguinte."""
    for celula in linha.cells:
        for paragrafo in celula.paragraphs:
            paragrafo.paragraph_format.keep_with_next = True


def _nao_quebrar_linha(linha) -> None:
    propriedades = linha._tr.get_or_add_trPr()
    elemento = OxmlElement("w:cantSplit"); elemento.set(qn("w:val"), "true")
    propriedades.append(elemento)


class DocumentoAbnt:
    def __init__(self) -> None:
        self.doc = Document()
        self._configurar_pagina(self.doc.sections[0])
        self._configurar_estilos()

    # ------------------------------------------------------------------ estrutura
    @staticmethod
    def _configurar_pagina(secao) -> None:
        secao.page_width, secao.page_height = Cm(21), Cm(29.7)
        secao.top_margin, secao.left_margin = Cm(3), Cm(3)
        secao.bottom_margin, secao.right_margin = Cm(2), Cm(2)
        secao.header_distance, secao.footer_distance = Cm(2), Cm(1.25)

    def _configurar_estilos(self) -> None:
        estilos = self.doc.styles
        normal = estilos["Normal"]
        normal.font.name, normal.font.size, normal.font.color.rgb = FONTE, Pt(12), PRETO
        normal.element.rPr.rFonts.set(qn("w:eastAsia"), FONTE)
        formato = normal.paragraph_format
        formato.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
        formato.space_before, formato.space_after = Pt(0), Pt(0)
        formato.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        formato.first_line_indent = Cm(1.25)
        for nivel, negrito, antes in [(1, True, 0), (2, False, 18), (3, True, 18)]:
            estilo = estilos[f"Heading {nivel}"]
            _fonte_fixa(estilo)
            estilo.font.size, estilo.font.bold = Pt(12), negrito
            estilo.font.italic, estilo.font.color.rgb = False, PRETO
            f = estilo.paragraph_format
            f.space_before, f.space_after = Pt(antes), Pt(18)
            f.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
            f.first_line_indent, f.left_indent = Cm(0), Cm(0)
            f.alignment = WD_ALIGN_PARAGRAPH.LEFT
            f.keep_with_next = True
            f.page_break_before = nivel == 1
        legenda = estilos["Caption"]
        _fonte_fixa(legenda)
        legenda.font.size, legenda.font.bold = Pt(10), False
        legenda.font.italic, legenda.font.color.rgb = False, PRETO
        f = legenda.paragraph_format
        f.alignment, f.first_line_indent = WD_ALIGN_PARAGRAPH.CENTER, Cm(0)
        f.space_before, f.space_after, f.keep_with_next = Pt(12), Pt(4), True
        f.line_spacing_rule = WD_LINE_SPACING.SINGLE

    def nova_secao(self, numerar: bool = False, reiniciar_em: int | None = None) -> None:
        """Começa uma seção em página nova. numerar=True mostra o número da página no canto superior direito."""
        secao = self.doc.add_section(WD_SECTION.NEW_PAGE)
        self._configurar_pagina(secao)
        secao.header.is_linked_to_previous = False
        cabecalho = secao.header.paragraphs[0]
        cabecalho.text = ""
        if numerar:
            cabecalho.alignment = WD_ALIGN_PARAGRAPH.RIGHT
            cabecalho.paragraph_format.first_line_indent = Cm(0)
            _campo(cabecalho, "PAGE", tamanho=10)
        for antigo in secao._sectPr.findall(qn("w:pgNumType")):
            secao._sectPr.remove(antigo)  # o python-docx copia a seção anterior, inclusive o reinício
        if reiniciar_em is not None:
            numeracao = OxmlElement("w:pgNumType"); numeracao.set(qn("w:start"), str(reiniciar_em))
            secao._sectPr.append(numeracao)

    def quebra_de_pagina(self) -> None:
        self.doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)

    # ------------------------------------------------------------------ elementos pré-textuais
    def _linha_centralizada(self, texto: str, negrito: bool = False, tamanho: int = 12, maiusculas: bool = False,
                            antes: float = 0, depois: float = 0):
        p = self.doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.space_before, p.paragraph_format.space_after = Pt(antes), Pt(depois)
        run = p.add_run(texto.upper() if maiusculas else texto)
        run.bold, run.font.size = negrito, Pt(tamanho)
        return p

    def capa(self, instituicao: str, curso: str, autores: list[str], titulo: str, subtitulo: str, cidade: str, ano: int) -> None:
        self._linha_centralizada(instituicao, negrito=True, maiusculas=True)
        self._linha_centralizada(curso, negrito=True, maiusculas=True)
        self._linha_centralizada("", antes=60)
        for autor in autores:
            self._linha_centralizada(autor, maiusculas=True)
        self._linha_centralizada("", antes=110)
        self._linha_centralizada(titulo, negrito=True, maiusculas=True, tamanho=14)
        if subtitulo:
            self._linha_centralizada(subtitulo, tamanho=12, antes=6)
        self._linha_centralizada("", antes=200)
        self._linha_centralizada(cidade, maiusculas=True)
        self._linha_centralizada(str(ano))

    def folha_de_rosto(self, autores: list[str], titulo: str, subtitulo: str, natureza: str, cidade: str, ano: int) -> None:
        for autor in autores:
            self._linha_centralizada(autor, maiusculas=True)
        self._linha_centralizada("", antes=110)
        self._linha_centralizada(titulo, negrito=True, maiusculas=True, tamanho=14)
        if subtitulo:
            self._linha_centralizada(subtitulo, antes=6)
        self._linha_centralizada("", antes=70)
        p = self.doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(8)
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        p.add_run(natureza).font.size = Pt(11)
        self._linha_centralizada("", antes=150)
        self._linha_centralizada(cidade, maiusculas=True)
        self._linha_centralizada(str(ano))

    def titulo_sem_numero(self, texto: str) -> None:
        """Títulos como SUMÁRIO, LISTA DE ILUSTRAÇÕES e REFERÊNCIAS: centralizados e fora do sumário."""
        self._linha_centralizada(texto.upper(), negrito=True, depois=18)

    def sumario(self) -> None:
        self.titulo_sem_numero("Sumário")
        p = self.doc.add_paragraph(); p.paragraph_format.first_line_indent = Cm(0)
        _campo(p, 'TOC \\o "1-3" \\h \\z \\u', texto="Atualize o sumário (o gerador faz isso pelo Word).")

    def lista_de(self, titulo: str, rotulo: str) -> None:
        self.titulo_sem_numero(titulo)
        p = self.doc.add_paragraph(); p.paragraph_format.first_line_indent = Cm(0)
        _campo(p, f'TOC \\h \\z \\c "{rotulo}"', texto=f"Lista de {rotulo.lower()}s.")

    # ------------------------------------------------------------------ texto
    def titulo(self, texto: str, nivel: int = 1) -> None:
        self.doc.add_heading(texto.upper() if nivel <= 2 else texto, level=nivel)

    def paragrafo(self, texto: str, recuo: bool = True, alinhar: str = "justificado", tamanho: float | None = None,
                  depois: float = 0, espacamento_simples: bool = False) -> None:
        """Texto com **negrito** e `código` simples."""
        p = self.doc.add_paragraph()
        p.paragraph_format.first_line_indent = Cm(1.25) if recuo else Cm(0)
        p.paragraph_format.space_after = Pt(depois)
        if espacamento_simples:
            p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        p.alignment = {"justificado": WD_ALIGN_PARAGRAPH.JUSTIFY, "esquerda": WD_ALIGN_PARAGRAPH.LEFT,
                       "centro": WD_ALIGN_PARAGRAPH.CENTER}[alinhar]
        for parte in re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", texto):
            if not parte:
                continue
            if parte.startswith("**"):
                run = p.add_run(parte[2:-2]); run.bold = True
            elif parte.startswith("`"):
                run = p.add_run(parte[1:-1]); run.font.name = "Consolas"
                run._r.get_or_add_rPr().get_or_add_rFonts().set(qn("w:eastAsia"), "Consolas")
                run.font.size = Pt((tamanho or 12) - 1)
            else:
                run = p.add_run(parte)
            if tamanho and not parte.startswith("`"):
                run.font.size = Pt(tamanho)

    def lista(self, itens: list[str]) -> None:
        """Alínea ABNT: travessão, texto alinhado à esquerda com recuo."""
        for item in itens:
            p = self.doc.add_paragraph()
            p.paragraph_format.left_indent = Cm(1.25)
            p.paragraph_format.first_line_indent = Cm(-0.5)
            p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
            texto = "– " + item
            for parte in re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", texto):
                if parte.startswith("**"):
                    p.add_run(parte[2:-2]).bold = True
                elif parte.startswith("`"):
                    run = p.add_run(parte[1:-1]); run.font.name = "Consolas"; run.font.size = Pt(11)
                elif parte:
                    p.add_run(parte)

    def codigo(self, texto: str) -> None:
        for linha in texto.strip("\n").split("\n"):
            p = self.doc.add_paragraph()
            p.paragraph_format.first_line_indent = Cm(0)
            p.paragraph_format.left_indent = Cm(0.5)
            p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            run = p.add_run(linha or " ")
            run.font.name, run.font.size = "Consolas", Pt(9)
            run._r.get_or_add_rPr().get_or_add_rFonts().set(qn("w:eastAsia"), "Consolas")

    # ------------------------------------------------------------------ ilustrações
    def _legenda(self, rotulo: str, titulo: str) -> None:
        p = self.doc.add_paragraph(style="Caption")
        p.add_run(f"{rotulo} ").bold = True
        _campo(p, f"SEQ {rotulo} \\* ARABIC", negrito=True)
        p.add_run(f" – {titulo}")

    def _fonte(self, fonte: str) -> None:
        p = self.doc.add_paragraph()
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.space_after = Pt(12)
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.add_run(f"Fonte: {fonte}").font.size = Pt(10)

    def figura(self, caminho: Path, titulo: str, fonte: str = "elaborado pelos autores (2026).",
               largura_cm: float = LARGURA_UTIL_CM, altura_max_cm: float = ALTURA_MAX_FIGURA_CM) -> None:
        caminho = Path(caminho)
        if not caminho.exists():
            self.paragrafo(f"[Imagem não encontrada: {caminho.name}]", recuo=False)
            return
        largura_px, altura_px = _tamanho_png(caminho)
        largura = min(largura_cm, altura_max_cm * largura_px / altura_px)
        self._legenda("Figura", titulo)
        p = self.doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.keep_with_next = True
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        p.add_run().add_picture(str(caminho), width=Cm(largura))
        self._fonte(fonte)

    def quadro(self, titulo: str, cabecalhos: list[str], linhas: list[list[str]], larguras_cm: list[float],
               fonte: str = "elaborado pelos autores (2026).", tamanho: float = 10) -> None:
        self._legenda("Quadro", titulo)
        tabela = self.doc.add_table(rows=1, cols=len(cabecalhos))
        tabela.style = "Table Grid"
        tabela.alignment = WD_TABLE_ALIGNMENT.CENTER
        self._preencher(tabela.rows[0], cabecalhos, larguras_cm, tamanho, negrito=True, fundo="E8F2E6")
        _repetir_cabecalho(tabela.rows[0])
        _manter_com_proxima(tabela.rows[0])
        for valores in linhas:
            linha = tabela.add_row()
            _nao_quebrar_linha(linha)
            self._preencher(linha, valores, larguras_cm, tamanho)
        self._fonte(fonte)

    def quadro_requisitos(self, titulo: str, itens: list[tuple[str, str, str]], autores: str, revisao: str, data: str,
                          rotulo_legenda: str) -> None:
        """Modelo da Aula 16: título; Autor | Revisão | Data; Nome | Código | Descrição."""
        self._legenda("Quadro", rotulo_legenda)
        larguras = [4.2, 1.8, 10.0]
        tabela = self.doc.add_table(rows=0, cols=3)
        tabela.style = "Table Grid"
        tabela.alignment = WD_TABLE_ALIGNMENT.CENTER
        topo = tabela.add_row()
        celula = topo.cells[0].merge(topo.cells[2])
        self._texto_celula(celula, titulo.upper(), 11, True, centro=True)
        _sombrear(celula, "1D704F")
        for run in celula.paragraphs[0].runs:
            run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        self._preencher(tabela.add_row(), ["Autor", "Revisão", "Data de atualização"], [4.2, 1.8, 10.0], 10, negrito=True, fundo="E8F2E6")
        self._preencher(tabela.add_row(), [autores, revisao, data], larguras, 10)
        cabecalho = tabela.add_row()
        self._preencher(cabecalho, ["Nome", "Código", "Descrição"], larguras, 10, negrito=True, fundo="E8F2E6")
        for linha in tabela.rows:
            _manter_com_proxima(linha)  # título, autores e cabeçalho ficam juntos com o primeiro requisito
        for nome, codigo, descricao in itens:
            linha = tabela.add_row()
            _nao_quebrar_linha(linha)
            self._preencher(linha, [nome, codigo, descricao], larguras, 10)
        self._fonte("elaborado pelos autores (2026), no modelo de documentação da disciplina.")

    def _preencher(self, linha, valores: list[str], larguras_cm: list[float], tamanho: float, negrito: bool = False,
                   fundo: str | None = None) -> None:
        for celula, valor, largura in zip(linha.cells, valores, larguras_cm):
            celula.width = Cm(largura)
            self._texto_celula(celula, valor, tamanho, negrito)
            if fundo:
                _sombrear(celula, fundo)

    @staticmethod
    def _texto_celula(celula, texto: str, tamanho: float, negrito: bool, centro: bool = False) -> None:
        p = celula.paragraphs[0]
        p.text = ""
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        p.paragraph_format.space_before, p.paragraph_format.space_after = Pt(2), Pt(2)
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER if centro else WD_ALIGN_PARAGRAPH.LEFT
        for parte in re.split(r"(`[^`]+`)", texto):
            if not parte:
                continue
            run = p.add_run(parte[1:-1] if parte.startswith("`") else parte)
            run.font.size, run.bold = Pt(tamanho), negrito
            if parte.startswith("`"):
                run.font.name = "Consolas"

    # ------------------------------------------------------------------ referências
    def referencia(self, texto: str) -> None:
        p = self.doc.add_paragraph()
        p.paragraph_format.first_line_indent = Cm(0)
        p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
        p.paragraph_format.space_after = Pt(12)
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        for parte in re.split(r"(\*\*[^*]+\*\*)", texto):
            if parte.startswith("**"):
                p.add_run(parte[2:-2]).bold = True
            elif parte:
                p.add_run(parte)

    def salvar(self, caminho: Path) -> Path:
        caminho = Path(caminho)
        caminho.parent.mkdir(parents=True, exist_ok=True)
        self.doc.save(str(caminho))
        return caminho


def definir_metadados(docx: Path, autor: str, titulo: str) -> None:
    """Autor e título do arquivo, sem dados pessoais: o Word grava a conta do Office em "modificado por" ao salvar."""
    documento = Document(str(docx))
    propriedades = documento.core_properties
    propriedades.author = propriedades.last_modified_by = autor
    propriedades.title, propriedades.subject, propriedades.comments, propriedades.keywords = titulo, "", "", ""
    documento.save(str(docx))


def word_para_pdf(docx: Path, pdf: Path, autor: str = "", titulo: str = "") -> Path:
    """Abre o .docx no Word, atualiza sumário, listas e numeração, salva o .docx e exporta o PDF."""
    docx, pdf = Path(docx).resolve(), Path(pdf).resolve()
    script = f"""
$ErrorActionPreference = 'Stop'
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
try {{
    $doc = $word.Documents.Open('{docx}', $false, $false)
    # Estilos internos do Word: -20 a -22 = Sumário 1 a 3; -36 = Índice de ilustrações.
    foreach ($id in -20, -21, -22, -36) {{
        $estilo = $doc.Styles.Item($id)
        $estilo.Font.Name = 'Arial'
        $estilo.Font.Size = 12
        $estilo.Font.Bold = ($id -eq -20)
        $estilo.ParagraphFormat.LeftIndent = 0
        $estilo.ParagraphFormat.FirstLineIndent = 0
        $estilo.ParagraphFormat.Alignment = 0
        $estilo.ParagraphFormat.LineSpacingRule = 1
    }}
    $doc.Fields.Update() | Out-Null
    foreach ($t in $doc.TablesOfContents) {{ $t.Update() | Out-Null }}
    foreach ($t in $doc.TablesOfFigures) {{ $t.Update() | Out-Null }}
    $doc.Fields.Update() | Out-Null
    foreach ($t in $doc.TablesOfContents) {{ $t.UpdatePageNumbers() | Out-Null }}
    $doc.Save()
    $doc.ExportAsFixedFormat('{pdf}', 17, $false, 0, 0, 1, 1, 0, $true, $true, 1)
    $doc.Close($false)
}} finally {{
    $word.Quit()
}}
"""
    definir_metadados(docx, autor, titulo)  # o PDF herda o autor e o título do .docx
    subprocess.run(["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", script], check=True,
                   capture_output=True, text=True, encoding="utf-8", errors="replace")
    definir_metadados(docx, autor, titulo)  # desfaz o "modificado por" que o Word gravou
    return pdf
