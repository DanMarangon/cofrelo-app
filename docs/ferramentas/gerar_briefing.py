"""Gera o briefing do Cofrelo (Word e PDF) a partir do Briefing Modelo da Aula 16.

Uso: python docs/ferramentas/gerar_briefing.py
Saída: docs/entregas/Briefing - Cofrelo.docx e .pdf
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from docx.enum.text import WD_ALIGN_PARAGRAPH  # noqa: E402
from docx.shared import Cm, Pt, RGBColor  # noqa: E402

import conteudo as c  # noqa: E402
from abnt import DocumentoAbnt, word_para_pdf  # noqa: E402

VERDE = RGBColor(0x1D, 0x70, 0x4F)


def gerar() -> list[Path]:
    p = c.projeto()
    doc = DocumentoAbnt()
    d = doc.doc

    topo = d.add_paragraph()
    topo.alignment = WD_ALIGN_PARAGRAPH.LEFT
    topo.paragraph_format.first_line_indent = Cm(0)
    run = topo.add_run(f"{p['instituicao']} · {p['curso']} · {p['dw']['disciplina']} ({p['dw']['professor']})")
    run.font.size, run.font.color.rgb = Pt(9), RGBColor(0x61, 0x6A, 0x64)

    titulo = d.add_paragraph()
    titulo.paragraph_format.first_line_indent = Cm(0)
    titulo.paragraph_format.space_before, titulo.paragraph_format.space_after = Pt(12), Pt(2)
    run = titulo.add_run("BRIEFING")
    run.bold, run.font.size, run.font.color.rgb = True, Pt(20), VERDE
    sub = d.add_paragraph()
    sub.paragraph_format.first_line_indent = Cm(0)
    sub.paragraph_format.space_after = Pt(10)
    run = sub.add_run(f"Projeto: {p['projeto']} – {p['subtitulo'].lower()}")
    run.bold, run.font.size = True, Pt(13)

    cliente = p["cliente_ficticio"]
    doc.quadro(
        "Dados do briefing",
        ["Item", "Informação"],
        [
            ["Cliente", f"{cliente['empresa']} (empresa fictícia, criada para o projeto acadêmico)"],
            ["Entrevistada", cliente["contato"]],
            ["Equipe", f"{p['dw']['grupo']}: " + ", ".join(i["nome"] for i in c.integrantes_dw())],
            ["Data", "17 de novembro de 2026"],
            ["Modelo usado", "Briefing Modelo da Aula 16 (18 perguntas)"],
        ],
        [3.5, 12.5],
        fonte="elaborado pelos autores (2026).",
    )

    for numero, (pergunta, resposta) in enumerate(c.briefing(), start=1):
        q = d.add_paragraph()
        q.paragraph_format.first_line_indent = Cm(0)
        q.paragraph_format.space_before, q.paragraph_format.space_after = Pt(10), Pt(2)
        q.paragraph_format.keep_with_next = True
        run = q.add_run(f"{numero}. {pergunta}")
        run.bold, run.font.color.rgb = True, VERDE
        doc.paragrafo(resposta, recuo=False)

    d.add_paragraph()
    doc.paragrafo("Observação: a empresa e a entrevistada são fictícias e foram criadas pela equipe para simular o "
                  "atendimento a um cliente, como pede o enunciado do projeto final.", recuo=False, tamanho=10)

    pasta = c.ENTREGAS
    docx = doc.salvar(pasta / "Briefing - Cofrelo.docx")
    pdf = word_para_pdf(docx, pasta / "Briefing - Cofrelo.pdf", f"{p['dw']['grupo']} – {p['dw']['disciplina']}", f"Briefing – {p['projeto']}")
    return [docx, pdf]


if __name__ == "__main__":
    for arquivo in gerar():
        print(arquivo)
