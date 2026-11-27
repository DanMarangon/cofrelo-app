"""Capturas do Prometheus (alvos e alertas) e do painel do Grafana, para o PDF de evidências de DevOps.

Precisa do perfil de observabilidade no ar: docker compose --profile observabilidade up -d
O Grafana do projeto tem acesso anônimo só de leitura, então não precisa de senha.

Uso: python docs/ferramentas/capturar_observabilidade.py [prefixo]
     prefixo: "normal" (padrão) ou outro nome, como "falha", para capturar durante a demonstração de falha.
Saída: docs/evidencias/observabilidade/<prefixo>-*.png
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from navegador import Navegador  # noqa: E402

PREFIXO = sys.argv[1] if len(sys.argv) > 1 else "normal"
SAIDA = Path(__file__).resolve().parents[1] / "evidencias" / "observabilidade"
PROMETHEUS = "http://localhost:9090"
GRAFANA = "http://localhost:3000"


def main() -> list[Path]:
    arquivos = []
    with Navegador(1440, 900) as nav:
        nav.abrir(f"{PROMETHEUS}/alerts", pronto="document.body.innerText.includes('BancoIndisponivel')", pausa=1.5)
        nav.avaliar("document.querySelectorAll('button').forEach(b => { if (/expand|show/i.test(b.innerText)) b.click(); }); true")
        arquivos.append(nav.capturar(SAIDA / f"{PREFIXO}-prometheus-alertas.png", pagina_inteira=False))
        nav.abrir(f"{PROMETHEUS}/targets", pronto="document.body.innerText.toLowerCase().includes('cofrelo')", pausa=1.5)
        arquivos.append(nav.capturar(SAIDA / f"{PREFIXO}-prometheus-alvos.png", pagina_inteira=False))
        consulta = "cofrelo_banco_disponivel"
        nav.abrir(f"{PROMETHEUS}/query?g0.expr={consulta}&g0.tab=graph&g0.range_input=30m",
                  pronto="document.body.innerText.includes('cofrelo_banco_disponivel')", pausa=2.5)
        arquivos.append(nav.capturar(SAIDA / f"{PREFIXO}-prometheus-banco-disponivel.png", pagina_inteira=False))

        nav.definir_tamanho(1600, 1000)
        nav.largura, nav.altura = 1600, 1000
        nav.abrir(f"{GRAFANA}/d/cofrelo/?orgId=1&from=now-30m&to=now&refresh=10s&kiosk",
                  pronto="document.body.innerText.includes('Banco de dados')", pausa=6)
        arquivos.append(nav.capturar(SAIDA / f"{PREFIXO}-grafana-painel.png", pagina_inteira=False))
    return arquivos


if __name__ == "__main__":
    for arquivo in main():
        print(arquivo)
