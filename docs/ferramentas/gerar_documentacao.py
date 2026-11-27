"""Gera a documentação do sistema (padrão ABNT) pedida em Desenvolvimento Web, em Word e PDF.

Segue o modelo "Documentação do Sistema" da Aula 16 (introdução com o briefing, quadros de requisitos funcionais,
não funcionais e regras de negócio, diagramas de caso de uso e de fluxo, wireframes e protótipo) e acrescenta os
itens 9 a 11 do enunciado: páginas em HTML e CSS, uso do JavaScript e o sistema em PHP com CRUD e banco de dados.

Uso: python docs/ferramentas/gerar_documentacao.py
Saída: docs/entregas/Documentação - Cofrelo - DW Grupo 2.docx e .pdf
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import conteudo as c  # noqa: E402
from abnt import DocumentoAbnt, word_para_pdf  # noqa: E402

TELAS = c.EVIDENCIAS / "telas"
DIAGRAMAS = c.EVIDENCIAS / "diagramas"
WIREFRAMES = c.EVIDENCIAS / "wireframes"


def trecho(caminho: str, inicio: str, maximo: int = 40) -> str:
    """Função inteira a partir da linha que contém `inicio` (conta as chaves até fechar)."""
    linhas = (c.RAIZ / caminho).read_text(encoding="utf-8").splitlines()
    i = next(n for n, linha in enumerate(linhas) if inicio in linha)
    while i > 0 and linhas[i - 1].strip().startswith(("//", "/**", "*", "#[")):
        i -= 1  # inclui o comentário logo acima
    saida, nivel, abriu = [], 0, False
    for linha in linhas[i:i + maximo]:
        saida.append(linha)
        sem_texto = re.sub(r"'[^']*'|\"[^\"]*\"", "", linha)
        nivel += sem_texto.count("{") - sem_texto.count("}")
        abriu = abriu or "{" in sem_texto
        if abriu and nivel <= 0:
            break
    recuo = min(len(l) - len(l.lstrip()) for l in saida if l.strip())
    return "\n".join(l[recuo:] for l in saida)


def gerar() -> list[Path]:
    p = c.projeto()
    dw = p["dw"]
    integrantes = c.integrantes_dw()
    nomes = [i["nome"] for i in integrantes]
    autores_quadro = ", ".join(nomes)
    doc = DocumentoAbnt()

    # ------------------------------------------------------------ pré-textuais
    doc.capa(p["instituicao"], p["curso"], nomes, f"{p['projeto']}: documentação do sistema", p["subtitulo"], p["cidade"], p["ano"])
    doc.nova_secao(numerar=False, reiniciar_em=1)
    natureza = (f"Documentação do sistema apresentada à disciplina de {dw['disciplina']} ({dw['turma']}) do curso de "
                f"{p['curso']} do {p['instituicao']}, como parte do projeto final (Atividade Prática Bimestral) do "
                f"{dw['grupo']}. Professor: {dw['professor']}.")
    doc.folha_de_rosto(nomes, f"{p['projeto']}: documentação do sistema", p["subtitulo"], natureza, p["cidade"], p["ano"])

    doc.quebra_de_pagina()
    doc.titulo_sem_numero("Resumo")
    doc.paragrafo(
        f"Este documento descreve o {p['projeto']}, um sistema web de controle financeiro pessoal desenvolvido pelo "
        f"{dw['grupo']} para uma empresa cliente fictícia. A partir de um briefing, foram levantados os requisitos "
        "funcionais, os não funcionais e as regras de negócio, modelados os diagramas de caso de uso e de fluxo, "
        "desenhados os wireframes e o protótipo no Figma e implementadas as páginas em HTML, CSS e JavaScript, com "
        "um back-end em PHP que faz o CRUD no banco de dados MySQL e oferece uma API REST. O sistema roda em "
        "contêineres Docker, tem testes automatizados e atende às diretrizes de acessibilidade WCAG 2.2 AA.",
        recuo=False)
    doc.paragrafo("**Palavras-chave:** finanças pessoais; desenvolvimento web; PHP; MySQL; JavaScript.", recuo=False)

    doc.quebra_de_pagina()
    doc.lista_de("Lista de figuras", "Figura")
    doc.quebra_de_pagina()
    doc.lista_de("Lista de quadros", "Quadro")
    doc.quebra_de_pagina()
    doc.sumario()

    # ------------------------------------------------------------ 1 introdução
    doc.nova_secao(numerar=True)
    doc.titulo("1 Introdução")
    doc.paragrafo(c.DESCRICAO_CURTA + " " + c.PROBLEMA)
    cliente = p["cliente_ficticio"]
    doc.paragrafo(
        f"Os requisitos foram levantados com um briefing aplicado à {cliente['empresa']}, empresa fictícia criada pelo "
        "grupo para simular um cliente real, seguindo as 18 perguntas do Briefing Modelo da disciplina. O briefing "
        "mostrou uma fintech nova, sem site, que quer atender jovens adultos de 18 a 35 anos (estudantes e "
        "profissionais em início de carreira) que hoje não sabem para onde vai o próprio dinheiro. Os objetivos "
        "definidos foram: mostrar com clareza o que entra e o que sai, controlar as assinaturas e criar o hábito de "
        "guardar para metas.")
    doc.paragrafo(
        "Também ficaram definidos a imagem e as restrições do produto: visual moderno, limpo e calmo, em verde com "
        "destaque em verde-lima, fonte Inter e o slogan \"Finanças em equilíbrio\"; nada de vermelho como cor "
        "principal, de telas poluídas ou de anúncios; tecnologias HTML, CSS, JavaScript, PHP e MySQL, com execução em "
        "Docker; e entrega até 30/11/2026, com apresentação em 08/12/2026.")
    link = f"{p['repositorio']}/blob/main/docs/entregas/Briefing%20-%20Cofrelo.pdf"
    doc.paragrafo(f"O briefing completo está no Apêndice A e no repositório do projeto: {link}.")
    doc.paragrafo("Objetivo do sistema: " + c.OBJETIVO)

    doc.titulo("1.1 Funcionalidades principais", 2)
    doc.lista([f"**{nome}:** {descricao};" for nome, descricao in c.FUNCIONALIDADES[:-1]]
              + [f"**{c.FUNCIONALIDADES[-1][0]}:** {c.FUNCIONALIDADES[-1][1]}."])

    doc.titulo("1.2 Tecnologias", 2)
    doc.paragrafo("O Quadro 1 resume as tecnologias e ferramentas usadas no projeto.")
    doc.quadro("Tecnologias e ferramentas", ["Camada", "Tecnologia"], [list(t) for t in c.TECNOLOGIAS], [4.0, 12.0])

    doc.titulo("1.3 Organização do documento", 2)
    doc.paragrafo(
        "A seção 2 apresenta os requisitos e as regras de negócio. A seção 3 traz a modelagem: diagramas de caso de "
        "uso e de fluxo, wireframes, protótipo e modelo do banco de dados. A seção 4 mostra a implementação das "
        "páginas, do JavaScript e do sistema em PHP com banco de dados. A seção 5 explica como executar o sistema e a "
        "seção 6 traz as considerações finais.")

    # ------------------------------------------------------------ 2 requisitos
    doc.titulo("2 Requisitos")
    doc.paragrafo(
        "Os requisitos seguem o modelo de documentação da disciplina: cada quadro traz os autores, a revisão e a data "
        "de atualização, e cada item tem nome, código e descrição. Os requisitos funcionais são o que o usuário faz no "
        "sistema; os não funcionais, as qualidades e o ambiente de que o sistema precisa; e as regras de negócio, o "
        "que o cliente exige que seja respeitado.")
    data = c.data_por_extenso()
    doc.titulo("2.1 Requisitos funcionais", 2)
    doc.quadro_requisitos("Requisitos funcionais", c.REQUISITOS_FUNCIONAIS, autores_quadro, "Versão 1.1", data, "Requisitos funcionais")
    doc.titulo("2.2 Requisitos não funcionais", 2)
    doc.quadro_requisitos("Requisitos não funcionais", c.REQUISITOS_NAO_FUNCIONAIS, autores_quadro, "Versão 1.1", data, "Requisitos não funcionais")
    doc.titulo("2.3 Regras de negócio", 2)
    doc.quadro_requisitos("Regras de negócio", c.REGRAS_DE_NEGOCIO, autores_quadro, "Versão 1.1", data, "Regras de negócio")

    # ------------------------------------------------------------ 3 modelagem
    doc.titulo("3 Modelagem do sistema")
    doc.titulo("3.1 Diagrama de caso de uso", 2)
    doc.paragrafo(
        "O diagrama de caso de uso (Figura 1) mostra como o usuário interage com o sistema. Cada elipse é uma "
        "funcionalidade. O visitante só pode se cadastrar e entrar; depois do login, o usuário acessa as demais "
        "funcionalidades. As relações de inclusão marcam o que sempre acontece junto, como o login antes de qualquer "
        "operação, e as de extensão marcam o que é opcional, como transformar uma despesa em assinatura.")
    doc.figura(DIAGRAMAS / "caso-de-uso.png", "Diagrama de caso de uso do Cofrelo")
    doc.titulo("3.2 Diagrama de fluxo do sistema", 2)
    doc.paragrafo(
        "O diagrama de fluxo (Figura 2) mostra o caminho do usuário desde a entrada no sistema: cadastro ou login, a "
        "visão geral, a escolha de uma funcionalidade no menu, as validações que o sistema faz antes de gravar e a "
        "decisão de continuar usando ou sair.")
    doc.figura(DIAGRAMAS / "fluxo-do-sistema.png", "Diagrama de fluxo do sistema", altura_max_cm=17)

    doc.titulo("3.3 Wireframes", 2)
    doc.paragrafo(
        "Os wireframes são o rascunho inicial de todas as páginas, em tons de cinza e sem detalhes visuais, para "
        "definir a posição de cada elemento antes do protótipo. As Figuras 3 a 12 mostram as dez telas do sistema.")
    for arquivo, titulo in c.WIREFRAMES:
        doc.figura(WIREFRAMES / arquivo, f"Wireframe: {titulo.lower()}", altura_max_cm=10.5)

    doc.titulo("3.4 Protótipo", 2)
    figma = p.get("figma", "").strip()
    texto_figma = f" O protótipo pode ser aberto em: {figma}." if figma else ""
    doc.paragrafo(
        "O protótipo foi feito no Figma e mostra o que foi realmente implementado: cores, tipografia, ícones e o "
        "layout final de cada tela (Figuras 13 a 21)." + texto_figma + " Três comentários deixados no protótipo "
        "viraram funcionalidades: um calendário para escolher o período em Transações, um mapa de gastos no estilo do "
        "calendário do GitHub em Relatórios e a opção de assinatura dentro do modal de nova transação, que substituiu "
        "a tela separada de assinaturas.")
    for arquivo, titulo in c.PROTOTIPO_TELAS:
        doc.figura(c.PROTOTIPO / arquivo, titulo, fonte="protótipo no Figma elaborado pelos autores (2026).", altura_max_cm=10.5)

    doc.titulo("3.5 Modelo do banco de dados", 2)
    doc.paragrafo(
        "O banco de dados MySQL tem sete tabelas (Figura 22). Cada usuário tem suas contas, transações, assinaturas e "
        "metas, e cada meta guarda os próprios depósitos. Os valores em dinheiro usam o tipo DECIMAL(12,2), que evita "
        "os erros de arredondamento do tipo FLOAT, e as chaves estrangeiras garantem que nenhum registro fique sem dono.")
    doc.figura(DIAGRAMAS / "modelo-do-banco.png", "Modelo entidade-relacionamento do banco de dados")

    # ------------------------------------------------------------ 4 implementação
    doc.titulo("4 Implementação")
    doc.paragrafo(
        "O sistema foi construído sem frameworks, para que cada parte do código mostre os conceitos da disciplina. As "
        "páginas são montadas em PHP (pasta views), estilizadas por uma única folha de estilos e animadas por módulos "
        "JavaScript (pasta public/assets/js) que conversam com a API REST. No servidor, o PHP segue camadas: "
        "controladores recebem a requisição, serviços aplicam as regras de negócio e repositórios fazem as consultas "
        "ao banco.")

    doc.titulo("4.1 Páginas em HTML e CSS", 2)
    doc.paragrafo(
        "Todas as telas do protótipo foram implementadas em HTML semântico (cabeçalho, menu, conteúdo principal, "
        "formulários com rótulos) e CSS com variáveis para as cores do protótipo, layout em grid e flexbox e versões "
        "para celular. As Figuras 23 a 37 mostram as páginas implementadas, com os dados da usuária de demonstração.")
    for arquivo, titulo in c.TELAS:
        altura = 17 if arquivo.startswith("15-") else 18.5
        doc.figura(TELAS / arquivo, f"Página implementada: {titulo[0].lower() + titulo[1:]}", altura_max_cm=altura)

    doc.titulo("4.2 Aplicação do JavaScript", 2)
    doc.paragrafo(
        "O enunciado pede o JavaScript aplicado em oito pontos. O Quadro 5 mostra onde cada um aparece no código; no "
        "próprio código-fonte, cada ponto está marcado com um comentário [DW 10.a] a [DW 10.h] para facilitar a "
        "explicação na apresentação.")
    doc.quadro("Uso do JavaScript no sistema (item 10 do enunciado)", ["Item", "Onde no código", "Como"],
               [[f"{letra}) {titulo}", onde, como] for letra, titulo, onde, como in c.JAVASCRIPT], [3.4, 6.6, 6.0], tamanho=9)
    doc.paragrafo("Exemplo de função com estrutura condicional e cálculo (public/assets/js/lib/calculos.js):")
    doc.codigo(trecho("public/assets/js/lib/calculos.js", "export function aporteNecessario"))
    doc.paragrafo("Exemplo de evento com espera: a busca de transações só roda quando a pessoa para de digitar "
                  "(public/assets/js/paginas/transacoes.js):")
    doc.codigo(trecho("public/assets/js/paginas/transacoes.js", "[DW 10.b]", maximo=14))

    doc.titulo("4.3 Formulário com campos calculados", 2)
    doc.paragrafo(
        "O simulador da página Metas é o formulário com campos calculados pedido no enunciado. A pessoa informa o "
        "valor objetivo, quanto já tem, o prazo em meses, o rendimento mensal e, se quiser, quanto pretende guardar "
        "por mês. A cada tecla, o JavaScript recalcula o aporte necessário, o saldo final, o rendimento, o total "
        "guardado e em quantos meses o objetivo é atingido, e desenha um gráfico da evolução. Com rendimento, o aporte "
        "usa a fórmula do valor futuro de uma série de depósitos:")
    doc.paragrafo("`aporte = (objetivo - atual × (1 + i)^n) × i / ((1 + i)^n - 1)`", alinhar="centro", recuo=False)
    doc.paragrafo("em que i é o rendimento mensal e n o número de meses. Trecho da função que lê o formulário e "
                  "escreve os resultados (public/assets/js/paginas/metas.js):")
    doc.codigo(trecho("public/assets/js/paginas/metas.js", "function recalcular()", maximo=26))
    doc.paragrafo(
        "Na versão 1.1.0, cada meta também passou a mostrar a previsão de conclusão: com a média guardada nos últimos "
        "3 meses, o sistema diz em quantos meses a meta fica pronta e em que mês (RN14).")

    doc.titulo("4.4 Sistema em PHP com CRUD e banco de dados", 2)
    doc.paragrafo(
        "O PHP faz o cadastro, a consulta, a alteração e a exclusão (CRUD) de todas as entidades, sempre no banco de "
        "dados MySQL. As mesmas operações respondem em JSON na API REST, que é usada pelas páginas e pode ser testada "
        "com curl ou Postman. O Quadro 6 mostra onde fica cada operação.")
    doc.quadro("Operações de CRUD por entidade", ["Entidade", "Cadastrar", "Consultar", "Alterar", "Excluir"],
               [list(linha) for linha in c.CRUD], [2.4, 3.6, 3.6, 3.2, 3.2], tamanho=9)
    doc.paragrafo("O controlador valida os dados com as regras de negócio antes de gravar "
                  "(src/Controlador/Api/TransacaoControlador.php):")
    doc.codigo(trecho("src/Controlador/Api/TransacaoControlador.php", "public function criar(", maximo=24))
    doc.paragrafo("O repositório grava no banco com consultas preparadas do PDO, que impedem SQL injection "
                  "(src/Repositorio/TransacaoRepositorio.php):")
    doc.codigo(trecho("src/Repositorio/TransacaoRepositorio.php", "public function criar(", maximo=30))
    doc.paragrafo("Além das consultas preparadas, o sistema protege os dados assim:")
    doc.lista([
        "senhas guardadas com `password_hash` (bcrypt) e nunca devolvidas pela API;",
        "token CSRF em toda requisição que altera dados e cookie de sessão HttpOnly e SameSite;",
        "texto sempre escapado nas páginas (`htmlspecialchars` no PHP e `textContent` no JavaScript);",
        "login bloqueado por 15 minutos depois de 5 senhas erradas;",
        "cabeçalho Content-Security-Policy, que bloqueia scripts de outros sites;",
        "cada consulta filtra pelo usuário logado, então ninguém vê os dados de outra pessoa (RN13).",
    ])

    doc.titulo("4.5 Qualidade: testes e acessibilidade", 2)
    doc.paragrafo(
        "O projeto tem 81 testes automatizados que rodam a cada alteração enviada ao GitHub: 39 testes unitários e 26 "
        "de integração com o banco de dados em PHPUnit, e 16 testes do JavaScript com o executor de testes do Node.js. "
        "O código PHP também passa pela análise estática do PHPStan. A acessibilidade foi conferida com o axe-core em "
        "todas as páginas, no computador e no celular, sem nenhuma violação das diretrizes WCAG 2.2 nível AA: "
        "contraste suficiente, nomes acessíveis nos botões, navegação completa pelo teclado e atalhos (N para nova "
        "transação, / para buscar).")

    # ------------------------------------------------------------ 5 execução
    doc.titulo("5 Como executar")
    doc.paragrafo("Com Docker (recomendado), na pasta do projeto:")
    doc.codigo("copy .env.example .env      (no Linux ou macOS: cp .env.example .env)\n"
               "docker compose up -d\n"
               "# abra http://localhost:8080")
    doc.paragrafo(
        "O banco é criado sozinho na primeira execução, com uma usuária de demonstração: e-mail mariana@cofrelo.app e "
        "senha cofrelo123. Sem Docker, com o XAMPP: copie a pasta do projeto para htdocs, crie o arquivo .env a partir "
        "do .env.example com os dados do MySQL do XAMPP e abra http://localhost/cofrelo-app. As tabelas e os dados de "
        "demonstração também são criados sozinhos.")
    doc.paragrafo(f"Código-fonte e instruções completas: {p['repositorio']}.")

    # ------------------------------------------------------------ 6 considerações finais
    doc.titulo("6 Considerações finais")
    doc.paragrafo(
        "O Cofrelo atende ao que o briefing pediu: mostra com clareza para onde vai o dinheiro, controla as "
        "assinaturas e transforma metas em um plano mensal. Todas as páginas do protótipo foram implementadas, o "
        "JavaScript foi aplicado nos oito pontos pedidos, o formulário do simulador calcula os resultados enquanto a "
        "pessoa digita e o PHP faz o CRUD completo no MySQL, também disponível como API REST.")
    doc.paragrafo(
        "Como próximos passos, o grupo sugere a integração com o Open Finance para importar as transações dos bancos, "
        "um aplicativo para celular e o envio de lembretes por e-mail.")

    # ------------------------------------------------------------ referências e apêndices
    doc.nova_secao(numerar=True)
    doc.titulo_sem_numero("Referências")
    for referencia in c.referencias():
        doc.referencia(referencia)

    doc.quebra_de_pagina()
    doc.titulo_sem_numero("Apêndice A – Briefing")
    doc.paragrafo(f"Projeto: {p['projeto']}. Cliente: {cliente['empresa']} (fictícia). Entrevistada: {cliente['contato']}.",
                  recuo=False, depois=6)
    for numero, (pergunta, resposta) in enumerate(c.briefing(), start=1):
        doc.paragrafo(f"**{numero}. {pergunta}:** {resposta}", recuo=False, depois=4)

    doc.quebra_de_pagina()
    doc.titulo_sem_numero("Apêndice B – Identificação do grupo")
    doc.quadro(f"{dw['grupo']} – {dw['disciplina']}, {dw['turma']}", ["Integrante", "RA"],
               [[i["nome"], i["ra"] or "—"] for i in integrantes], [11.0, 5.0])

    pasta = c.ENTREGAS
    docx = doc.salvar(pasta / f"Documentação - {p['projeto']} - DW {dw['grupo']}.docx")
    pdf = word_para_pdf(docx, pasta / f"Documentação - {p['projeto']} - DW {dw['grupo']}.pdf",
                        f"{dw['grupo']} – {dw['disciplina']}", f"{p['projeto']}: documentação do sistema")
    return [docx, pdf]


if __name__ == "__main__":
    for arquivo in gerar():
        print(arquivo)
