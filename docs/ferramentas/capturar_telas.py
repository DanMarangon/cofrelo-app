"""Captura as telas do Cofrelo rodando (Docker ou XAMPP), já logado com a conta de demonstração.

Uso:
    python docs/ferramentas/capturar_telas.py [endereço] [pasta de saída]
    python docs/ferramentas/capturar_telas.py http://localhost:8080 docs/telas

As imagens são usadas na documentação de Desenvolvimento Web e no PDF de evidências de DevOps.
"""

from __future__ import annotations

import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from navegador import Navegador  # noqa: E402

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost:8080").rstrip("/")
SAIDA = Path(sys.argv[2] if len(sys.argv) > 2 else Path(__file__).resolve().parents[1] / "evidencias" / "telas")
SEM_CARREGANDO = "!document.querySelector('.esqueleto')"


def capturar_todas() -> list[Path]:
    arquivos: list[Path] = []
    with Navegador(1440, 900) as nav:
        nav.abrir(f"{BASE}/login", pronto="document.querySelector('#acesso-email')")
        arquivos.append(nav.capturar(SAIDA / "01-entrar.png", pagina_inteira=False))
        nav.abrir(f"{BASE}/cadastro", pronto="document.querySelector('#cad-nome')")
        arquivos.append(nav.capturar(SAIDA / "02-criar-conta.png", pagina_inteira=False))

        nav.entrar(BASE, "mariana@cofrelo.app", "cofrelo123")
        nav.esperar(SEM_CARREGANDO + " && document.querySelector('#grafico-fluxo svg')")
        time.sleep(0.8)
        arquivos.append(nav.capturar(SAIDA / "03-visao-geral.png"))

        nav.abrir(f"{BASE}/transacoes", pronto=SEM_CARREGANDO + " && document.querySelector('#linhas-transacoes tr')")
        arquivos.append(nav.capturar(SAIDA / "04-transacoes.png"))

        nav.avaliar("document.querySelector('#filtro-periodo').click(), true")
        time.sleep(0.4)
        arquivos.append(nav.capturar(SAIDA / "05-calendario-de-periodo.png", pagina_inteira=False))
        nav.avaliar("document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'})), true")

        nav.avaliar("document.querySelector('[data-nova-transacao]').click(), true")
        nav.esperar("!document.querySelector('#modal-transacao').hidden")
        nav.avaliar("""(() => { const d = document.querySelector('#mt-descricao'); d.value = 'Supermercado';
            d.dispatchEvent(new Event('input')); const v = document.querySelector('#mt-valor'); v.value = '28640';
            v.dispatchEvent(new Event('input')); return true; })()""")
        time.sleep(0.6)
        arquivos.append(nav.capturar(SAIDA / "06-nova-transacao.png", pagina_inteira=False))

        nav.avaliar("document.querySelector('#mt-abas [data-tipo=\"assinatura\"]').click(), true")
        nav.avaliar("""(() => { const d = document.querySelector('#mt-descricao'); d.value = 'Netflix';
            d.dispatchEvent(new Event('input')); const v = document.querySelector('#mt-valor'); v.value = '5590';
            v.dispatchEvent(new Event('input')); return true; })()""")
        time.sleep(0.5)
        arquivos.append(nav.capturar(SAIDA / "07-nova-assinatura.png", pagina_inteira=False))
        nav.avaliar("document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'})), true")

        nav.avaliar("document.querySelector('#abas-tipo [data-tipo=\"assinatura\"]').click(), true")
        nav.esperar("document.querySelector('#lista-assinaturas li')")
        time.sleep(0.4)
        arquivos.append(nav.capturar(SAIDA / "08-assinaturas.png"))

        nav.abrir(f"{BASE}/contas", pronto=SEM_CARREGANDO + " && document.querySelector('#lista-contas article')")
        arquivos.append(nav.capturar(SAIDA / "09-minhas-contas.png"))

        nav.abrir(f"{BASE}/metas", pronto=SEM_CARREGANDO + " && document.querySelector('#lista-metas article')")
        time.sleep(0.6)
        arquivos.append(nav.capturar(SAIDA / "10-metas-e-simulador.png"))

        nav.abrir(f"{BASE}/relatorios", pronto=SEM_CARREGANDO + " && document.querySelector('#mapa-gastos svg')")
        time.sleep(0.6)
        arquivos.append(nav.capturar(SAIDA / "11-relatorios.png"))

        nav.abrir(f"{BASE}/configuracoes", pronto="document.querySelector('#cfg-nome')")
        arquivos.append(nav.capturar(SAIDA / "12-configuracoes.png"))

        nav.abrir(f"{BASE}/ajuda", pronto="document.querySelector('#lista-perguntas')")
        arquivos.append(nav.capturar(SAIDA / "13-central-de-ajuda.png"))

        nav.abrir(f"{BASE}/visao-geral", pronto=SEM_CARREGANDO)
        nav.esperar("!document.querySelector('#selo-notificacoes').hidden || document.querySelector('#lista-notificacoes li')")
        nav.avaliar("document.querySelector('#botao-notificacoes').click(), true")
        time.sleep(0.4)
        arquivos.append(nav.capturar(SAIDA / "14-notificacoes.png", pagina_inteira=False))

    with Navegador(390, 844, escala=2) as celular:
        celular.entrar(BASE, "mariana@cofrelo.app", "cofrelo123")
        celular.esperar(SEM_CARREGANDO)
        time.sleep(0.8)
        arquivos.append(celular.capturar(SAIDA / "15-celular-visao-geral.png", pagina_inteira=False))
    return arquivos


if __name__ == "__main__":
    for arquivo in capturar_todas():
        print(arquivo)
