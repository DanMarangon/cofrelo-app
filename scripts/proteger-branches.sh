#!/usr/bin/env bash
# Ativa a proteção das branches main e develop: mudanças só por Pull Request e com o CI verde.
# O GitHub só permite isso em repositório público ou com plano Pro. Uso: ./scripts/proteger-branches.sh
set -euo pipefail
REPOSITORIO="${1:-DanMarangon/cofrelo-app}"
for ramo in main develop; do
  gh api -X PUT "repos/$REPOSITORIO/branches/$ramo/protection" --input - <<JSON
{"required_status_checks":{"strict":false,"contexts":["Validação e testes","Verificações de segurança","Imagem Docker"]},
 "enforce_admins":false,
 "required_pull_request_reviews":{"required_approving_review_count":1},
 "restrictions":null,
 "allow_force_pushes":false,
 "allow_deletions":false}
JSON
  echo "Proteção ativada na branch $ramo."
done
