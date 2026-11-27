"""Coleta do GitHub (com o GitHub CLI) os dados que o PDF de evidências de DevOps mostra.

Pull Requests, branches, execuções do GitHub Actions (sucessos e falhas), releases com os arquivos anexados e
versões da imagem no GHCR. Tudo vai para docs/evidencias/pipeline/dados.json.

Requisito: gh instalado e autenticado (gh auth login). Uso: python docs/ferramentas/coletar_evidencias.py
"""

from __future__ import annotations

import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import conteudo as c  # noqa: E402

REPO = c.projeto()["repositorio"].removeprefix("https://github.com/")
SAIDA = c.EVIDENCIAS / "pipeline"


def gh(*argumentos: str, padrao=None):
    resultado = subprocess.run(["gh", *argumentos], capture_output=True, text=True, encoding="utf-8")
    if resultado.returncode != 0:
        if padrao is not None:
            return padrao
        raise RuntimeError(f"gh {' '.join(argumentos)}: {resultado.stderr.strip()}")
    return json.loads(resultado.stdout) if resultado.stdout.strip().startswith(("[", "{")) else resultado.stdout


def execucoes() -> list[dict]:
    campos = "databaseId,displayTitle,headBranch,event,status,conclusion,createdAt,updatedAt,workflowName,url,headSha"
    return gh("run", "list", "-R", REPO, "--limit", "200", "--json", campos)


def jobs(id_execucao: int) -> list[dict]:
    dados = gh("run", "view", str(id_execucao), "-R", REPO, "--json", "jobs", padrao={"jobs": []})
    return [{"nome": j["name"], "conclusao": j["conclusion"], "url": j["url"],
             "passos": [{"nome": s["name"], "conclusao": s["conclusion"]} for s in j.get("steps", [])]}
            for j in dados["jobs"]]


def contar_commits(ramo: str) -> int:
    resultado = subprocess.run(["git", "-C", str(c.RAIZ), "rev-list", "--count", ramo], capture_output=True, text=True)
    return int(resultado.stdout.strip() or 0)


def main() -> Path:
    SAIDA.mkdir(parents=True, exist_ok=True)
    prs = gh("pr", "list", "-R", REPO, "--state", "all", "--limit", "100", "--json",
             "number,title,headRefName,baseRefName,state,author,createdAt,mergedAt,url")
    for pr in prs:
        pr["autor"] = pr.pop("author", {}).get("login", "")
    runs = execucoes()
    ci = [r for r in runs if r["workflowName"] == "CI"]
    release = [r for r in runs if r["workflowName"] == "Release"]

    def primeira(filtro):
        return next((r for r in ci if filtro(r)), None)

    falha_controlada = [r for r in ci if r["headBranch"] == "feature/previsao-das-metas" and r["conclusion"] == "failure"]
    correcao = [r for r in ci if r["headBranch"] == "feature/previsao-das-metas" and r["conclusion"] == "success"]
    destaques = {
        "sucesso_main": primeira(lambda r: r["headBranch"] == "main" and r["conclusion"] == "success"),
        "sucesso_develop": primeira(lambda r: r["headBranch"] == "develop" and r["conclusion"] == "success"),
        "falha_controlada": falha_controlada[-1] if falha_controlada else None,
        "falha_controlada_todas": falha_controlada,
        "correcao": correcao[0] if correcao else None,
        "falha_trivy_tag": primeira(lambda r: r["headBranch"] == "feature/docker" and r["conclusion"] == "failure"),
        "falha_gitleaks": primeira(lambda r: r["headBranch"] == "feature/nucleo" and r["conclusion"] == "failure"),
    }
    for chave in ["sucesso_main", "falha_controlada", "correcao"]:
        if destaques[chave]:
            destaques[chave]["jobs"] = jobs(destaques[chave]["databaseId"])
    for r in release:
        r["jobs"] = jobs(r["databaseId"])

    releases = []
    for item in gh("release", "list", "-R", REPO, "--json", "tagName,name,publishedAt", padrao=[]):
        detalhe = gh("release", "view", item["tagName"], "-R", REPO, "--json", "tagName,name,url,publishedAt,body,assets", padrao={})
        detalhe["assets"] = [{"nome": a["name"], "tamanho": a["size"], "url": a["url"]} for a in detalhe.get("assets", [])]
        versao = item["tagName"].removeprefix("v")
        detalhe["imagem_txt"] = gh("release", "download", item["tagName"], "-R", REPO, "-p", f"imagem-{versao}.txt", "-O", "-", padrao="")
        releases.append(detalhe)

    pacote = gh("api", f"/users/{REPO.split('/')[0]}/packages/container/{REPO.split('/')[1]}/versions?per_page=30", padrao=[])
    versoes_imagem = [{"digest": v.get("name"), "tags": v.get("metadata", {}).get("container", {}).get("tags", []),
                       "criada": v.get("created_at")} for v in pacote] if isinstance(pacote, list) else []

    dados = {
        "coletado_em": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "repositorio": REPO,
        "branches": [b["name"] for b in gh("api", f"repos/{REPO}/branches?per_page=100", padrao=[])],
        "tags": [t["name"] for t in gh("api", f"repos/{REPO}/tags", padrao=[])],
        "commits": {ramo: contar_commits(ramo) for ramo in ["origin/main", "origin/develop"]},
        "prs": sorted(prs, key=lambda p: p["number"]),
        "resumo_ci": {
            "total": len(ci),
            "sucesso": sum(r["conclusion"] == "success" for r in ci),
            "falha": sum(r["conclusion"] == "failure" for r in ci),
            "cancelada": sum(r["conclusion"] == "cancelled" for r in ci),
        },
        "destaques": destaques,
        "releases_workflow": release,
        "releases": releases,
        "imagem": {"nome": c.projeto()["imagem_docker"], "versoes": versoes_imagem},
    }
    arquivo = SAIDA / "dados.json"
    arquivo.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")
    return arquivo


if __name__ == "__main__":
    print(main())
