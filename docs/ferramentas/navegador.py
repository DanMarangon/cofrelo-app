"""Controle do Microsoft Edge pelo protocolo de depuração (CDP).

Abre páginas, executa JavaScript, tira capturas de tela e gera PDF. É usado para capturar as telas
do sistema, desenhar wireframes e diagramas e gerar os documentos em PDF.

Requisitos: Microsoft Edge instalado e o pacote websocket-client (pip install websocket-client).
"""

from __future__ import annotations

import base64
import json
import socket
import subprocess
import tempfile
import time
import urllib.request
from pathlib import Path

import websocket

EDGE = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"


def _porta_livre() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


class Navegador:
    def __init__(self, largura: int = 1440, altura: int = 900, escala: float = 1.0):
        self.largura, self.altura, self.escala = largura, altura, escala
        porta = _porta_livre()
        perfil = tempfile.mkdtemp(prefix="edge-cofrelo-")
        self.processo = subprocess.Popen(
            [EDGE, "--headless=new", "--disable-gpu", "--no-first-run", "--hide-scrollbars",
             "--disable-extensions", f"--user-data-dir={perfil}", f"--remote-debugging-port={porta}",
             f"--window-size={largura},{altura}", "about:blank"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        alvos = None
        for _ in range(100):
            try:
                alvos = json.load(urllib.request.urlopen(f"http://127.0.0.1:{porta}/json", timeout=1))
                break
            except OSError:
                time.sleep(0.2)
        pagina = next(a for a in alvos if a["type"] == "page")
        self.ws = websocket.create_connection(pagina["webSocketDebuggerUrl"], suppress_origin=True, timeout=120)
        self._id = 0
        self.cmd("Page.enable")
        self.cmd("Runtime.enable")
        self.definir_tamanho(largura, altura)

    def cmd(self, metodo: str, **parametros):
        self._id += 1
        self.ws.send(json.dumps({"id": self._id, "method": metodo, "params": parametros}))
        while True:
            mensagem = json.loads(self.ws.recv())
            if mensagem.get("id") == self._id:
                if "error" in mensagem:
                    raise RuntimeError(f"{metodo}: {mensagem['error']}")
                return mensagem.get("result", {})

    def definir_tamanho(self, largura: int, altura: int) -> None:
        self.cmd("Emulation.setDeviceMetricsOverride", width=largura, height=altura,
                 deviceScaleFactor=self.escala, mobile=largura < 700)

    def avaliar(self, expressao: str):
        resultado = self.cmd("Runtime.evaluate", expression=expressao, returnByValue=True, awaitPromise=True)
        if "exceptionDetails" in resultado:
            raise RuntimeError(f"Erro no JavaScript: {resultado['exceptionDetails'].get('text')} em {expressao[:80]}")
        return resultado.get("result", {}).get("value")

    def esperar(self, condicao: str, limite: float = 20.0) -> None:
        fim = time.time() + limite
        while time.time() < fim:
            try:
                if self.avaliar(f"Boolean({condicao})"):
                    return
            except RuntimeError:
                pass
            time.sleep(0.2)
        raise TimeoutError(f"A condição não ficou verdadeira em {limite}s: {condicao}")

    def abrir(self, url: str, pronto: str | None = None, pausa: float = 0.4) -> None:
        self.cmd("Page.navigate", url=url)
        self.esperar("document.readyState === 'complete'")
        self.avaliar("document.fonts.ready.then(() => true)")
        if pronto:
            self.esperar(pronto)
        time.sleep(pausa)

    def capturar(self, arquivo: str | Path, pagina_inteira: bool = True) -> Path:
        arquivo = Path(arquivo)
        arquivo.parent.mkdir(parents=True, exist_ok=True)
        if pagina_inteira:
            altura = int(self.avaliar("Math.ceil(document.documentElement.scrollHeight)") or self.altura)
            self.definir_tamanho(self.largura, max(self.altura, altura))
            time.sleep(0.3)
        dados = self.cmd("Page.captureScreenshot", format="png", captureBeyondViewport=False)["data"]
        arquivo.write_bytes(base64.b64decode(dados))
        if pagina_inteira:
            self.definir_tamanho(self.largura, self.altura)
        return arquivo

    def pdf(self, arquivo: str | Path, **opcoes) -> Path:
        arquivo = Path(arquivo)
        padrao = dict(printBackground=True, preferCSSPageSize=True, generateTaggedPDF=True, generateDocumentOutline=True)
        padrao.update(opcoes)
        try:
            dados = self.cmd("Page.printToPDF", **padrao)["data"]
        except RuntimeError:
            padrao.pop("generateDocumentOutline", None)
            dados = self.cmd("Page.printToPDF", **padrao)["data"]
        arquivo.write_bytes(base64.b64decode(dados))
        return arquivo

    def entrar(self, base: str, email: str, senha: str) -> None:
        """Faz login pelo formulário, como uma pessoa faria."""
        self.abrir(f"{base}/login", pronto="document.querySelector('#acesso-email')")
        self.avaliar(f"""(() => {{
            document.querySelector('#acesso-email').value = {json.dumps(email)};
            document.querySelector('#acesso-senha').value = {json.dumps(senha)};
            document.querySelector('#formulario-acesso').requestSubmit();
            return true;
        }})()""")
        self.esperar("location.pathname.endsWith('/visao-geral')", 20)
        self.esperar("document.readyState === 'complete'")

    def fechar(self) -> None:
        try:
            self.ws.close()
        finally:
            self.processo.terminate()

    def __enter__(self):
        return self

    def __exit__(self, *_):
        self.fechar()
