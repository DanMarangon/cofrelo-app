"""Demonstração de como uma falha é identificada depois da implantação (requisito de observabilidade).

Para o MySQL, mostra o /health respondendo 503, o container marcado como unhealthy, as linhas do log e o alerta
BancoIndisponivel disparando no Prometheus (com capturas da tela), e depois religa o banco e confere a volta.

Precisa da aplicação e do perfil de observabilidade no ar:
    docker compose --profile observabilidade up -d
Uso: python docs/ferramentas/demonstrar_falha.py
Saída: docs/evidencias/observabilidade/deteccao-de-falha.txt e falha-*.png
"""

from __future__ import annotations

import json
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import conteudo as c  # noqa: E402

SAIDA = c.EVIDENCIAS / "observabilidade"
LINHAS: list[str] = []


def registrar(texto: str = "") -> None:
    linha = f"[{datetime.now():%H:%M:%S}] {texto}" if texto else ""
    LINHAS.append(linha)
    print(linha, flush=True)


def comando(*args: str) -> str:
    resultado = subprocess.run(list(args), cwd=c.RAIZ, capture_output=True, text=True, encoding="utf-8", errors="replace")
    return (resultado.stdout + resultado.stderr).strip()


def saude() -> tuple[int, str]:
    try:
        with urllib.request.urlopen("http://localhost:8080/health", timeout=5) as resposta:
            return resposta.status, resposta.read().decode()
    except urllib.error.HTTPError as erro:
        return erro.code, erro.read().decode()
    except OSError as erro:
        return 0, str(erro)


def alertas_disparados() -> list[str]:
    try:
        with urllib.request.urlopen("http://localhost:9090/api/v1/alerts", timeout=5) as resposta:
            dados = json.load(resposta)
        return [f"{a['labels']['alertname']} ({a['state']})" for a in dados["data"]["alerts"]]
    except OSError:
        return []


def esperar(condicao, limite: float, intervalo: float = 3) -> bool:
    fim = time.time() + limite
    while time.time() < fim:
        if condicao():
            return True
        time.sleep(intervalo)
    return False


def main() -> None:
    SAIDA.mkdir(parents=True, exist_ok=True)
    status, corpo = saude()
    registrar(f"Antes da falha: GET /health -> {status} {corpo}")
    registrar(f"Alertas ativos no Prometheus: {alertas_disparados() or 'nenhum'}")
    registrar()
    registrar("Simulando a falha: docker compose stop banco")
    registrar(comando("docker", "compose", "stop", "banco").replace("\n", " | "))
    esperar(lambda: saude()[0] == 503, 30, 2)
    status, corpo = saude()
    registrar(f"GET /health -> {status} {corpo}")
    registrar()
    registrar("Esperando o Docker marcar o container como unhealthy (3 verificações seguidas falhando)...")
    esperar(lambda: "unhealthy" in comando("docker", "compose", "ps", "app", "--format", "{{.Status}}"), 120, 5)
    registrar("docker compose ps app -> " + comando("docker", "compose", "ps", "app", "--format", "{{.Name}} {{.Status}}"))
    registrar()
    registrar("Linhas do log da aplicação com o erro (docker compose logs app):")
    for linha in comando("docker", "compose", "logs", "app", "--since", "3m", "--no-log-prefix").splitlines():
        if '"saude.falha"' in linha or '"banco.erro"' in linha:
            LINHAS.append("    " + linha.strip()[:400])
            if sum('"evento"' in l for l in LINHAS) >= 4:
                break
    print("\n".join(LINHAS[-4:]))
    registrar()
    registrar("Esperando o Prometheus disparar o alerta BancoIndisponivel (regra com for: 30s)...")
    esperar(lambda: any(a.startswith("BancoIndisponivel (firing)") for a in alertas_disparados()), 180, 5)
    registrar(f"Alertas no Prometheus: {alertas_disparados()}")
    capturas = subprocess.run([sys.executable, str(Path(__file__).with_name("capturar_observabilidade.py")), "falha"],
                              capture_output=True, text=True, encoding="utf-8", errors="replace")
    registrar("Capturas da tela durante a falha: " + ", ".join(Path(l).name for l in capturas.stdout.split() if l.endswith(".png")))
    registrar()
    registrar("Recuperando: docker compose start banco")
    registrar(comando("docker", "compose", "start", "banco").replace("\n", " | "))
    esperar(lambda: saude()[0] == 200, 120, 3)
    status, corpo = saude()
    registrar(f"GET /health -> {status} {corpo}")
    esperar(lambda: not any(a.startswith("BancoIndisponivel") for a in alertas_disparados()), 120, 5)
    registrar(f"Alertas ativos no Prometheus: {alertas_disparados() or 'nenhum'}")
    (SAIDA / "deteccao-de-falha.txt").write_text("\n".join(LINHAS) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
