"""Gera os wireframes (rascunho de baixa fidelidade) de todas as páginas do Cofrelo.

Uso: python docs/ferramentas/wireframes.py [pasta de saída]   (padrão: docs/wireframes)
Cada página vira um PNG em tons de cinza, sem cores nem dados reais: só a estrutura.
"""

from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from navegador import Navegador  # noqa: E402

SAIDA = Path(sys.argv[1] if len(sys.argv) > 1 else Path(__file__).resolve().parents[1] / "evidencias" / "wireframes")

CSS = """
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; color: #555; background: #fff; font-size: 13px; }
.tela { width: 1280px; min-height: 800px; display: grid; grid-template-columns: 210px 1fr; border: 2px solid #888; }
.lateral { border-right: 2px solid #888; padding: 22px 16px; display: flex; flex-direction: column; gap: 10px; }
.logo { height: 34px; border: 2px dashed #888; display: grid; place-items: center; font-weight: 700; margin-bottom: 14px; }
.item { height: 30px; border: 1.5px solid #aaa; border-radius: 6px; display: flex; align-items: center; padding: 0 10px; gap: 8px; }
.item.ativo { background: #e6e6e6; border-color: #777; font-weight: 700; }
.item::before { content: ''; width: 14px; height: 14px; border: 1.5px solid #999; border-radius: 3px; }
.rotulo-menu { font-size: 11px; font-weight: 700; letter-spacing: .06em; margin-top: 8px; }
.area { display: flex; flex-direction: column; }
.topo { height: 64px; border-bottom: 2px solid #888; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; }
.topo .titulo { font-size: 18px; font-weight: 700; }
.topo .direita { display: flex; gap: 14px; align-items: center; }
.circulo { width: 30px; height: 30px; border: 1.5px solid #888; border-radius: 50%; display: grid; place-items: center; font-size: 11px; }
.conteudo { padding: 22px 24px; display: flex; flex-direction: column; gap: 16px; }
.cabecalho { display: flex; justify-content: space-between; align-items: flex-end; }
.h1 { font-size: 22px; font-weight: 700; color: #444; }
.sub { width: 330px; height: 10px; background: #ddd; border-radius: 4px; margin-top: 8px; }
.botoes { display: flex; gap: 10px; }
.botao { height: 36px; padding: 0 16px; border: 1.5px solid #777; border-radius: 6px; display: grid; place-items: center; font-weight: 600; }
.botao.principal { background: #cfcfcf; }
.grade { display: grid; gap: 14px; }
.g2 { grid-template-columns: 1.6fr 1fr; } .g3 { grid-template-columns: repeat(3, 1fr); } .g4 { grid-template-columns: repeat(4, 1fr); }
.cartao { border: 1.5px solid #999; border-radius: 10px; padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.cartao.destaque { background: #ececec; }
.titulo-cartao { font-weight: 700; color: #444; }
.linha { height: 9px; background: #ddd; border-radius: 4px; }
.valor { height: 22px; width: 60%; background: #c9c9c9; border-radius: 4px; }
.campo { height: 34px; border: 1.5px solid #aaa; border-radius: 6px; display: flex; align-items: center; padding: 0 10px; color: #888; }
.barras { height: 170px; display: flex; align-items: flex-end; gap: 18px; padding: 0 10px; border-bottom: 1.5px solid #999; }
.barras div { flex: 1; display: flex; gap: 5px; align-items: flex-end; height: 100%; }
.barras i { flex: 1; background: #bbb; border-radius: 3px 3px 0 0; }
.barras i:nth-child(2) { background: #dcdcdc; }
.rosca { width: 130px; height: 130px; border: 22px solid #c4c4c4; border-radius: 50%; margin: 6px auto; display: grid; place-items: center; font-size: 11px; }
.tabela { border: 1.5px solid #aaa; border-radius: 6px; overflow: hidden; }
.tabela div { height: 34px; border-bottom: 1px solid #ccc; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 12px; align-items: center; padding: 0 10px; }
.tabela div:first-child { background: #eee; font-weight: 700; }
.tabela span { height: 8px; background: #ddd; border-radius: 4px; }
.progresso { height: 8px; border: 1px solid #999; border-radius: 4px; }
.progresso i { display: block; height: 100%; width: 60%; background: #bbb; }
.abas { display: flex; gap: 6px; } .abas span { padding: 6px 14px; border: 1.5px solid #aaa; border-radius: 6px; }
.abas span:first-child { background: #ddd; }
.nota { font-size: 11px; color: #777; font-style: italic; }
.mapa { display: grid; grid-template-columns: repeat(53, 1fr); gap: 3px; }
.mapa i { aspect-ratio: 1; background: #ddd; border-radius: 2px; }
.mapa i:nth-child(3n) { background: #bbb; } .mapa i:nth-child(7n) { background: #999; }
.acesso { width: 1280px; height: 800px; display: grid; grid-template-columns: 1fr 1fr; border: 2px solid #888; }
.acesso .marca { background: #e9e9e9; border-right: 2px solid #888; padding: 40px; display: flex; flex-direction: column; justify-content: space-between; }
.acesso .form { display: grid; place-items: center; }
.acesso .caixa { width: 380px; display: flex; flex-direction: column; gap: 12px; }
.fundo-modal { position: relative; }
.fundo-modal::after { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,.18); }
.modal { position: absolute; top: 60px; left: 50%; transform: translateX(-50%); width: 520px; background: #fff; border: 2px solid #666;
         border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 10px; z-index: 2; }
.duas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
"""

MENU = ["Visão geral", "Transações", "Minhas contas", "Relatórios", "Metas e objetivos"]


def lateral(ativo: str) -> str:
    itens = "".join(f'<div class="item{" ativo" if nome == ativo else ""}">{nome}</div>' for nome in MENU)
    preferencias = "".join(f'<div class="item{" ativo" if nome == ativo else ""}">{nome}</div>' for nome in ["Configurações", "Central de ajuda"])
    return (f'<aside class="lateral"><div class="logo">LOGO cofrelo</div><div class="rotulo-menu">PRINCIPAL</div>{itens}'
            f'<div class="rotulo-menu">PREFERÊNCIAS</div>{preferencias}</aside>')


def topo(titulo: str) -> str:
    return (f'<header class="topo"><div><div class="nota">Meu espaço</div><div class="titulo">{titulo}</div></div>'
            f'<div class="direita"><div class="circulo">sino</div><div>Olá, [nome] ▾</div></div></header>')


def tela(ativo: str, titulo: str, conteudo: str) -> str:
    return f'<div class="tela">{lateral(ativo)}<div class="area">{topo(titulo)}<main class="conteudo">{conteudo}</main></div></div>'


def cabecalho(h1: str, botoes: list[str]) -> str:
    html_botoes = "".join(f'<div class="botao{" principal" if b.startswith("+") else ""}">{b}</div>' for b in botoes)
    return f'<div class="cabecalho"><div><div class="h1">{h1}</div><div class="sub"></div></div><div class="botoes">{html_botoes}</div></div>'


def indicador(titulo: str, destaque: bool = False) -> str:
    return f'<div class="cartao{" destaque" if destaque else ""}"><div>{titulo}</div><div class="valor"></div><div class="linha" style="width:70%"></div></div>'


def barras(meses: int = 6) -> str:
    alturas = [(62, 41), (73, 49), (69, 46), (81, 41), (76, 40), (85, 39), (70, 44), (66, 48)]
    grupos = "".join(f'<div><i style="height:{a}%"></i><i style="height:{b}%"></i></div>' for a, b in alturas[:meses])
    return f'<div class="barras">{grupos}</div>'


def tabela(colunas: list[str], linhas: int) -> str:
    cabeca = "".join(f"<b>{c}</b>" for c in colunas)
    corpo = "".join("<div>" + "<span></span>" * len(colunas) + "</div>" for _ in range(linhas))
    estilo = f'style="grid-template-columns: 2fr {" ".join(["1fr"] * (len(colunas) - 1))}"'
    return f'<div class="tabela"><div {estilo}>{cabeca}</div>' + corpo.replace("<div>", f"<div {estilo}>") + "</div>"


def acesso(titulo: str, campos: list[str], extra: str = "") -> str:
    html_campos = "".join(f'<div class="campo">{c}</div>' for c in campos)
    return (f'<div class="acesso"><div class="marca"><div class="logo" style="width:160px">LOGO cofrelo</div>'
            f'<div><div class="h1">Frase principal da marca</div><div class="sub"></div><div class="sub" style="width:260px"></div></div>'
            f'<div class="grade g3"><div class="cartao">Destaque 1</div><div class="cartao">Destaque 2</div><div class="cartao">Destaque 3</div></div></div>'
            f'<div class="form"><div class="caixa"><div class="h1">{titulo}</div><div class="sub" style="width:200px"></div>'
            f'{html_campos}<div class="botao principal">{titulo.split()[0]}</div>{extra}</div></div></div>')


def visao_geral() -> str:
    return tela("Visão geral", "Visão geral",
                cabecalho("Seu dinheiro, com mais clareza.", ["[calendário] Mês", "+ Nova transação"])
                + '<div class="grade g3">' + indicador("Saldo total", True) + indicador("Receitas do mês") + indicador("Despesas do mês") + "</div>"
                + '<div class="grade g2"><div class="cartao"><div class="titulo-cartao">Fluxo de caixa (6 meses)</div>' + barras()
                + '</div><div class="cartao"><div class="titulo-cartao">Onde você mais gastou</div><div class="rosca">Total</div>'
                + '<div class="linha"></div><div class="linha"></div><div class="nota">Orçamento usado</div><div class="progresso"><i></i></div></div></div>'
                + '<div class="grade g2"><div class="cartao"><div class="titulo-cartao">Últimas transações</div>'
                + tabela(["Descrição", "Categoria", "Data", "Valor"], 4)
                + '</div><div class="cartao destaque"><div class="titulo-cartao">Meta em destaque</div><div class="linha"></div>'
                + '<div class="progresso"><i></i></div><div class="valor"></div><div class="botao">Adicionar à meta</div></div></div>')


def transacoes() -> str:
    return tela("Transações", "Transações",
                cabecalho("Cada movimento, no seu controle.", ["+ Nova transação"])
                + '<div class="grade g3">' + indicador("Receitas do período") + indicador("Despesas do período") + indicador("Balanço", True) + "</div>"
                + '<div class="cartao"><div class="cabecalho"><div class="titulo-cartao">Histórico de transações</div><div class="botao">Exportar CSV</div></div>'
                + '<div class="grade g4"><div class="campo">Buscar por descrição ou valor</div><div class="campo">Categoria ▾</div>'
                + '<div class="campo">Conta ▾</div><div class="campo">[calendário] Período</div></div>'
                + '<div class="abas"><span>Todas</span><span>Receitas</span><span>Despesas</span><span>Transferências</span><span>Assinaturas</span></div>'
                + tabela(["Descrição", "Categoria", "Conta", "Data", "Status", "Valor"], 8)
                + '<div class="cabecalho"><div class="nota">Mostrando 1–8 de N</div><div class="abas"><span>1</span><span>2</span><span>3</span></div></div></div>')


def nova_transacao() -> str:
    modal = ('<div class="modal"><div class="h1">Nova transação</div><div class="sub" style="width:240px"></div>'
             '<div class="abas"><span>Despesa</span><span>Receita</span><span>Transferência</span><span>Assinatura</span></div>'
             '<div class="campo" style="height:56px;font-size:20px">R$ 0,00</div><div class="campo">Descrição</div>'
             '<div class="duas"><div class="campo">Categoria ▾</div><div class="campo">Conta ▾</div></div>'
             '<div class="duas"><div class="campo">Data [calendário]</div><div class="campo">Forma de pagamento ▾</div></div>'
             '<div class="abas"><span>Pago</span><span>Pendente</span></div>'
             '<div class="cartao"><b>Assinatura</b><div class="duas"><div class="campo">Dia da cobrança ▾</div><div class="campo">Avisar antes ▾</div></div></div>'
             '<div class="campo">Observação (opcional)</div><div class="botoes" style="justify-content:flex-end"><div class="botao">Cancelar</div>'
             '<div class="botao principal">Salvar transação</div></div></div>')
    return f'<div class="fundo-modal">{transacoes()}</div>{modal}'


def contas() -> str:
    conta = '<div class="cartao"><div class="circulo">ic</div><div class="titulo-cartao">Nome da conta</div><div class="linha" style="width:50%"></div><div class="valor"></div><div class="nota">% do total · Ver detalhes →</div></div>'
    return tela("Minhas contas", "Minhas contas",
                cabecalho("Todas as suas contas, juntas.", ["+ Adicionar conta"])
                + '<div class="cartao destaque" style="height:110px"><div>PATRIMÔNIO DISPONÍVEL</div><div class="valor" style="width:30%"></div><div class="linha" style="width:20%"></div></div>'
                + f'<div class="titulo-cartao">Minhas contas</div><div class="grade g3">{conta * 3}</div>'
                + '<div class="cartao"><div class="titulo-cartao">Movimentações entre contas</div>' + tabela(["Origem → destino", "Data", "Valor"], 3) + "</div>")


def metas() -> str:
    meta = ('<div class="cartao"><div class="circulo">ic</div><div class="nota">SITUAÇÃO</div><div class="titulo-cartao">Nome da meta</div>'
            '<div class="linha" style="width:70%"></div><div class="valor" style="width:30%"></div><div class="progresso"><i></i></div>'
            '<div class="linha"></div><div class="botao">Guardar dinheiro</div></div>')
    simulador = ('<div class="cartao"><div class="titulo-cartao">Simulador de metas (campos calculados)</div><div class="grade g2">'
                 '<div><div class="duas"><div class="campo">Quanto quero juntar</div><div class="campo">Já tenho</div></div><br>'
                 '<div class="duas"><div class="campo">Em quantos meses</div><div class="campo">Rendimento ao mês</div></div><br>'
                 '<div class="campo">Quanto consigo guardar por mês</div></div>'
                 '<div><div class="cartao destaque">Guarde por mês: R$ —</div><div class="duas"><div class="cartao">Saldo final</div>'
                 '<div class="cartao">Rendimento</div><div class="cartao">Total guardado</div><div class="cartao">Meses</div></div>'
                 '<div class="barras" style="height:80px"></div></div></div></div>')
    return tela("Metas e objetivos", "Metas e objetivos",
                cabecalho("Dê um destino aos seus sonhos.", ["+ Criar nova meta"])
                + '<div class="grade g3">' + indicador("Total reservado", True) + indicador("Metas ativas") + indicador("Próxima conquista") + "</div>"
                + f'<div class="cabecalho"><div class="titulo-cartao">Meus objetivos</div><div class="abas"><span>Ativas</span><span>Concluídas</span></div></div>'
                + f'<div class="grade g3">{meta * 3}</div>' + simulador)


def relatorios() -> str:
    return tela("Relatórios", "Relatórios",
                cabecalho("Entenda seus números. Planeje melhor.", ["[calendário] Período", "+ Exportar relatório"])
                + '<div class="grade g4">' + indicador("Receitas") + indicador("Despesas") + indicador("Resultado") + indicador("Taxa de economia", True) + "</div>"
                + '<div class="cartao"><div class="titulo-cartao">Evolução das receitas e despesas</div>' + barras(6) + "</div>"
                + '<div class="cartao"><div class="titulo-cartao">Despesas por categoria</div><div class="grade g2">'
                + tabela(["Categoria", "Mês anterior", "Mês atual", "Part.", "Variação"], 5) + '<div class="rosca">Total</div></div></div>'
                + '<div class="cartao"><div class="titulo-cartao">Seu ano em gastos (calendário estilo GitHub)</div><div class="mapa">'
                + "<i></i>" * (53 * 7) + '</div><div class="nota">Menos ▢▢▢▢ Mais</div></div>')


def configuracoes() -> str:
    def secao(titulo, campos):
        html = "".join(f'<div class="campo">{c}</div>' for c in campos)
        return (f'<div class="grade" style="grid-template-columns:230px 1fr;padding:14px 0;border-bottom:1px solid #ccc">'
                f'<div><div class="titulo-cartao">{titulo}</div><div class="linha"></div></div><div class="grade" style="max-width:460px">{html}'
                f'<div class="botao principal" style="width:160px">Salvar</div></div></div>')
    return tela("Configurações", "Configurações",
                cabecalho("Do seu jeito.", [])
                + '<div class="cartao">' + secao("Perfil e orçamento", ["Nome", "E-mail", "Orçamento mensal (R$)"])
                + secao("Senha", ["Senha atual", "Nova senha", "Repita a nova senha"])
                + secao("Excluir conta", ["Digite EXCLUIR", "Sua senha"]) + "</div>")


def ajuda() -> str:
    perguntas = "".join('<div class="campo" style="justify-content:space-between">Pergunta frequente <b>▾</b></div>' for _ in range(7))
    return tela("Central de ajuda", "Central de ajuda",
                cabecalho("Como podemos ajudar?", [])
                + f'<div class="grade g2"><div class="cartao"><div class="titulo-cartao">Perguntas frequentes</div><div class="campo">Buscar nas perguntas</div>{perguntas}</div>'
                + '<div class="grade"><div class="cartao"><div class="titulo-cartao">Atalhos de teclado</div><div class="linha"></div><div class="linha"></div></div>'
                + '<div class="cartao"><div class="titulo-cartao">Sobre o Cofrelo</div><div class="linha"></div><div class="linha"></div></div></div></div>')


PAGINAS = {
    "01-entrar": acesso("Entrar no Cofrelo", ["E-mail", "Senha"], '<div class="nota">Criar conta · Conta de demonstração</div>'),
    "02-criar-conta": acesso("Criar sua conta", ["Nome", "E-mail", "Senha", "Repita a senha"], '<div class="progresso"><i></i></div>'),
    "03-visao-geral": visao_geral(),
    "04-transacoes": transacoes(),
    "05-nova-transacao": nova_transacao(),
    "06-minhas-contas": contas(),
    "07-metas-e-objetivos": metas(),
    "08-relatorios": relatorios(),
    "09-configuracoes": configuracoes(),
    "10-central-de-ajuda": ajuda(),
}


def gerar() -> list[Path]:
    SAIDA.mkdir(parents=True, exist_ok=True)
    arquivos = []
    with Navegador(1284, 804, escala=1.5) as nav:
        for nome, corpo in PAGINAS.items():
            pagina = SAIDA / f"_{nome}.html"
            pagina.write_text(f"<!doctype html><html lang='pt-BR'><head><meta charset='utf-8'><style>{CSS}</style></head>"
                              f"<body style='position:relative'>{corpo}</body></html>", encoding="utf-8")
            nav.abrir(pagina.as_uri(), pausa=0.2)
            arquivos.append(nav.capturar(SAIDA / f"{nome}.png"))
            pagina.unlink()
    return arquivos


if __name__ == "__main__":
    for arquivo in gerar():
        print(arquivo)
