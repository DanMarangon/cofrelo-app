"""Refaz as evidências e os documentos do Cofrelo de uma vez.

Uso (na pasta do projeto, com a aplicação no ar em http://localhost:8080):
    python docs/ferramentas/gerar_tudo.py                  capturas, diagramas, wireframes, auditoria, coleta e documentos
    python docs/ferramentas/gerar_tudo.py --so-documentos  só os documentos, a partir das evidências que já existem
    python docs/ferramentas/gerar_tudo.py --zip            também monta o .zip de entrega de DW (na pasta Downloads)

As demonstrações de implantação e de falha mudam o ambiente em execução e ficam separadas:
    .\\docs\\ferramentas\\demonstrar_implantacao.ps1
    python docs/ferramentas/demonstrar_falha.py
"""

from __future__ import annotations

import subprocess
import sys
import tempfile
import urllib.request
import zipfile
from pathlib import Path

FERRAMENTAS = Path(__file__).resolve().parent
sys.path.insert(0, str(FERRAMENTAS))
import conteudo as c  # noqa: E402


def rodar(script: str, *argumentos: str, obrigatorio: bool = True) -> bool:
    print(f"\n==> {script} {' '.join(argumentos)}", flush=True)
    resultado = subprocess.run([sys.executable, str(FERRAMENTAS / script), *argumentos], cwd=c.RAIZ)
    if resultado.returncode != 0 and obrigatorio:
        raise SystemExit(f"{script} falhou (código {resultado.returncode}).")
    return resultado.returncode == 0


def no_ar(url: str) -> bool:
    try:
        with urllib.request.urlopen(url, timeout=3) as resposta:
            return resposta.status == 200
    except OSError:
        return False


def montar_zip() -> Path:
    """Documentação + aplicação (o código da main, sem as pastas de evidências) para postar no Moodle."""
    p = c.projeto()
    destino = Path.home() / "Downloads" / f"{p['projeto']} - {p['dw']['grupo']} - Entrega DW.zip"
    with tempfile.TemporaryDirectory() as pasta:
        codigo = Path(pasta) / "codigo.zip"
        subprocess.run(["git", "-C", str(c.RAIZ), "archive", "--format=zip", f"--prefix={p['projeto'].lower()}-app/",
                        "-o", str(codigo), "HEAD"], check=True)
        with zipfile.ZipFile(destino, "w", zipfile.ZIP_DEFLATED) as saida:
            for nome in [f"Documentação - {p['projeto']} - DW {p['dw']['grupo']}.pdf",
                         f"Documentação - {p['projeto']} - DW {p['dw']['grupo']}.docx",
                         f"Briefing - {p['projeto']}.pdf", f"Apresentação DW - {p['projeto']}.pdf",
                         f"Apresentação DW - {p['projeto']}.pptx"]:
                arquivo = c.ENTREGAS / nome
                if arquivo.exists():
                    saida.write(arquivo, f"documentacao/{nome}")
            with zipfile.ZipFile(codigo) as fonte:
                for item in fonte.infolist():
                    if "/docs/evidencias/" in item.filename or "/docs/entregas/" in item.filename:
                        continue
                    saida.writestr(f"aplicacao/{item.filename}", fonte.read(item))
            saida.writestr("LEIA-ME.txt", (
                f"{p['projeto']} - {p['dw']['grupo']} - {p['dw']['disciplina']}\n\n"
                "documentacao/  documentação no padrão ABNT, briefing e slides da apresentação\n"
                f"aplicacao/     código-fonte; para rodar: copie .env.example para .env e use docker compose up -d\n\n"
                f"Repositório: {p['repositorio']}\n"
                "Usuária de demonstração: mariana@cofrelo.app / cofrelo123\n"))
    return destino


def main() -> None:
    argumentos = set(sys.argv[1:])
    if "--so-documentos" not in argumentos:
        if not no_ar("http://localhost:8080/health"):
            raise SystemExit("A aplicação não respondeu em http://localhost:8080/health. Rode: docker compose up -d")
        rodar("capturar_telas.py")
        rodar("diagramas.py")
        rodar("wireframes.py")
        rodar("auditar_acessibilidade.py", obrigatorio=False)
        if no_ar("http://localhost:9090/-/ready") and no_ar("http://localhost:3000/api/health"):
            rodar("capturar_observabilidade.py", "normal")
        else:
            print("Prometheus/Grafana fora do ar: capturas de observabilidade mantidas como estão.")
        rodar("coletar_evidencias.py")
    rodar("gerar_briefing.py")
    rodar("gerar_documentacao.py")
    rodar("gerar_evidencias_devops.py")
    rodar("gerar_slides.py")
    rodar("gerar_tecnologias.py")
    if "--zip" in argumentos:
        print(f"\nPacote de entrega de DW: {montar_zip()}")
    print(f"\nDocumentos em {c.ENTREGAS}")


if __name__ == "__main__":
    main()
