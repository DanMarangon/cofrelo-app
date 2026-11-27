#!/usr/bin/env bash
# Implanta uma versão do Cofrelo com Docker Compose, confere o /health e volta para a anterior se falhar.
#
#   ./scripts/implantar.sh 1.0.0
#   ./scripts/implantar.sh 1.1.0 --simular-falha   # testa o rollback automático
#   ./scripts/implantar.sh --voltar                # volta para a versão anterior
#   ./scripts/implantar.sh --situacao              # mostra a versão em execução
#
# Mesma lógica do scripts/implantar.ps1 (Windows).
set -euo pipefail

cd "$(dirname "$0")/.."
IMAGEM="ghcr.io/danmarangon/cofrelo-app"
REPOSITORIO="DanMarangon/cofrelo-app"
PORTA="${APP_PORTA:-8080}"
ESPERA="${ESPERA_SEGUNDOS:-60}"
ESTADO=".deploy"
mkdir -p "$ESTADO"

escrever() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }
registrar() { printf '%s %s\n' "$(date +%Y-%m-%dT%H:%M:%S)" "$*" >> "$ESTADO/historico.log"; }
ler() { [ -f "$1" ] && tr -d '[:space:]' < "$1" || true; }
saude() { curl -fsS --max-time 3 "http://localhost:$PORTA/health" 2>/dev/null || true; }

garantir_imagem() {
  local versao="$1" referencia="$IMAGEM:$1"
  if [ -n "$(docker images -q "$referencia")" ]; then escrever "A imagem $referencia já está aqui."; return; fi
  escrever "Baixando $referencia do GitHub Container Registry..."
  if docker pull "$referencia" > /dev/null; then return; fi
  escrever "Sem acesso ao registro. Baixando a imagem anexada à release v$versao..."
  local pasta="$ESTADO/release-$versao"
  mkdir -p "$pasta"
  gh release download "v$versao" --repo "$REPOSITORIO" --pattern "cofrelo-imagem-$versao.tar.gz" --pattern "imagem-$versao.txt" --dir "$pasta" --clobber
  docker load -i "$pasta/cofrelo-imagem-$versao.tar.gz" > /dev/null
  local esperado carregado
  esperado=$(sed -n 's/^id=//p' "$pasta/imagem-$versao.txt")
  carregado=$(docker image inspect --format '{{.Id}}' "$referencia")
  [ -z "$esperado" ] || [ "$esperado" = "$carregado" ] || { escrever "ID da imagem diferente do publicado."; exit 2; }
  escrever "Imagem conferida: $carregado"
}

subir() {
  COFRELO_VERSAO="$1" SIMULAR_FALHA="$2" docker compose up -d --no-build --force-recreate app
}

esperar_saude() {
  local versao="$1" limite=$((SECONDS + ESPERA)) resposta
  while [ $SECONDS -lt $limite ]; do
    resposta=$(saude)
    if echo "$resposta" | grep -q '"status":"ok"' && echo "$resposta" | grep -q "\"versao\":\"$versao\""; then
      echo "$resposta"
      return 0
    fi
    sleep 2
  done
  return 1
}

VERSAO=""
FALHAR=false
case "${1:-}" in
  --situacao)
    escrever "Versão registrada: $(ler "$ESTADO/versao-atual.txt") (anterior: $(ler "$ESTADO/versao-anterior.txt"))"
    escrever "Health: $(saude)"
    exit 0 ;;
  --voltar)
    VERSAO=$(ler "$ESTADO/versao-anterior.txt")
    [ -n "$VERSAO" ] || { escrever "Não há versão anterior registrada."; exit 1; }
    escrever "Rollback manual: voltando para a versão $VERSAO." ;;
  "") escrever "Informe a versão. Exemplo: ./scripts/implantar.sh 1.0.0"; exit 1 ;;
  *) VERSAO="${1#v}"; [ "${2:-}" = "--simular-falha" ] && FALHAR=true ;;
esac

ATUAL=$(ler "$ESTADO/versao-atual.txt")
escrever "Versão em execução: ${ATUAL:-nenhuma}. Versão pedida: $VERSAO."
garantir_imagem "$VERSAO"
escrever "Trocando o container pela versão $VERSAO (estratégia recreate)..."
subir "$VERSAO" "$FALHAR"

if resultado=$(esperar_saude "$VERSAO"); then
  [ -n "$ATUAL" ] && [ "$ATUAL" != "$VERSAO" ] && echo "$ATUAL" > "$ESTADO/versao-anterior.txt"
  echo "$VERSAO" > "$ESTADO/versao-atual.txt"
  registrar "IMPLANTADA $VERSAO (antes: $ATUAL)"
  escrever "Implantação concluída: $resultado"
  exit 0
fi

escrever "A versão $VERSAO não ficou saudável em $ESPERA segundos. Últimas linhas do log:"
docker compose logs app --tail 12 || true
if [ -n "$ATUAL" ] && [ "$ATUAL" != "$VERSAO" ]; then
  escrever "ROLLBACK: voltando para a versão $ATUAL..."
  subir "$ATUAL" false
  if volta=$(esperar_saude "$ATUAL"); then
    registrar "ROLLBACK $VERSAO -> $ATUAL"
    escrever "ROLLBACK OK: a versão $ATUAL voltou a atender. $volta"
    exit 1
  fi
  registrar "ROLLBACK FALHOU $VERSAO -> $ATUAL"
  escrever "ROLLBACK FALHOU: confira os containers."
  exit 2
fi
registrar "FALHOU $VERSAO (sem versão anterior)"
escrever "Não há versão anterior para voltar."
exit 1
