<#
.SYNOPSIS
    Implanta uma versão do Cofrelo com Docker Compose, confere a saúde e volta para a versão anterior se falhar.

.DESCRIPTION
    Estratégia recreate: o container da versão atual é trocado pelo da versão nova. Depois da troca, o script
    consulta o /health por até 60 segundos. Se a versão nova não responder "ok", ele sobe de novo a versão
    anterior (rollback automático). A imagem vem do GitHub Container Registry; sem acesso ao registro, ela é
    baixada da release do GitHub (o arquivo cofrelo-imagem-<versão>.tar.gz) com o GitHub CLI.

.EXAMPLE
    .\scripts\implantar.ps1 -Versao 1.0.0
    .\scripts\implantar.ps1 -Versao 1.1.0 -SimularFalha    # testa o rollback automático
    .\scripts\implantar.ps1 -Voltar                         # volta para a versão anterior
    .\scripts\implantar.ps1 -Situacao                       # mostra a versão em execução
#>
param(
    [string]$Versao,
    [switch]$SimularFalha,
    [switch]$Voltar,
    [switch]$Situacao,
    [int]$Porta = 8080,
    [int]$EsperaSegundos = 60
)

$ErrorActionPreference = 'Stop'
$Raiz = Split-Path -Parent $PSScriptRoot
Set-Location $Raiz

$Imagem = 'ghcr.io/danmarangon/cofrelo-app'
$Repositorio = 'DanMarangon/cofrelo-app'
$PastaEstado = Join-Path $Raiz '.deploy'
$ArquivoAtual = Join-Path $PastaEstado 'versao-atual.txt'
$ArquivoAnterior = Join-Path $PastaEstado 'versao-anterior.txt'
$Historico = Join-Path $PastaEstado 'historico.log'
New-Item -ItemType Directory -Force $PastaEstado | Out-Null

function Escrever([string]$Texto) {
    Write-Host ('[{0}] {1}' -f (Get-Date -Format 'HH:mm:ss'), $Texto)
}

function Registrar([string]$Texto) {
    Add-Content -Path $Historico -Value ('{0} {1}' -f (Get-Date -Format 's'), $Texto) -Encoding UTF8
}

# Roda um programa externo (docker, gh) sem que as mensagens dele no stderr interrompam o script:
# com a saída capturada, o PowerShell 5.1 transforma esse stderr em erro. Quem decide é o $LASTEXITCODE.
function Executar([scriptblock]$Comando) {
    $anterior = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Comando 2>&1 | ForEach-Object { "$_" } } finally { $ErrorActionPreference = $anterior }
}

function Ler-Versao([string]$Arquivo) {
    if (Test-Path $Arquivo) { return (Get-Content $Arquivo -Raw).Trim() }
    return $null
}

function Consultar-Saude {
    try {
        return Invoke-RestMethod -Uri "http://localhost:$Porta/health" -TimeoutSec 3
    } catch {
        return $null
    }
}

function Garantir-Imagem([string]$VersaoDesejada) {
    $referencia = "${Imagem}:$VersaoDesejada"
    if (Executar { docker images -q $referencia }) {
        Escrever "A imagem $referencia já está neste computador."
        return
    }
    Escrever "Baixando $referencia do GitHub Container Registry..."
    $null = Executar { docker pull $referencia }
    if ($LASTEXITCODE -eq 0) { return }

    Escrever "Sem acesso ao registro. Baixando a imagem anexada à release v$VersaoDesejada (GitHub CLI)..."
    $pasta = Join-Path $PastaEstado "release-$VersaoDesejada"
    New-Item -ItemType Directory -Force $pasta | Out-Null
    $null = Executar { gh release download "v$VersaoDesejada" --repo $Repositorio --pattern "cofrelo-imagem-$VersaoDesejada.tar.gz" --pattern "imagem-$VersaoDesejada.txt" --dir $pasta --clobber }
    if ($LASTEXITCODE -ne 0) { throw "Não foi possível obter a imagem da versão $VersaoDesejada." }
    $null = Executar { docker load -i (Join-Path $pasta "cofrelo-imagem-$VersaoDesejada.tar.gz") }
    if ($LASTEXITCODE -ne 0) { throw "O Docker não conseguiu carregar a imagem da versão $VersaoDesejada." }

    # Confere se a imagem carregada é a mesma que o pipeline publicou (rastreabilidade).
    $esperado = (Select-String -Path (Join-Path $pasta "imagem-$VersaoDesejada.txt") -Pattern '^id=(.+)$').Matches.Groups[1].Value
    $carregado = Executar { docker image inspect --format '{{.Id}}' $referencia }
    if ($esperado -and $esperado -ne $carregado) { throw "O ID da imagem carregada ($carregado) é diferente do publicado ($esperado)." }
    Escrever "Imagem conferida: $carregado"
}

function Subir([string]$VersaoDesejada, [bool]$Falhar) {
    $env:COFRELO_VERSAO = $VersaoDesejada
    $env:SIMULAR_FALHA = if ($Falhar) { 'true' } else { 'false' }
    Executar { docker compose up -d --no-build --force-recreate app } | ForEach-Object { Escrever "  $_" }
    if ($LASTEXITCODE -ne 0) { throw "O Docker Compose não conseguiu subir a versão $VersaoDesejada." }
}

function Esperar-Saude([string]$VersaoEsperada) {
    $limite = (Get-Date).AddSeconds($EsperaSegundos)
    while ((Get-Date) -lt $limite) {
        $saude = Consultar-Saude
        if ($saude -and $saude.status -eq 'ok' -and $saude.versao -eq $VersaoEsperada) { return $saude }
        Start-Sleep -Seconds 2
    }
    return $null
}

if ($Situacao) {
    $saude = Consultar-Saude
    Escrever ("Versão registrada: {0} (anterior: {1})" -f (Ler-Versao $ArquivoAtual), (Ler-Versao $ArquivoAnterior))
    if ($saude) { Escrever "Health: status=$($saude.status), versão=$($saude.versao), commit=$($saude.commit), banco=$($saude.banco)" }
    else { Escrever 'O /health não respondeu.' }
    exit 0
}

if ($Voltar) {
    $Versao = Ler-Versao $ArquivoAnterior
    if (-not $Versao) { throw 'Não há versão anterior registrada para voltar.' }
    Escrever "Rollback manual pedido: voltando para a versão $Versao."
}

if (-not $Versao) { throw 'Informe a versão. Exemplo: .\scripts\implantar.ps1 -Versao 1.0.0' }
$Versao = $Versao.TrimStart('v')
$atual = Ler-Versao $ArquivoAtual

Escrever ("Versão em execução: {0}. Versão pedida: {1}." -f ($(if ($atual) { $atual } else { 'nenhuma' })), $Versao)
Garantir-Imagem $Versao
Escrever "Trocando o container pela versão $Versao (estratégia recreate)..."
Subir $Versao $SimularFalha.IsPresent

$saude = Esperar-Saude $Versao
if ($saude) {
    if ($atual -and $atual -ne $Versao) { Set-Content -Path $ArquivoAnterior -Value $atual -Encoding ASCII }
    Set-Content -Path $ArquivoAtual -Value $Versao -Encoding ASCII
    Registrar "IMPLANTADA $Versao (antes: $atual)"
    Escrever "Implantação concluída: versão $($saude.versao), commit $($saude.commit), banco $($saude.banco)."
    exit 0
}

Escrever "A versão $Versao não ficou saudável em $EsperaSegundos segundos. Últimas linhas do log:"
Executar { docker compose logs app --tail 12 } | ForEach-Object { Write-Host "  $_" }

if ($atual -and $atual -ne $Versao) {
    Escrever "ROLLBACK: voltando para a versão $atual..."
    Subir $atual $false
    $volta = Esperar-Saude $atual
    if ($volta) {
        Registrar "ROLLBACK $Versao -> $atual"
        Escrever "ROLLBACK OK: a versão $atual voltou a atender (health ok, commit $($volta.commit))."
        exit 1
    }
    Registrar "ROLLBACK FALHOU $Versao -> $atual"
    Escrever 'ROLLBACK FALHOU: confira os containers com "docker compose ps" e os logs.'
    exit 2
}

Registrar "FALHOU $Versao (sem versão anterior)"
Escrever 'Não há versão anterior para voltar. Confira os logs acima.'
exit 1
