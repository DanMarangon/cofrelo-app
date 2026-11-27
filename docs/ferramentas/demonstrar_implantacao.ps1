<#
.SYNOPSIS
    Demonstrações de implantação do Cofrelo para as evidências de DevOps.
.DESCRIPTION
    Pressupõe a versão 1.0.0 no ar (.\scripts\implantar.ps1 -Versao 1.0.0) e as releases 1.0.0 e 1.1.0 publicadas.
      2) versão 1.1.0 quebrada (-SimularFalha): o /health não fica saudável e o script volta sozinho para a 1.0.0
      3) atualização normal para a 1.1.0
      4) rollback manual (-Voltar) para a 1.0.0 e volta para a 1.1.0
    Os logs vão para docs\evidencias\implantacao.
#>
$ErrorActionPreference = 'Stop'
$Raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $Raiz
$Pasta = Join-Path $Raiz 'docs\evidencias\implantacao'
New-Item -ItemType Directory -Force $Pasta | Out-Null
$Utf8 = New-Object Text.UTF8Encoding $false

function Rodar([string]$Arquivo, [hashtable[]]$Passos) {
    $linhas = @()
    foreach ($passo in $Passos) {
        $texto = ($passo.GetEnumerator() | ForEach-Object { if ($_.Value -is [bool]) { "-$($_.Key)" } else { "-$($_.Key) $($_.Value)" } }) -join ' '
        $linhas += "PS> .\scripts\implantar.ps1 $texto"
        $linhas += "# $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')"
        $saida = & .\scripts\implantar.ps1 @passo *>&1 | ForEach-Object { "$_" }
        $linhas += $saida
        $linhas += "# código de saída: $LASTEXITCODE"
        $linhas += ''
        $saida | ForEach-Object { Write-Host $_ }
    }
    [IO.File]::WriteAllLines((Join-Path $Pasta $Arquivo), [string[]]$linhas, $Utf8)
}

Rodar '02-falha-e-rollback-automatico.txt' @(@{ Versao = '1.1.0'; SimularFalha = $true })
Rodar '03-atualizacao-1.1.0.txt' @(@{ Versao = '1.1.0' })
Rodar '04-rollback-manual.txt' @(@{ Voltar = $true }, @{ Situacao = $true }, @{ Versao = '1.1.0' }, @{ Situacao = $true })
Copy-Item (Join-Path $Raiz '.deploy\historico.log') (Join-Path $Pasta 'historico-de-implantacoes.txt') -Force
Write-Host "Logs salvos em $Pasta"
