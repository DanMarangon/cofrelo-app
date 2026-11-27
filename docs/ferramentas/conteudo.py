"""Conteúdo compartilhado pelos documentos do Cofrelo (documentação ABNT, briefing, evidências e slides).

Os textos ficam aqui uma vez só, para a documentação, os slides e o PDF de evidências dizerem a mesma coisa.
Os dados do grupo, da dupla e os links ficam em projeto.json.
"""

from __future__ import annotations

import json
from datetime import date
from pathlib import Path

FERRAMENTAS = Path(__file__).resolve().parent
DOCS = FERRAMENTAS.parent
RAIZ = DOCS.parent
EVIDENCIAS = DOCS / "evidencias"
ENTREGAS = DOCS / "entregas"
PROTOTIPO = DOCS / "prototipo"


def projeto() -> dict:
    return json.loads((FERRAMENTAS / "projeto.json").read_text(encoding="utf-8"))


def integrantes_dw() -> list[dict]:
    return [i for i in projeto()["dw"]["integrantes"] if i["nome"].strip()]


def dupla_devops() -> list[dict]:
    """Os dois da dupla; o segundo aparece como pendente enquanto não for informado em projeto.json."""
    dupla = [dict(i) for i in projeto()["devops"]["dupla"]]
    for pessoa in dupla:
        if not pessoa["nome"].strip():
            pessoa["nome"] = "[segundo integrante: preencher em docs/ferramentas/projeto.json]"
            pessoa["pendente"] = True
    return dupla


def nome_com_ra(pessoa: dict) -> str:
    return f"{pessoa['nome']} (RA {pessoa['ra']})" if pessoa.get("ra") else pessoa["nome"]


def data_por_extenso(d: date | None = None) -> str:
    d = d or date.today()
    meses = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro",
             "outubro", "novembro", "dezembro"]
    return f"{d.day} de {meses[d.month - 1]} de {d.year}"


DESCRICAO_CURTA = (
    "O Cofrelo é um sistema web de controle financeiro pessoal. Ele reúne as contas da pessoa, registra receitas, "
    "despesas, transferências e assinaturas, acompanha metas de economia e mostra relatórios do que entra e do que sai."
)

PROBLEMA = (
    "Muita gente não sabe para onde vai o próprio dinheiro: os gastos ficam espalhados entre cartão, Pix e boletos, "
    "as assinaturas são cobradas sem que a pessoa perceba e guardar dinheiro para um objetivo vira uma intenção que "
    "nunca sai do papel."
)

OBJETIVO = (
    "Dar uma visão clara e rápida das finanças do mês, ajudar a controlar as cobranças recorrentes e transformar metas "
    "(reserva de emergência, viagem, carro) em um plano mensal concreto."
)

FUNCIONALIDADES = [
    ("Visão geral", "saldo total, receitas e despesas do mês com a variação em relação ao mês anterior, fluxo de caixa de 6 meses, gastos por categoria, orçamento usado, últimas transações e a meta mais adiantada"),
    ("Transações", "cadastro de despesas, receitas e transferências, com filtros por texto, categoria, conta e período (calendário), ordenação, paginação, edição, exclusão e exportação em CSV"),
    ("Assinaturas", "uma despesa pode virar cobrança mensal; o sistema lança a cobrança sozinho todo mês e avisa antes do vencimento"),
    ("Minhas contas", "patrimônio, saldo de cada conta e transferências entre contas"),
    ("Metas e objetivos", "progresso de cada meta, depósitos (\"guardar dinheiro\"), simulador de quanto guardar por mês e previsão de conclusão no ritmo atual"),
    ("Relatórios", "evolução mensal, comparação por categoria, taxa de economia e mapa de gastos diários no estilo do calendário do GitHub"),
    ("Notificações", "contas pendentes, cobranças chegando, orçamento quase no fim e metas com prazo próximo"),
    ("Configurações e ajuda", "perfil, orçamento mensal, troca de senha, exclusão da conta e central de ajuda com busca"),
]

TECNOLOGIAS = [
    ("Front-end", "HTML5, CSS3 (sem framework, com variáveis de tema) e JavaScript em módulos ES"),
    ("Back-end", "PHP 8.3 sem framework, com autoload PSR-4 e API REST em JSON"),
    ("Banco de dados", "MySQL 8.4 (InnoDB), acessado por PDO com consultas preparadas"),
    ("Testes", "PHPUnit 11 (unitários e de integração com MySQL) e node --test para o JavaScript"),
    ("Qualidade", "PHPStan nível 6, auditoria de acessibilidade com axe-core (WCAG 2.2 AA)"),
    ("Contêineres", "Docker (imagem multi-stage com Apache) e Docker Compose"),
    ("CI/CD", "GitHub Actions, GitHub Container Registry (GHCR) e releases do GitHub"),
    ("Segurança no pipeline", "composer audit, Gitleaks, Trivy, zizmor, hadolint e Dependabot"),
    ("Observabilidade", "/health, logs em JSON, /metrics, Prometheus (alertas) e Grafana"),
    ("Design", "Figma (protótipo), fonte Inter e ícones SVG próprios"),
]

# Nome, código, descrição: formato do modelo de documentação da Aula 16.
REQUISITOS_FUNCIONAIS = [
    ("Cadastrar usuário", "RF01", "Permite criar uma conta com nome, e-mail, senha e confirmação. Ao terminar, o usuário já entra no sistema com uma conta principal criada."),
    ("Entrar e sair", "RF02", "Permite entrar com e-mail e senha e sair do sistema. A sessão protege todas as páginas e a API."),
    ("Ver a visão geral do mês", "RF03", "Mostra saldo total, receitas, despesas, variação em relação ao mês anterior, fluxo de caixa de 6 meses, gastos por categoria, orçamento usado, últimas transações e a meta em destaque."),
    ("Registrar transação", "RF04", "Permite cadastrar despesa, receita ou transferência entre contas, com valor, descrição, categoria, conta, data, forma de pagamento, status (pago ou pendente) e observação."),
    ("Consultar transações", "RF05", "Lista as transações com busca por descrição ou valor, filtros de categoria, conta e período escolhido num calendário, abas por tipo, ordenação e paginação."),
    ("Alterar transação", "RF06", "Permite editar qualquer campo de uma transação já registrada."),
    ("Excluir transação", "RF07", "Permite excluir uma transação depois de uma confirmação."),
    ("Exportar transações", "RF08", "Gera um arquivo CSV com as transações filtradas, pronto para abrir no Excel."),
    ("Registrar assinatura", "RF09", "Permite marcar uma despesa como assinatura mensal (dia da cobrança e dias de aviso) e consultar, editar, pausar ou excluir as assinaturas."),
    ("Gerenciar contas", "RF10", "Permite cadastrar, consultar, alterar e excluir contas (corrente, poupança, investimento, carteira digital e dinheiro) e ver o patrimônio total."),
    ("Gerenciar metas", "RF11", "Permite criar, consultar, alterar e excluir metas com valor objetivo, valor atual, prazo e ícone."),
    ("Guardar dinheiro na meta", "RF12", "Permite registrar um depósito numa meta; o valor guardado e o progresso são atualizados na hora."),
    ("Simular aporte mensal", "RF13", "Formulário que calcula, enquanto a pessoa digita, quanto guardar por mês para chegar ao objetivo no prazo, com rendimento mensal opcional."),
    ("Ver previsão de conclusão", "RF14", "Mostra em cada meta em quantos meses ela fica pronta se a pessoa continuar guardando no ritmo dos últimos 3 meses."),
    ("Ver relatórios", "RF15", "Mostra evolução mensal, comparação por categoria com o mês anterior, taxa de economia e mapa de gastos diários, com escolha do período, e exporta o relatório em CSV."),
    ("Receber notificações", "RF16", "Avisa no sino do topo sobre contas pendentes, cobranças de assinatura chegando, orçamento quase no fim e metas com prazo próximo."),
    ("Configurar o perfil", "RF17", "Permite alterar nome, e-mail e orçamento mensal, trocar a senha e excluir a própria conta."),
    ("Consultar a ajuda", "RF18", "Central de ajuda com perguntas frequentes, busca e lista de atalhos de teclado."),
    ("Usar a API REST", "RF19", "Todas as operações também respondem em JSON nas rotas /api/... (autenticação, transações, contas, metas, assinaturas, resumo, relatório e notificações)."),
]

REQUISITOS_NAO_FUNCIONAIS = [
    ("Desempenho", "RNF01", "As páginas e as respostas da API devem carregar em menos de 1 segundo numa rede local. A verificação de saúde (/health) responde em poucos milissegundos."),
    ("Compatibilidade de navegador", "RNF02", "Funcionar nas versões atuais do Chrome, Edge, Firefox e Safari, sem plugins."),
    ("Responsividade", "RNF03", "Layout adaptado a telas de celular (a partir de 390 px de largura), tablet e computador."),
    ("Acessibilidade", "RNF04", "Atender às diretrizes WCAG 2.2 nível AA: contraste, nomes acessíveis, navegação completa pelo teclado e atalhos. Auditoria com axe-core sem violações."),
    ("Segurança", "RNF05", "Senhas guardadas com bcrypt, sessão com cookie HttpOnly e SameSite, token CSRF, consultas preparadas, Content-Security-Policy, bloqueio após 5 tentativas de login erradas e nenhuma senha ou chave no repositório."),
    ("Privacidade (LGPD)", "RNF06", "Coletar só os dados necessários, não guardar páginas com dados financeiros no cache do navegador, registrar o e-mail nos logs apenas como hash e permitir que o usuário exclua a própria conta."),
    ("Sistema operacional e infraestrutura", "RNF07", "Rodar em Windows, Linux ou macOS com Docker 24 ou mais novo e Docker Compose v2, cerca de 1 GB de memória livre e as portas 8080, 9090 e 3000. Alternativa sem Docker: PHP 8.1 ou mais novo com pdo_mysql, Apache com mod_rewrite e MySQL 8 (XAMPP)."),
    ("Rede e banda larga", "RNF08", "Funcionar em rede local ou internet comum; fontes, ícones e scripts são servidos pelo próprio sistema, sem depender de CDNs externas."),
    ("Disponibilidade e monitoramento", "RNF09", "Endpoint /health, logs em JSON, métricas no formato do Prometheus com alertas e painel no Grafana; a implantação volta sozinha para a versão anterior se a nova não ficar saudável."),
    ("Configuração", "RNF10", "Dados de conexão e senhas em variáveis de ambiente (.env), fora do código; o .env.example mostra quais variáveis existem."),
    ("Manutenibilidade", "RNF11", "Código organizado em camadas (controladores, serviços, repositórios), analisado pelo PHPStan e coberto por testes automatizados que rodam a cada push no GitHub Actions."),
    ("Portabilidade e versionamento", "RNF12", "Aplicação entregue como imagem Docker versionada no GitHub Container Registry; código no GitHub com GitFlow e Pull Requests."),
]

REGRAS_DE_NEGOCIO = [
    ("Senha forte", "RN01", "A senha precisa ter pelo menos 8 caracteres, misturando letras e números, no cadastro e na troca de senha."),
    ("Conta principal", "RN02", "Todo usuário começa com uma conta chamada \"Conta principal\", criada junto com o cadastro."),
    ("Dados válidos da transação", "RN03", "O valor precisa ser maior que zero e ter no máximo duas casas decimais; a descrição, de 2 a 120 caracteres; a data, entre o ano 2000 e os próximos dois anos; a conta precisa ser do próprio usuário."),
    ("Conta com histórico", "RN04", "Uma conta que já tem transações não pode ser excluída, para não apagar o histórico junto."),
    ("Saldo real", "RN05", "O saldo das contas considera só as transações pagas; o que está pendente ainda não saiu da conta."),
    ("Totais do mês", "RN06", "Receitas, despesas e orçamento do mês somam o que já foi pago e o que está pendente no mês."),
    ("Categoria e transferência", "RN07", "Categoria de receita só vale para receita e de despesa só para despesa. Transferência não tem categoria e exige contas de origem e destino diferentes."),
    ("Situação da meta", "RN08", "Meta sem dinheiro guardado está \"Pronta para começar\"; com algum valor, \"Em progresso\"; ao atingir o objetivo, \"Concluída\"."),
    ("Aporte mensal sugerido", "RN09", "O sistema calcula quanto guardar por mês para chegar ao objetivo no prazo, considerando o rendimento mensal quando informado (valor futuro de uma série de depósitos)."),
    ("Taxa de economia", "RN10", "A taxa de economia do período é a parte da renda que sobrou: (receitas - despesas) / receitas."),
    ("Cobrança automática de assinatura", "RN11", "Só despesas podem virar assinatura. No primeiro acesso de cada mês, o sistema lança sozinho a cobrança no dia escolhido (de 1 a 28)."),
    ("Avisos", "RN12", "O sistema avisa sobre contas pendentes que vencem em até 7 dias ou estão atrasadas, assinaturas que vão ser cobradas dentro do prazo de aviso, orçamento com 80% ou mais usado e metas com prazo em até 30 dias."),
    ("Acesso restrito", "RN13", "Só usuários cadastrados e logados acessam as páginas e a API, e cada usuário só vê e altera os próprios dados."),
    ("Previsão da meta", "RN14", "A previsão de conclusão usa a média guardada nos últimos 3 meses e arredonda os meses para cima; sem depósitos recentes, não há previsão."),
]

# Perguntas do Briefing Modelo da Aula 16 e as respostas da cliente (fictícia, para fins acadêmicos).
BRIEFING = [
    ("Ramo de atuação da empresa e tempo de mercado",
     "Tecnologia financeira (fintech) voltada à organização e à educação financeira pessoal. Startup fundada em 2025 em Brasília, com cerca de um ano de mercado."),
    ("Número de filiais/franquias",
     "Nenhuma. A operação é totalmente digital, com uma equipe pequena em Brasília."),
    ("Produtos e/ou serviços oferecidos",
     "Um sistema web gratuito de controle financeiro pessoal (contas, transações, assinaturas, metas e relatórios) e conteúdos curtos de educação financeira nas redes sociais."),
    ("Endereço do site atual",
     "A empresa ainda não tem site nem sistema; a divulgação acontece só pelas redes sociais. O domínio definitivo ainda será escolhido."),
    ("Vantagens/desvantagens sobre os concorrentes",
     "Vantagens: uso simples, foco em metas e em assinaturas, sem anúncios, sem venda de dados e sem instalar nada (funciona no navegador do computador e do celular). Desvantagens: marca nova e, nesta primeira versão, sem integração automática com os bancos (Open Finance) e sem aplicativo nativo."),
    ("Referências de sites (outras empresas)",
     "Mobills e Organizze (organização por categorias e contas), Nubank (visual limpo e linguagem simples) e o calendário de contribuições do GitHub (ideia para o mapa de gastos)."),
    ("Objetivos a serem alcançados",
     "Ajudar as pessoas a saber para onde vai o dinheiro, controlar as cobranças recorrentes e criar o hábito de guardar para objetivos. Como produto mínimo, validar o uso com os primeiros usuários e medir quantos criam ao menos uma meta."),
    ("Público-alvo",
     "Jovens adultos de 18 a 35 anos, principalmente estudantes universitários e profissionais em início de carreira, que usam celular e computador e querem organizar a vida financeira sem planilhas."),
    ("Conteúdo do site",
     "Tela de entrada e cadastro; visão geral do mês; transações (com assinaturas); minhas contas; metas e objetivos com simulador; relatórios; notificações; configurações; central de ajuda."),
    ("Imagem a ser transmitida para os usuários",
     "Modernidade e confiança, com layout clean e calmo. Cores: verde (dinheiro e equilíbrio) com um verde-lima de destaque, fundo claro e textos escuros. Fonte Inter. Slogan: \"Finanças em equilíbrio\"."),
    ("Documentação disponível",
     "Logotipo (um cofrinho estilizado), paleta de cores e o protótipo no Figma com as telas principais, feitos pela equipe. Não há folders nem manuais."),
    ("Tecnologias ou linguagens desejadas",
     "HTML, CSS, JavaScript, PHP e banco de dados MySQL. O sistema deve rodar em contêiner Docker e o código ficar versionado no GitHub."),
    ("Ferramentas de marketing utilizadas atualmente",
     "Perfis no Instagram e no TikTok com dicas de finanças e uma lista de e-mails de interessados."),
    ("Objeções",
     "Não usar vermelho como cor principal (lembra dívida e prejuízo), evitar telas poluídas e excesso de gráficos, não exibir anúncios e nunca pedir senha de banco."),
    ("Mínimo e máximo de verba disponível para o projeto",
     "Por ser um projeto acadêmico, não há custo. Como referência, a cliente estimou entre R$ 10 mil e R$ 20 mil para um produto mínimo feito por uma agência."),
    ("Prazo para desenvolvimento e/ou cronograma",
     "Documentação e aplicação até 30/11/2026; apresentação do sistema funcionando em 08/12/2026."),
    ("Contato (Nome/tel/email)", None),  # preenchido com projeto.json
    ("Observação",
     "O sistema precisa funcionar em qualquer computador no dia da apresentação, com dados de demonstração (usuária fictícia Mariana Alves), e respeitar a LGPD, sem coletar dados desnecessários."),
]


def briefing() -> list[tuple[str, str]]:
    cliente = projeto()["cliente_ficticio"]
    contato = f"{cliente['contato']}; telefone {cliente['telefone']}; e-mail {cliente['email']}."
    return [(pergunta, resposta if resposta is not None else contato) for pergunta, resposta in BRIEFING]


# Item 10 do enunciado de DW: onde cada uso do JavaScript aparece no código.
JAVASCRIPT = [
    ("a", "Estruturas condicionais e de controle de fluxo",
     "componentes/modal-transacao.js (cada tipo de transação mostra campos diferentes), componentes/transacao-visual.js (switch por tipo), componentes/calendario.js e paginas/ajuda.js (laços e condições)",
     "if/else, switch, for e for...of decidem o que aparece na tela e percorrem listas de dias, perguntas e marcas dos gráficos."),
    ("b", "Eventos",
     "app.js (atalhos de teclado N, / e G+V), componentes/modal.js (Esc e Tab), componentes/formulario.js e paginas/transacoes.js (evento input com espera)",
     "addEventListener em click, input, submit, keydown e change. A busca de transações só roda quando a pessoa para de digitar."),
    ("c", "Métodos",
     "paginas/metas.js (filter e map para separar e desenhar as metas), lib/formatos.js (Intl.NumberFormat e toLocaleDateString)",
     "Métodos de array (map, filter, reduce, sort, find), de string e de data, e métodos dos próprios objetos do sistema."),
    ("d", "Objetos",
     "paginas/transacoes.js (objeto com o estado dos filtros) e componentes/modal-transacao.js (estado do formulário aberto)",
     "Objetos literais guardam o estado da tela; os dados da API chegam como objetos JSON."),
    ("e", "Tabelas",
     "paginas/transacoes.js e paginas/visao-geral.js",
     "As tabelas de transações são montadas pelo JavaScript a partir da API, com uma linha por transação e botões de editar e excluir."),
    ("f", "Funções",
     "paginas/metas.js (função que lê o simulador, calcula e escreve os resultados), componentes/modal-deposito.js e lib/calculos.js",
     "Funções puras de cálculo (aporte mensal, valor futuro, percentuais), testadas com node --test, e funções que desenham cada parte da tela."),
    ("g", "Interações com o usuário",
     "componentes/graficos.js (ao passar o mouse, destaca o mês e mostra os valores) e paginas/configuracoes.js (força da senha enquanto digita)",
     "Modais, calendário de período, máscara de valores, confirmação antes de excluir, avisos e destaque dos gráficos."),
    ("h", "Automatizações",
     "componentes/notificacoes.js (atualiza a cada minuto), componentes/avisos.js (aviso some sozinho) e componentes/modal-transacao.js (sugere a categoria pela descrição)",
     "Atualizações periódicas, sugestão automática de categoria e cálculos refeitos a cada tecla."),
]

CRUD = [
    ("Usuário", "Cadastro (/cadastro, POST /api/auth/cadastro)", "GET /api/auth/eu", "Configurações (perfil e senha)", "Configurações (excluir conta)"),
    ("Transação", "POST /api/transacoes", "GET /api/transacoes e /api/transacoes/{id}", "PUT /api/transacoes/{id}", "DELETE /api/transacoes/{id}"),
    ("Conta", "POST /api/contas", "GET /api/contas e /api/contas/{id}", "PUT /api/contas/{id}", "DELETE /api/contas/{id}"),
    ("Meta", "POST /api/metas e POST /api/metas/{id}/depositos", "GET /api/metas e /api/metas/{id}", "PUT /api/metas/{id}", "DELETE /api/metas/{id}"),
    ("Assinatura", "POST /api/transacoes (com assinatura)", "GET /api/assinaturas", "PUT /api/assinaturas/{id}", "DELETE /api/assinaturas/{id}"),
]

TELAS = [
    ("01-entrar.png", "Tela de entrada"),
    ("02-criar-conta.png", "Cadastro de usuário"),
    ("03-visao-geral.png", "Visão geral do mês"),
    ("04-transacoes.png", "Transações com filtros, abas e tabela"),
    ("05-calendario-de-periodo.png", "Calendário para escolher o período (comentário do protótipo)"),
    ("06-nova-transacao.png", "Modal de nova transação"),
    ("07-nova-assinatura.png", "Aba Assinatura no modal de nova transação (comentário do protótipo)"),
    ("08-assinaturas.png", "Assinaturas dentro de Transações"),
    ("09-minhas-contas.png", "Minhas contas"),
    ("10-metas-e-simulador.png", "Metas, previsão de conclusão e simulador de aporte mensal"),
    ("11-relatorios.png", "Relatórios com o mapa de gastos no estilo do GitHub (comentário do protótipo)"),
    ("12-configuracoes.png", "Configurações"),
    ("13-central-de-ajuda.png", "Central de ajuda"),
    ("14-notificacoes.png", "Painel de notificações"),
    ("15-celular-visao-geral.png", "Visão geral no celular"),
]

WIREFRAMES = [
    ("01-entrar.png", "Entrar"), ("02-criar-conta.png", "Criar conta"), ("03-visao-geral.png", "Visão geral"),
    ("04-transacoes.png", "Transações"), ("05-nova-transacao.png", "Nova transação (modal)"),
    ("06-minhas-contas.png", "Minhas contas"), ("07-metas-e-objetivos.png", "Metas e objetivos"),
    ("08-relatorios.png", "Relatórios"), ("09-configuracoes.png", "Configurações"), ("10-central-de-ajuda.png", "Central de ajuda"),
]

PROTOTIPO_TELAS = [
    ("01-visao-geral.png", "Protótipo: visão geral"),
    ("02-transacoes-com-comentario.png", "Protótipo: transações, com o comentário pedindo um calendário de período"),
    ("03-minhas-contas.png", "Protótipo: minhas contas"),
    ("04-metas-e-objetivos.png", "Protótipo: metas e objetivos"),
    ("05-icones-e-estilos.png", "Protótipo: ícones, cores e estilos"),
    ("06-relatorios.png", "Protótipo: relatórios"),
    ("07-relatorios-com-comentario.png", "Protótipo: relatórios, com o comentário pedindo o calendário do GitHub"),
    ("08-nova-transacao.png", "Protótipo: nova transação"),
    ("09-nova-transacao-com-comentario.png", "Protótipo: nova transação, com o comentário pedindo a opção de assinatura"),
]

REFERENCIAS = [
    "ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS. **NBR 14724**: informação e documentação: trabalhos acadêmicos: apresentação. Rio de Janeiro: ABNT, 2011.",
    "ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS. **NBR 6023**: informação e documentação: referências: elaboração. Rio de Janeiro: ABNT, 2018.",
    "DOCKER INC. **Docker Documentation**. [S. l.], 2026. Disponível em: https://docs.docker.com. Acesso em: 27 nov. 2026.",
    "GITHUB. **GitHub Actions documentation**. [S. l.], 2026. Disponível em: https://docs.github.com/actions. Acesso em: 27 nov. 2026.",
    "MOZILLA. **MDN Web Docs**: HTML, CSS e JavaScript. [S. l.], 2026. Disponível em: https://developer.mozilla.org/pt-BR. Acesso em: 27 nov. 2026.",
    "ORACLE. **MySQL 8.4 Reference Manual**. [S. l.], 2026. Disponível em: https://dev.mysql.com/doc/refman/8.4/en. Acesso em: 27 nov. 2026.",
    "PHP GROUP. **Manual do PHP**. [S. l.], 2026. Disponível em: https://www.php.net/manual/pt_BR. Acesso em: 27 nov. 2026.",
    "W3C. **Web Content Accessibility Guidelines (WCAG) 2.2**. [S. l.], 2023. Disponível em: https://www.w3.org/TR/WCAG22. Acesso em: 27 nov. 2026.",
]


def referencias() -> list[str]:
    extras = projeto().get("referencias_extras", [])
    return sorted(REFERENCIAS + extras, key=lambda r: r.replace("*", "").upper())
