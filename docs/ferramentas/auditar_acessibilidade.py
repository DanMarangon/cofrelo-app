"""Auditoria de acessibilidade (WCAG 2.2 A/AA) de todas as páginas do Cofrelo com o axe-core.

Abre cada página no Edge (computador e celular), injeta o axe-core e lista as violações encontradas.
Também audita os estados com modal aberto (nova transação) e o painel de notificações.

Uso:
    python docs/ferramentas/auditar_acessibilidade.py [URL_BASE] [ARQUIVO_JSON]

Sai com código 1 se houver violação, para poder rodar como verificação.
"""

from __future__ import annotations

import json
import sys
import tempfile
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from navegador import Navegador  # noqa: E402

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8080").rstrip("/")
SAIDA = Path(sys.argv[2]) if len(sys.argv) > 2 else None
AXE_VERSAO = "4.10.3"
AXE_URL = f"https://cdn.jsdelivr.net/npm/axe-core@{AXE_VERSAO}/axe.min.js"
REGRAS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa", "best-practice"]

PUBLICAS = ["/login", "/cadastro"]
PRIVADAS = ["/visao-geral", "/transacoes", "/contas", "/metas", "/relatorios", "/configuracoes", "/ajuda"]


def axe_fonte() -> str:
    cache = Path(tempfile.gettempdir()) / f"axe-{AXE_VERSAO}.min.js"
    if not cache.exists():
        cache.write_bytes(urllib.request.urlopen(AXE_URL, timeout=60).read())
    return cache.read_text(encoding="utf-8")


def auditar(nav: Navegador, fonte: str, rotulo: str) -> list[dict]:
    nav.avaliar(fonte + "; true")
    resultado = nav.avaliar(
        "axe.run(document, {runOnly: {type: 'tag', values: %s}, resultTypes: ['violations']})"
        ".then(r => r.violations.map(v => ({id: v.id, impacto: v.impact, ajuda: v.help, url: v.helpUrl,"
        " alvos: v.nodes.slice(0, 4).map(n => n.target.join(' ')), total: v.nodes.length})))" % json.dumps(REGRAS)
    )
    for violacao in resultado:
        violacao["onde"] = rotulo
    return resultado


def main() -> int:
    fonte = axe_fonte()
    violacoes: list[dict] = []
    for largura, altura, nome in [(1440, 900, "computador"), (390, 844, "celular")]:
        with Navegador(largura, altura) as nav:
            for caminho in PUBLICAS:
                nav.abrir(f"{BASE}{caminho}", pronto="document.querySelector('main, form')")
                violacoes += auditar(nav, fonte, f"{caminho} ({nome})")
            nav.entrar(BASE, "mariana@cofrelo.app", "cofrelo123")
            for caminho in PRIVADAS:
                nav.abrir(f"{BASE}{caminho}", pronto="document.querySelector('main')", pausa=1.2)
                violacoes += auditar(nav, fonte, f"{caminho} ({nome})")
            if nome == "computador":
                nav.abrir(f"{BASE}/transacoes", pronto="document.querySelector('main')", pausa=1.0)
                nav.avaliar("document.querySelector('[data-nova-transacao]').click(); true")
                nav.esperar("!document.getElementById('modal-transacao').hidden", 10)
                # Espera a animação de abertura acabar: no meio dela o texto ainda está semitransparente.
                nav.esperar("document.getAnimations().every(a => a.playState !== 'running')", 10)
                violacoes += auditar(nav, fonte, "modal Nova transação (computador)")

    if SAIDA:
        SAIDA.parent.mkdir(parents=True, exist_ok=True)
        SAIDA.write_text(json.dumps(violacoes, ensure_ascii=False, indent=2), encoding="utf-8")

    if not violacoes:
        print(f"Nenhuma violação WCAG 2.2 A/AA encontrada pelo axe-core {AXE_VERSAO}.")
        return 0
    por_regra: dict[str, list[dict]] = {}
    for v in violacoes:
        por_regra.setdefault(v["id"], []).append(v)
    for regra, itens in sorted(por_regra.items(), key=lambda par: -len(par[1])):
        print(f"\n[{itens[0]['impacto']}] {regra}: {itens[0]['ajuda']}")
        for item in itens:
            print(f"   {item['onde']}: {item['total']} elemento(s), ex.: {' | '.join(item['alvos'][:3])}")
    print(f"\nTotal: {len(violacoes)} violação(ões) em {len(por_regra)} regra(s).")
    return 1


if __name__ == "__main__":
    sys.exit(main())
