# Changelog

Todas as alteracoes relevantes deste sistema serao registradas aqui.

## [1.6.40] - 2026-08-10

### Adicionado
- Tela de dados passa a exportar todos os escopos administrativos em JSON, PDF, CSV e XLSX.
- Exportacao XLSX cria uma aba por tabela, com cabecalho fixo, filtros e preservacao de codigos textuais.
- Escopos CSV com varias tabelas sao entregues em um pacote ZIP com manifesto; escopos simples continuam como arquivo CSV direto.
- Relatorio PDF administrativo apresenta resumo, tabelas paginadas e visualizacao pronta para imprimir ou salvar como PDF.

### Seguranca e compatibilidade
- Conteudos textuais de CSV e XLSX sao protegidos contra interpretacao indevida como formulas de planilha.
- Nova rota de exportacao utiliza a permissao administrativa de gerenciamento de dados e mantem a exportacao JSON anterior compativel.
- Documentada e adicionada ao diagnostico a extensao PHP `zip`, necessaria para XLSX e pacotes CSV compostos.

## [1.6.39] - 2026-07-28

### Adicionado
- Anexo V por lote com relacao simplificada do numero e nome do item, quantidade, valor unitario estimado e valor total estimado.
- Cada lote possui cabecalho com numero e denominacao, tabela propria e valor total do lote.
- Novo anexo disponivel em PDF institucional, Word e Excel no menu de anexos por lote.

### Integridade
- Anexo V participa do versionamento, hash, contagem de itens, total financeiro e invalidacao automatica dos documentos do projeto.
- Schema e permissoes de relatorios passam a reconhecer o novo tipo `lot_annex_v`.

## [1.6.38] - 2026-07-24

### Corrigido
- Impressao do DOD recebe um rodape alternativo para Firefox/Gecko, repetido pelo grupo de rodape da tabela paginada quando o elemento fixo nao e renderizado pelo navegador.
- O fallback reutiliza as mesmas informacoes institucionais, faixas coloridas, altura reservada e recuo de seguranca do rodape principal.

### Compatibilidade
- Chrome e Edge mantem o rodape fixo validado, sem duplicacao visual; Firefox passa a usar o mecanismo compativel com sua paginacao.

## [1.6.37] - 2026-07-23

### Corrigido
- Rodape institucional do DOD passa a ser recuado para dentro da area imprimivel, evitando que navegadores o cortem ou ocultem ao imprimir e exportar para PDF.
- Reserva inferior de cada pagina considera o novo recuo do rodape e mantem o conteudo sem sobreposicao.

### Testes
- Suite do DOD valida o recuo de seguranca, a reserva inferior completa e a visibilidade explicita do rodape na impressao.

## [1.6.36] - 2026-07-22

### Corrigido
- Impressao/PDF do DOD deixa de usar offsets verticais negativos que faziam o Chromium fragmentar cabecalho e rodape em paginas ou regioes incorretas.
- Cabecalho institucional, conteudo e rodape passam a ocupar faixas separadas em todas as paginas, sem corte ou sobreposicao.
- Normalizacao do cabecalho passa a preservar entidade, estado, local e caminhos de logos ja configurados no documento.

### Alterado
- Metricas de impressao passam a expor posicoes fisicas claras para topo do cabecalho e base do rodape, mantendo area exclusiva para a numeracao.
- Grupos de cabecalho e rodape de uma tabela estrutural reservam o espaco necessario em cada pagina do Chromium, enquanto os elementos fixos usam somente coordenadas nao negativas.
- Alturas recebem margem de seguranca para logos, linhas institucionais, faixas e quatro linhas de contato; visualizacao em tela e cabecalhos/rodapes nativos do Word permanecem inalterados.

### Testes
- Suite do DOD valida coordenadas, relacoes entre margens e alturas, ausencia dos offsets antigos, valores personalizados e espacos repetidos.
- PDF realista de 22 paginas gerado no Chrome foi validado quanto a logos, cabecalho, rodape, numeracao, conteudo, tabelas, titulos, paginas em branco e cores institucionais.

## [1.6.35] - 2026-07-22

### Documentacao
- Backlog de possiveis features revisado contra o estado atual do sistema e reorganizado por prioridade critica, alta, media e estrategica.
- Autenticacao AD/LDAP e configuracao de exemplo para Nginx/PHP-FPM foram marcadas como ja implementadas.
- Entregas parciais agora descrevem separadamente o que existe e o escopo que ainda permanece pendente.
- Adicionadas novas sugestoes para CSRF, rate limiting, seguranca de uploads, observabilidade, testes end-to-end, filas, notificacoes, PNCP, planejamento anual, gestao pos-compra, qualidade de dados e pesquisa global.

## [1.6.34] - 2026-07-22

### Adicionado
- DOD passa a permitir que o usuario escolha, de forma independente, se o cabecalho e o rodape institucionais devem repetir em todas as paginas.
- As preferencias sao aplicadas na impressao/PDF e na exportacao Word; quando desativadas, o cabecalho permanece apenas no inicio e o rodape apenas no fim do documento.

### Compatibilidade
- DODs existentes continuam repetindo cabecalho e rodape por padrao, sem necessidade de migracao do banco de dados.
- A numeracao de paginas do Word permanece funcional mesmo quando o rodape institucional nao e repetido.

### Testes
- Suite do DOD passa a validar os valores padrao, a desativacao independente e a integracao das novas opcoes com o formulario e os exportadores.

## [1.6.33] - 2026-07-22

### Corrigido
- Corrigidos todos os textos com acentuacao substituida por `?` na tela do comparativo anual de precos, incluindo titulos, indicadores, tabelas, mensagens e legendas dos graficos.
- Exportacao Excel do comparativo recebeu as mesmas correcoes em cabecalhos, resumo, serie mensal e observacoes de preco.

### Testes
- Suite do BI passa a validar UTF-8, ausencia de interrogacoes no lugar de acentos e os principais textos da tela e da exportacao.

## [1.6.32] - 2026-07-22

### Corrigido
- Cabecalho e rodape do PDF do DOD deixam de compartilhar o mesmo elemento usado na pre-visualizacao, evitando posicionamento instavel e sobreposicao durante a fragmentacao em varias paginas.
- Margens superior e inferior de impressao passam a considerar a quantidade real de linhas institucionais, dados do rodape e espaco exclusivo da numeracao de paginas.
- Elementos repetidos ficam posicionados fora do fluxo paginado, enquanto a visualizacao em tela preserva o layout e a aparencia anteriores.

### Testes
- Suite do DOD passa a validar a geometria reservada, afastamento das bordas, area da paginacao e separacao entre os elementos de tela e de impressao.

## [1.6.31] - 2026-07-21

### Alterado
- Menu superior substituido por sidebar fixa e rolavel no desktop, liberando largura e reduzindo a poluicao visual da navegacao.
- Navegacao reorganizada em secoes de acesso principal, cadastros e administracao, preservando filtros por permissao e destaque da pagina atual.
- Perfil, papel do usuario, alteracao de senha e encerramento da sessao passam a ficar no rodape da sidebar.

### Responsividade
- Em tablets e celulares, a sidebar passa a funcionar como painel lateral do Bootstrap, com cabecalho compacto, fechamento automatico ao navegar e largura limitada ao viewport.
- Estados de foco, contraste, textos longos e rolagem ate o item ativo receberam tratamento especifico de acessibilidade e usabilidade.

### Testes
- Adicionado teste automatizado para estrutura da sidebar, permissoes, estado ativo, dimensoes responsivas, foco visivel e comportamento do offcanvas.

## [1.6.30] - 2026-07-21

### Seguranca
- Todas as 119 rotas publicas passam a possuir politica explicita de acesso; paginas autenticadas nao mapeadas sao negadas por padrao e registradas em log.
- Endpoints legados que carregam diretamente o repositorio agora inicializam a autenticacao antes de executar consultas ou alteracoes.
- Separadas as permissoes de consulta e gestao para catalogo, projetos e orcamentos, preservando o perfil Consulta como somente leitura.
- Sugestoes de IA passam a exigir `ai.use`, relatorios e anexos exigem `reports.view` e arquivos privados de cotacao exigem `budgets.view`.
- Cookies de sessao reconhecem HTTPS encaminhado pelo Nginx e usam modo estrito de sessao.

### Alterado
- Lotes e memorias quantitativas aceitam consulta em `GET`, mas exigem gestao para qualquer alteracao.
- Menus e acoes de itens, projetos, demandas, orcamentos, lotes, versoes e assinaturas ocultam comandos que o perfil atual nao pode executar.
- Evidencias privadas de assinatura deixam de ser consultadas ou exibidas para perfis sem permissao de confirmacao.
- README passa a documentar a matriz dos quatro perfis do sistema.

### Testes
- Criada auditoria automatizada da cobertura de autorizacao, do bootstrap de seguranca, da validade da matriz e da separacao entre leitura e escrita.
- Testes de autenticacao ampliados para perfis, IA, relatorios, orcamentos, rotas desconhecidas e politicas dependentes do metodo HTTP.

## [1.6.29] - 2026-07-21

### Corrigido
- Valor total estimado do `project_show` passa a usar a mesma regra de media por fonte aplicada aos Anexos II e IV.
- Cotacoes repetidas do mesmo fornecedor em demandas diferentes sao consolidadas antes da media global do item, evitando ponderacoes divergentes.

### Alterado
- Calculo consolidado de precos permanece em consulta em lote, sem restaurar as consultas repetidas que prejudicavam o desempenho do projeto.
- Regra de arredondamento, fallback de valor manual e contagem de fontes foi centralizada para uso pelo projeto e pelos anexos.

### Testes
- Adicionado teste de regressao que exige igualdade entre a media e o total exibidos no projeto e os valores dos Anexos II e IV.

## [1.6.28] - 2026-07-21

### Adicionado
- Acao para sequenciar automaticamente os lotes pela ordem de cadastro, recompondo a numeracao continua apos exclusoes ou alteracoes manuais.

### Alterado
- Renumeracao de lotes passa a ser transacional, respeita o bloqueio do projeto, evita conflitos com numeros existentes e invalida anexos somente quando houver mudanca.

### Testes
- Suite passa a verificar a ordenacao por data de criacao e identificador, o tratamento da restricao unica e a disponibilidade da nova acao na interface.

## [1.6.27] - 2026-07-21

### Documentacao
- `.env.example` reorganizado por secoes, com comentarios curtos sobre finalidade, formato e valores aceitos em cada configuracao.
- Incluidos alertas para credenciais, cores, ambiente, PostgreSQL, OpenAI, toolkit e autenticacao AD/LDAP.

## [1.6.26] - 2026-07-21

### Adicionado
- Toolkit flutuante de ferramentas rapidas integrado ao layout compartilhado das telas internas.
- Variaveis de ambiente para ativacao, URL do script, textos, cores, posicao e atalho do toolkit.
- Validacao de posicao e cores antes da inicializacao, com configuracao serializada de forma segura para JavaScript.

### Documentacao
- `.env.example` e README passam a documentar a configuracao completa do toolkit.

## [1.6.25] - 2026-07-21

### Adicionado
- Topico 4 do DOD dividido em estimativa automatica e metodologia editavel, com tabela sequencial de item, descricao, tipo de unidade e quantidade efetiva consolidada.
- Topico 5 do DOD estruturado em requisitos tecnicos minimos por item, prazo de entrega, condicoes de recebimento e suporte tecnico.
- Parametros editaveis de prazo, tipo de dia e marco inicial da entrega, mantendo os padroes de 7 dias para entrega e 5 dias para recebimento.

### Alterado
- Requisitos tecnicos do DOD passam a aproveitar automaticamente especificacoes, criterios de aceitacao, documentacao, certificados, observacoes, garantia e validade cadastrados nos itens.
- Conteudos manuais existentes nos topicos de quantidade e requisitos sao preservados como metodologia e requisitos adicionais.
- Pre-visualizacao e impressao do DOD passam a usar dimensoes A4, cabecalho e rodape mais estaveis, tabelas com colunas controladas e melhor quebra de pagina.

### Testes
- Suite do DOD passa a cobrir tabela automatica de quantidades, configuracoes dos requisitos, valores padrao, validacoes e estilos essenciais da exportacao.

## [1.6.24] - 2026-07-21

### Adicionado
- Fluxo administrativo para aprovar, negar ou aprovar demandas com ressalva, com justificativa obrigatoria nas decisoes restritivas.
- Aprovacao quantitativa por item, permitindo registrar quantidades diferentes das solicitadas somente em decisoes com ressalva.
- Historico auditavel das decisoes, com responsavel, data, justificativa e snapshot dos quantitativos solicitados e aprovados.
- Situacao da aprovacao exibida na demanda, na listagem do projeto e nos relatorios PDF e Word.
- Exportacao e importacao JSON das decisoes, validacoes dos itens e historico de aprovacao.

### Alterado
- Alteracoes posteriores nos dados, itens ou quantitativos da demanda voltam sua analise para pendente, preservam o historico e invalidam os anexos do projeto.
- `project_show.php` passa a reutilizar o consolidado ja carregado e deixa de recalcular o conteudo e o hash de todos os anexos a cada abertura.
- Calculo dos valores medios do projeto passa de consultas repetidas por demanda para uma consulta agregada por conjunto, com cache durante a requisicao.
- Projetos de compra direta deixam de consultar situacoes de anexos exclusivos de licitacao.

### Banco de Dados
- Schema adiciona os campos da decisao em `demand_lists` e a tabela imutavel `demand_approval_events`, com constraints, indices e ajuste de sequencia idempotentes.
- Registros de demandas existentes permanecem sem decisao formal ate serem analisados; novas demandas iniciam com situacao pendente.

### Testes
- Suite passa a cobrir aprovacao integral, ressalva quantitativa, negativa, validacoes obrigatorias, historico no JSON e estados dos anexos.
- Testes de regressao protegem a consulta unica das versoes dos anexos, a agregacao de orcamentos e o reaproveitamento do consolidado no projeto.

## [1.6.23] - 2026-07-20

### Adicionado
- Anexo IV por item com relacao simplificada do numero oficial, nome e quantidade efetiva de cada item da licitacao.
- Exportacao do novo anexo em PDF institucional, Word e Excel, com data de emissao configuravel.
- Versionamento, hash, invalidacao automatica e indicador de situacao para o novo documento.

### Banco de Dados
- Restricao de tipos de anexos atualizada de forma idempotente para aceitar o Anexo IV por item sem alterar os registros existentes.

### Corrigido
- Resumo textual da memoria de calculo passa a identificar as demandas aprovadas e a unidade da quantidade final conforme o resultado esperado.
- Teste da memoria estruturada corrige a ordem dos snapshots solicitado/aprovado e compara quantidades decimais adequadamente.

### Testes
- Suite de relatorios passa a validar titulo, colunas, versionamento e suporte do schema ao novo anexo.

## [1.6.22] - 2026-07-20

### Adicionado
- Memoria de calculo estruturada para estimativa de quantitativos em projetos, com composicao, deducoes, referencias de apoio, texto legivel e hash proprio.
- Teste automatizado dedicado a validar o exemplo completo de memoria de calculo, a quantidade efetiva e a renderizacao textual do resumo.

### Alterado
- Itens, demandas, anexos e relatorios passam a considerar a quantidade efetiva consolidada quando a memoria de calculo do item estiver disponivel.
## [1.6.21] - 2026-07-13

### Adicionado
- Pagina administrativa `Editor e documentos` para definir fonte, tamanho, entrelinhas, espaco entre paragrafos, alinhamento, aplicacao forcada do alinhamento, margens de impressao e numeracao.
- Previa responsiva e atualizada em tempo real das configuracoes do documento.
- Persistencia global auditavel dos padroes do TipTap e documentos, com permissao exclusiva de administrador e suporte na exportacao/importacao JSON geral.

### Alterado
- TipTap passa a carregar os padroes administrativos em editores existentes e em topicos adicionados dinamicamente ao DOD.
- DOD passa a aplicar tipografia, espacamentos, alinhamento e margens definidos pela Administracao.
- Cabecalho e rodape institucionais do DOD passam a ser repetidos em todas as paginas impressas e nas secoes do Word.
- Impressao/PDF e Word passam a exibir pagina atual e total de paginas quando a numeracao estiver habilitada.

### Banco de Dados
- Schema adiciona a tabela singleton `rich_text_editor_settings` e o respectivo gatilho de atualizacao, sem alterar configuracoes ou documentos existentes.

### Testes
- Suite passa a cobrir normalizacao e limites dos padroes, permissao administrativa, schema, TipTap, exportacao JSON e repeticao/paginacao do DOD em impressao e Word.

## [1.6.20] - 2026-07-13

### Adicionado
- Secao estruturada de classificacao e condicoes de fornecimento no cadastro de itens, com opcoes para material permanente, consumo nao perecivel e consumo perecivel.
- Classificacao interna propria para servicos, preservando a separacao entre objetos materiais e execucao de servicos.
- Garantia minima em meses, validade remanescente opcional ou obrigatoria conforme a classificacao e textos institucionais gerados automaticamente.
- Previa em tempo real das clausulas de garantia e validade, com selecao visual responsiva e excecao de validade para material permanente restrita a administrador e acompanhada de justificativa.
- Filtro de classificacao na listagem do catalogo e exibicao dos novos dados no item, nas versoes, nos anexos e nas exportacoes Word, PDF e JSON.
- Auditoria das condicoes de fornecimento com estado anterior, proximo estado, textos legados, usuario e data da versao.

### Alterado
- Novos itens e itens editados passam a exigir dados estruturados; itens antigos permanecem inalterados ate a primeira edicao.
- Textos livres de garantia e validade deixam de ser a fonte oficial depois da migracao e passam a ser preservados somente no historico.
- Exportacao e importacao JSON passam ao formato 2, incluindo classificacao, perecibilidade, prazos, textos gerados e resumo auditavel das versoes.
- Copia, restauracao de versoes, demandas e consultas consolidadas passam a preservar as condicoes estruturadas dos itens.

### Banco de Dados
- Schema adiciona natureza, perecibilidade, garantia em meses, validade minima, justificativa excepcional e marcador de migracao aos itens e suas versoes.
- Constraints mantem itens legados validos sem alterar seus dados e aplicam os prazos minimos aos registros migrados.

### Testes
- Suite passa a cobrir valores padrao, limites minimos, bloqueio de decimais e intervalos, perecibilidade, servicos, excecao administrativa, anexos e formato JSON 2.

## [1.6.19] - 2026-07-13

### Alterado
- Anexos I e II, por item e por lote, deixam de exibir o topico de certificados e seus valores.
- Certificados permanecem disponiveis no cadastro e na consulta comum dos itens.

### Testes
- Suite de relatorios passa a garantir a ausencia de certificados nas versoes HTML e textuais dos anexos.

## [1.6.18] - 2026-07-13

### Adicionado
- Componente reutilizavel de texto rico com TipTap 3, suporte a titulos, paragrafos, negrito, italico, sublinhado, listas, tabelas, alinhamento, links, desfazer/refazer e limpeza de formatacao.
- Validacao por limite de caracteres, carregamento de HTML existente, integracao automatica aos formularios e comportamento responsivo e acessivel.
- Saneamento de HTML no servidor para preservar apenas estruturas, links e atributos permitidos antes da persistencia e da exibicao.

### Corrigido
- Faixas institucionais vermelha, azul e amarela do DOD passam a permanecer visiveis na impressao mesmo quando o navegador nao imprime fundos.
- Numeracao `Pagina x de y` deixa de ser exibida na pre-visualizacao e na impressao do DOD.

### Alterado
- Topicos manuais do DOD passam a usar o editor TipTap; topicos automaticos permanecem somente leitura.
- Exportacao do DOD passa a formatar de forma legivel titulos, listas, links e tabelas produzidos pelo editor.

### Testes
- Suite cobre saneamento de HTML, conteudo existente, recursos essenciais do editor, ausencia da numeracao e faixas proprias para impressao.

## [1.6.17] - 2026-07-13

### Alterado
- Anexos I a IV por lote passam a usar os nomes institucionais padronizados no menu, documento, Word, Excel e validacao de hash.
- Nome sugerido ao salvar o PDF institucional deixa de concatenar o nome do projeto; a identificacao do projeto permanece dentro do documento.

### Testes
- Suite passa a validar os quatro nomes por lote e a ausencia do projeto no titulo HTML usado pelo navegador ao salvar.

## [1.6.16] - 2026-07-13

### Adicionado
- Fluxos de assinatura paralelos ou sequenciais, com ate 20 assinantes, ordem de etapas e links individuais por token.
- Painel administrativo de pendencias com pesquisa e filtros por projeto, secretaria, situacao e tipo de fluxo.
- Multiplos comprovantes por assinatura, armazenados de forma privada com metadados e hash SHA-256.
- Endpoint autenticado para download de assinatura e evidencias por usuarios com permissao de confirmacoes.
- Validacao do hash individual da assinatura na pagina central de autenticidade documental.

### Alterado
- Login passa a usar o mesmo brasao municipal definido por `MUNICIPAL_LOGO_PATH`.
- Demanda passa a exibir fluxo, etapa, prazo, evidencias e hash individual de cada assinante.
- Fluxo sequencial libera automaticamente o proximo assinante depois da conclusao da etapa anterior.
- `database/schema.sql` passa a criar, de forma idempotente, `demand_signature_flows` e `demand_confirmation_attachments`.

### Seguranca
- Cada assinatura incorpora ao hash o snapshot da demanda, os dados declarados, a assinatura desenhada e os hashes de todos os comprovantes.
- Evidencias permanecem fora do diretorio publico e sao servidas com bloqueio de cache e controle de permissao.

### Testes
- Suite cobre modos de fluxo, ordem de liberacao, expiracao, integridade do hash, schema, rotas privadas e uso do brasao municipal no login.

## [1.6.15] - 2026-07-13

### Corrigido
- Links de assinatura de demanda deixam de depender do acesso direto a um novo arquivo PHP pelo Nginx.
- Gateway publico pelo index reconhece somente a acao de assinatura acompanhada de token, mantendo a validacao integral da solicitacao.

### Mantido
- Endpoint direto de assinatura continua aceito para compatibilidade com links ja emitidos.

### Testes
- Suite passa a validar a geracao do link publico e o bloqueio do gateway quando o token estiver ausente.

## [1.6.14] - 2026-07-13

### Corrigido
- Titulos, cabecalhos e rotulos dos Anexos I e II por item deixam de exibir caracteres corrompidos por conversoes incorretas de Unicode.
- Textos antigos com mojibake nas especificacoes sao reparados somente durante a apresentacao dos anexos.
- Modelos padrao de observacoes para produtos e servicos passam a ser gerados corretamente em UTF-8.

### Alterado
- Anexos I e II por item e por lote passam a apresentar descricao minima, caracteristicas minimas, criterios de aceitacao, certificados minimos, observacoes e garantia em secoes legiveis.
- Caracteristicas e demais campos multivalorados passam a usar listas nao ordenadas.
- Garantia passa a integrar a consulta consolidada e as especificacoes dos Anexos II passam a compor o hash documental.

### Testes
- Suite de relatorios passa a validar secoes, listas, garantia e recuperacao de mojibake nas especificacoes dos anexos.

## [1.6.13] - 2026-07-13

### Adicionado
- Comparativo anual de precos em /annual_price_comparison.php, com filtros por periodo, item, fornecedor, categoria e secretaria.
- Graficos de tendencia mensal, media movel de tres meses, resumo anual e evolucao das principais series por dimensao.
- Indicadores de media, mediana, moda, menor/maior valor, desvio padrao, coeficiente de variacao e outliers.
- Exportacao Excel do conjunto filtrado, incluindo resumo anual, serie mensal, agrupamentos e observacoes detalhadas.

### Alterado
- Gestao de Projetos passa a oferecer acesso direto ao comparativo anual sob a permissao bi.view.
- Historico deduplica copias e reaproveitamentos do mesmo orcamento para evitar distorcao estatistica.
- Schema recebe indices para consultas historicas por data, fornecedor, item e categoria.

### Testes
- Suite do BI passa a cobrir filtros, deduplicacao, agrupamento anual, media movel e identificacao de outliers por item/ano.


## [1.6.12] - 2026-07-08

### Adicionado
- Autenticacao via AD/LDAP configuravel por `.env`, com busca por `sAMAccountName`, UPN ou e-mail e mapeamento de grupos para perfis do sistema.
- Espelhamento automatico de usuarios LDAP em `app_users`, mantendo permissoes locais, auditoria e fallback administrativo local.
- Diagnostico LDAP em `/environment_diagnostics.php`, com validacao de extensao PHP, host, porta, bind de servico e busca opcional por login.

### Alterado
- Login passa a tentar LDAP quando habilitado e, se configurado, cair para autenticacao local do administrador.
- README e `.env.example` passam a documentar as variaveis LDAP, grupos/perfis e validacao operacional.

### Seguranca
- Falhas de autenticacao LDAP/local passam a ser registradas em `storage/logs/app.log` sem expor senhas.

### Testes
- Suite de autenticacao passa a cobrir configuracao LDAP, escape de filtro, normalizacao de usuario e mapeamento de grupos para perfis.
## [1.6.11] - 2026-07-08

### Alterado
- Orcamento geral do projeto passa a bloquear campos de valor unitario e observacao dos itens enquanto nenhum fornecedor estiver selecionado, evitando perda de dados no recarregamento da tela.

## [1.6.10] - 2026-07-08

### Documentacao
- `docs/features.md` foi revisado para remover funcionalidades ja implementadas e registrar novas sugestoes consultivas com prompts de solicitacao futura.
## [1.6.9] - 2026-07-08

### Alterado
- Orcamento geral do projeto passa a usar busca de fornecedor com dropdown filtravel por nome, CNPJ, contato, e-mail, cidade e porte.
- Metadados de numero, data e validade do orcamento foram concentrados nos documentos do orcamento, evitando preenchimento duplicado fora da secao.

### Corrigido
- Salvamento do orcamento geral preserva numero, data e validade informados no primeiro documento mesmo quando nao houver arquivo anexado.

## [1.6.8] - 2026-07-08

### Alterado
- Pagina Dados passa a listar os escopos atuais de importacao/exportacao JSON com descricao, tabelas principais, avisos e atalhos de exportacao/template.
- Seletores de exportacao e importacao agora exibem ajuda contextual do escopo escolhido.

## [1.6.7] - 2026-07-07

### Adicionado
- DOD da Compra Direta passa a usar variaveis de ambiente para entidade, estado, local, CNPJ e logos institucionais padrao.
- DOD passa a aceitar logos adicionais por departamento e assinaturas vinculadas a colaboradores cadastrados ou preenchidas manualmente.
- Unidades administrativas passam a armazenar endereco, CEP, telefone, ramal e e-mail para preenchimento do rodape institucional.
- Colaboradores passam a ter vinculo com unidade administrativa, ramal e WhatsApp.
- Exportacao/importacao JSON passa a incluir contatos das unidades administrativas e colaboradores no escopo de secretarias/unidades.

### Alterado
- Menu e telas principais passam a adotar a nomenclatura Unidade Administrativa/Secretaria no lugar de demandantes.
- Cadastro de colaboradores foi reorganizado em secoes de identificacao, lotacao administrativa e contato.
- Exportacao do DOD passa a preencher o rodape com dados da unidade administrativa vinculada a demanda.
## [1.6.6] - 2026-07-07

### Corrigido
- Textos automaticos do DOD da Compra Direta deixam de gerar acentuacao corrompida em trechos como contratacao, orcamento, numero e Oficio.
- Geracao textual do DOD passa a usar escapes Unicode seguros em tempo de execucao, evitando nova gravacao com mojibake.

### Testes
- Teste do DOD passa a validar explicitamente que a estimativa de valor nao contem sequencias corrompidas e preserva acentuacao.
## [1.6.5] - 2026-07-07

### Adicionado
- DOD da Compra Direta passa a gerar automaticamente os topicos de estimativa de quantidades, estimativa de valor e impactos ambientais a partir das demandas, orcamentos e itens do projeto.
- Rodape do DOD passa a aceitar multiplas assinaturas configuraveis e numeracao opcional de paginas.
- Tela do DOD recebe editor simples para negrito, italico, listas e listas numeradas nos topicos editaveis.
- Teste automatizado para conteudos automaticos, valor por extenso, deduplicacao de impactos e renderizacao do DOD.

### Alterado
- Exportacao do DOD passa a usar cabecalho e rodape institucionais com logos, brasao central, faixas vermelho/azul/amarelo e dados de contato.
- Local padrao do DOD passa a ser Espirito Santo do Turvo - SP e os numeros de topicos sao exibidos com ponto apos o numero.

### Corrigido
- Mensagem de alerta de preco discrepante passa a ser gerada com acentuacao correta.

## [1.6.4] - 2026-07-07

### Alterado
- Dashboard passa a calcular valores estimados por agregacao SQL com media dos orcamentos e referencias historicas selecionadas, reduzindo consultas repetidas.
- Controle de anexos do Dashboard passa a consultar as versoes registradas em `project_annex_versions`, evitando recalculo de hashes de todos os anexos a cada carregamento.
- Gestao de projetos passa a usar o projeto selecionado como escopo real dos indicadores, graficos, ranking de fornecedores e tabela de projetos.

### Adicionado
- Gestao de projetos passa a exibir analise global do projeto selecionado, indicando itens sem orcamento, com menos de tres fontes, possiveis outliers e alta divergencia.
- Teste automatizado para estatisticas e deteccao de outliers usadas no BI.

## [1.6.3] - 2026-07-07

### Adicionado
- Pagina administrativa de diagnostico do ambiente com status do PostgreSQL, versao do PHP, extensoes, configuracoes principais e permissao de escrita no storage.
- Pagina administrativa de logs lendo `storage/logs`, com filtros por data, nivel, usuario, rota, arquivo e mensagem.
- Suite automatizada em PHP puro com runner `php tests/run.php`, cobrindo autenticacao, repository, medias, relatorios, bloqueio de projeto fechado, hash de documentos, importacao/exportacao e logs.
- Logs novos passam a registrar automaticamente rota, URI, metodo e usuario autenticado quando disponiveis.

### Corrigido
- Criacao do primeiro administrador deixa de falhar por chamada a funcao inexistente `auth_pg_bool`.
- Schema separa corretamente o `setval` de `app_users` e `categories` em comandos distintos.
- Menu administrativo passa a filtrar grupos e itens conforme permissoes do usuario autenticado.

## [1.6.2] - 2026-07-06

### Adicionado
- Sistema de autenticacao com login, logout, sessao segura e criacao assistida do primeiro administrador em `/setup_admin.php`.
- Cadastro administrativo de usuarios com perfis Administrador, Gestor, Operador e Consulta.
- Controle de permissoes por perfil para usuarios, dados, catalogo, projetos, orcamentos, fornecedores, demandantes, confirmacoes, relatorios, BI, hashes e IA.
- Area de perfil para o usuario autenticado trocar a propria senha.
- Schema passa a incluir a tabela `app_users`, indices, constraint de perfil e trigger de atualizacao.

### Alterado
- Todas as paginas internas passam a exigir login, mantendo publico apenas login, logout, criacao inicial do administrador e assinatura por token de demanda.
- Menu principal passa a exibir opcoes conforme as permissoes do perfil autenticado.
- README passa a documentar o primeiro acesso administrativo e os perfis de usuario.

### Seguranca
- Rotas administrativas validam permissao no servidor, inclusive em acesso direto por URL.
- O sistema impede desativar o proprio usuario e impede deixar a aplicacao sem administrador ativo.

## [1.6.1] - 2026-07-06

### Adicionado
- Cadastro de colaboradores para reutilizar responsaveis, requisitantes e tecnicos nas confirmacoes formais de demanda.
- Fluxo de confirmacao formal da demanda por link com token, assinatura em canvas para uso no celular e upload de documento de comprovacao.
- Painel na demanda para acompanhar solicitacoes pendentes, assinadas, expiradas ou revogadas, com hash da confirmacao quando assinada.
- Schema passa a incluir as tabelas `collaborators` e `demand_confirmation_requests`, com snapshot JSON, hash, metadados de assinatura e arquivos privados em storage.

### Alterado
- Menu Cadastros passa a incluir Colaboradores.
- README passa a documentar o armazenamento privado das evidencias de confirmacao de demanda.

### Seguranca
- Documento pessoal e assinatura ficam armazenados em `storage/uploads/demand_confirmations/` e nao sao expostos por endpoint publico direto enquanto o sistema nao possuir login/perfis.

## [1.6.0] - 2026-07-06

### Adicionado
- Modalidade do projeto passa a diferenciar Licitacao e Compra Direta.
- Compra Direta passa a contar com DOD configuravel, com cabecalho, rodape, topicos habilitaveis, reordenacao, textos padrao, prompt de apoio por IA e exportacao para visualizacao/PDF e Word.
- Demandas de Compra Direta passam a destacar requisitante e campo de cotador.
- Orcamento do projeto passa a exibir apuracao da Compra Direta por menor valor global ou menor valor por item.
- Schema passa a incluir campos de Compra Direta no projeto, campo de cotador na demanda e tabela `direct_purchase_dod_documents`.

### Alterado
- Sistema passa a ser identificado como Sistema Interno de Compras e Licitacoes.
- Tela do projeto separa acoes de Licitacao e Compra Direta, ocultando anexos licitatorios quando a modalidade for Compra Direta.
- Listagem de projetos passa a exibir a modalidade do projeto.

## [1.5.33] - 2026-07-03

### Adicionado
- Observacoes padrao especificas para itens de servico, com foco em execucao, qualificacao, normas aplicaveis, garantia e suporte quando cabivel.

### Alterado
- Formulario de itens passa a trocar o modelo de especificacao tecnica conforme o tipo de unidade selecionado, preservando campos ja preenchidos e substituindo apenas observacoes padrao conhecidas.

### Corrigido
- Itens de servico deixam de receber observacoes padrao de produtos no JSON de especificacao tecnica.

## [1.5.32] - 2026-07-03

### Corrigido
- Criacao e edicao de projetos deixam de reutilizar o parametro `:status` em expressoes `CASE`, evitando erro de parametro ambiguo no PostgreSQL ao salvar novos projetos.
## [1.5.31] - 2026-07-02

### Adicionado
- Orcamento geral do projeto passa a permitir varios documentos do mesmo fornecedor, cada um com numero, data, validade, arquivo e observacao proprios.
- Tabela `demand_supplier_quote_attachments` passa a armazenar os documentos dos orcamentos com metadados individuais.

### Alterado
- Telas de demanda, orcamento da demanda e orcamentos do projeto passam a exibir todos os documentos vinculados ao orcamento do fornecedor.
- Clonagem de projetos e exportacao/importacao JSON passam a contemplar documentos multiplos dos orcamentos.

## [1.5.30] - 2026-07-02

### Adicionado
- Cadastro de fornecedores passa a registrar capital social, situacao especial e data da situacao especial.
- Schema passa a conter tabela de referencia `cnae_references`, alimentada com os CNAEs do arquivo `external/br_bd_diretorios_brasil_cnae_2.csv`.
- Formulario de fornecedores passa a ter busca de CNAE principal e secundario usando a tabela de referencia.
- Novo endpoint `/cnae_lookup.php` para consulta AJAX de CNAEs por codigo ou descricao.

### Alterado
- Consulta de CNPJ passa a tentar preencher e-mail, capital social, situacao especial, data da situacao especial e enriquecer CNAEs pela referencia local.
- Listagem, pesquisa e exportacao/importacao JSON de fornecedores passam a contemplar os novos campos fiscais.

## [1.5.29] - 2026-06-26

### Corrigido
- Tela de visualizacao do projeto passa a separar a barra de acoes do titulo, evitando que os menus ocupem a mesma linha do nome do projeto.
- Barra de acoes do projeto recebeu layout proprio e comportamento responsivo mais previsivel.

## [1.5.28] - 2026-06-26

### Adicionado
- Nova area Gestao de projetos no menu principal, com visao de BI governamental para projetos, fornecedores, itens e valores.
- Filtros por texto, status, projeto e item para analise administrativa dos projetos.
- Graficos de valor estimado por projeto, projetos por status, fornecedores mais presentes e comparativo de precos por fornecedor do item.
- Indicadores estatisticos por item com media, mediana, moda, menor valor, maior valor, desvio padrao e coeficiente de variacao.
- Achados administrativos com alertas para ausencia de cotacoes, poucos fornecedores, alta dispersao e possiveis outliers.

## [1.5.27] - 2026-06-26

### Adicionado
- Relatorio do projeto passa a ter a aba Consolidado por denominacao, agrupando itens por lote/denominacao com quantidade e total estimado.
- Orcamento geral do projeto passa a contar com Banco de precos de orcamentos gerais, permitindo carregar orcamentos historicos compativeis de outros projetos.

### Alterado
- Formulario de orcamento geral passa a aceitar valores carregados do banco global e preserva a origem historica dos precos reaproveitados.
- Menu Fornecedor do projeto passa a incluir acesso ao banco de precos de orcamentos gerais.

## [1.5.26] - 2026-06-26

### Adicionado
- Listagem de fornecedores passa a ter barra de pesquisa e filtros por status, participacao em licitacao, UF e porte da empresa.
- Solicitacao formal ao fornecedor passa a ter PDF, Word e Excel filtrados por denominacao de lote.
- Solicitacao formal ao fornecedor passa a ter exportacoes separadas por denominacao do projeto.
- Nova tela para escolher a denominacao e baixar os arquivos filtrados para fornecedor.

### Alterado
- Menu de licitacao do projeto foi dividido em Anexos por item, Anexos por lote e Fornecedor, reduzindo a poluicao visual.

## [1.5.25] - 2026-06-26

### Adicionado
- Cadastro de fornecedores passa a incluir Inscricao Estadual, Inscricao Municipal, porte da empresa, participacao em licitacao, URL do site, CNAE principal e CNAEs secundarios.
- Orcamentos individuais e gerais passam a registrar quem realizou a cotacao pelo fornecedor e quem coletou a cotacao na prefeitura.

### Alterado
- Cadastro de fornecedores foi reorganizado em secoes para separar identificacao fiscal, contato, endereco, CNAE, dados bancarios e observacoes.
- Endereco, cidade e UF de fornecedores passam a ser padronizados em maiusculas no cadastro e no preenchimento por API.
- Consulta por CNPJ passa a preencher porte, CNAE principal e CNAEs secundarios quando a API publica retornar esses dados.
- Exportacao/importacao JSON passa a contemplar os novos campos cadastrais de fornecedores e responsaveis dos orcamentos.
- Listagem de fornecedores e telas de orcamento passam a exibir os novos dados cadastrais e responsaveis da cotacao.

## [1.5.24] - 2026-06-25

### Adicionado
- Status Cancelado para projetos, com justificativa obrigatoria, bloqueio de alteracoes, invalidacao dos anexos e novo hash do projeto.
- Status Reaberto para projetos cancelados, com justificativa, tipo de reabertura, prazo de correcao e fechamento automatico quando o prazo expira.
- Historico auditavel de eventos de status, com snapshot JSON e hash proprio para cancelamentos, reaberturas e fechamentos automaticos.
- Pagina consultiva de orcamentos do projeto no menu Licitacao.
- Opcao para copiar denominacoes e vinculos de lotes a partir de outro projeto.

### Alterado
- Bloqueios de edicao passam a considerar projetos fechados e cancelados.
- Validacao de hash passa a reconhecer eventos de status do projeto.

## [1.5.23] - 2026-06-25

### Corrigido
- Clonagem de projetos passa a copiar demandas, itens, numeracao de licitacao, denominacoes de lote, vinculos, orcamentos de fornecedores, itens cotados e referencias de banco de precos.
- Exportacoes Word e Excel passam a emitir BOM UTF-8 e os exports antigos foram alinhados ao helper de download para evitar mojibake em textos acentuados no Office.

## [1.5.22] - 2026-06-18

### Alterado
- Relatorios de demanda passam a ter modos separados para demanda com preco e sem preco: por unidade, por unidade filtrada, por secretaria, por secretaria filtrada e por secretaria filtrada detalhando suas unidades.
- Menu de relatorios do projeto reorganizado para expor os relatorios consolidados sem filtro e os relatorios filtrados de forma individual.

## [1.5.21] - 2026-06-17

### Adicionado
- Relatorios de demanda por unidade e por secretaria, com versoes sem precos e com precos, exportaveis em PDF, Word e Excel.
- Status "Retificacao" para projetos fechados que precisem voltar a permitir correcoes.
- Hash de fechamento do projeto ao marcar o status como Fechado.
- Pagina administrativa para validar hashes de anexos e fechamentos de projeto.

### Alterado
- Projetos fechados passam a bloquear alteracoes em demandas, itens, orcamentos, banco de precos, numeracao de licitacao e denominacoes de lote.
- Telas de projeto, demanda, orcamento, numeracao e lotes passam a exibir avisos de somente leitura quando o projeto esta fechado.
- Ao fechar um projeto, os status Rascunho, Coletando demandas e Em revisao ficam indisponiveis; permanecem apenas Fechado e Retificacao.
- Relatorios filtrados de demanda por unidade/secretaria passam a abrir com seletor, permitindo gerar PDF, Word e Excel apenas para a unidade ou secretaria escolhida.

## [1.5.20] - 2026-06-16

### Adicionado
- Dashboard administrativo com indicadores de fornecedores, secretarias, unidades demandantes, anexos pendentes e projetos recentes.
- Mascaras de CPF/CNPJ, telefone, CEP, CPF do proprietario e UF no cadastro de fornecedores.

### Alterado
- Projetos passam a exibir status traduzido e com cores na listagem, visualizacao e dashboard.
- Cadastro de fornecedor passa a normalizar e validar CPF/CNPJ, telefone, e-mail, UF e CEP antes de salvar.

## [1.5.19] - 2026-06-16

### Adicionado
- Anexos de licitacao passam a permitir alterar a data de emissao antes de imprimir/salvar PDF, mantendo a mesma data nos links de Word e Excel.

### Alterado
- Cabecalho dos anexos passa a exibir "Versao do documento" no lugar de "Versao".

## [1.5.18] - 2026-06-16

### Adicionado
- Anexo IV por lote - Quadro resumido da estimativa de precos, com lote, denominacao, itens integrantes e valor estimado do lote.

### Alterado
- Tela de denominacoes de lotes passa a exibir apenas a listagem e acoes principais.
- Cadastro/edicao da denominacao e gerenciamento de vinculos de produtos/categorias foram separados em paginas proprias, melhorando a leitura e reduzindo a poluicao visual.

## [1.5.17] - 2026-06-16

### Alterado
- Anexo III por lote passa a exibir somente lote, denominacao, itens integrantes e justificativa do agrupamento.
- Versionamento do Anexo III por lote passa a considerar apenas as informacoes exibidas no quadro de agrupamento.

## [1.5.16] - 2026-06-16

### Alterado
- Tela de denominacoes de lotes reorganizada em areas separadas para cadastro/edicao, vinculo de produtos/categorias e listagem dos vinculos.
- Anexo II por lote passa a empilhar valores dos fornecedores em coluna propria, evitando sobreposicao visual com valor unitario e total estimado.

## [1.5.15] - 2026-06-16

### Adicionado
- Cadastro de denominacoes de lotes por projeto, com numero do lote, nome e justificativa.
- Vinculo de denominacoes por produto especifico ou por categoria/subcategoria do produto.
- Alternativa de licitacao por lote com anexos I, II e III proprios.
- Anexo I por lote com divisao por numero do lote e denominacao.
- Anexo II por lote com numero do lote, denominacao, item, especificacao, unidade, quantidade, memoria de calculo, fornecedores, valores e subtotal por lote.
- Anexo III por lote com quadro resumido separado por lote e valor global estimado.

### Alterado
- Menu de licitacao do projeto passa a exibir os nomes por extenso dos anexos I, II e III.
- Controle de versoes/hash dos anexos passa a contemplar tambem os anexos por lote.
- Exportacao/importacao JSON de projetos passa a contemplar denominacoes e vinculos de lotes.

## [1.5.14] - 2026-06-16

### Adicionado
- Numeracao de licitacao por item consolidado do projeto, com tela para ordenar ou renumerar os itens.
- Controle de versoes dos anexos I, II e III com hash de validacao e status de regeneracao.
- Anexo III - Quadro resumido da estimativa de precos, exportavel em PDF institucional, Word e Excel.

### Alterado
- Anexos I e II passam a usar a mesma numeracao/ordenacao de itens.
- Anexo II passa a exibir "Fornecedores consultados" e remove subtotal quando existir apenas um grupo.
- Exportacao/importacao JSON passa a contemplar numeracao de licitacao e versoes de anexos.

### Corrigido
- Orcamento geral deixa de criar cotacoes vazias por demanda sem preco, anexo ou metadados.
- Upload de anexo do orcamento geral so ocorre apos validacoes basicas do formulario.
- Checkbox de fornecedor ativo passa a tratar corretamente valores booleanos vindos do PostgreSQL.

## [1.5.13] - 2026-06-16

### Adicionado
- Formulario de orcamento geral passa a exibir o valor total do orcamento do fornecedor selecionado.
- Valor total do orcamento geral passa a atualizar automaticamente conforme os precos unitarios sao digitados.

## [1.5.12] - 2026-06-16

### Adicionado
- Formulario de orcamento geral passa a avisar quando o fornecedor selecionado ja possui orcamento no projeto.
- Orcamento geral passa a exibir link para visualizar o anexo digital ja enviado pelo fornecedor.
- Opcao para remover ou substituir o anexo atual ao salvar o orcamento geral.

### Alterado
- Salvamento do orcamento geral agora diferencia manter, substituir ou remover o anexo existente.

## [1.5.11] - 2026-06-16

### Adicionado
- Consulta de CEP no cadastro de fornecedores para preencher endereco, cidade e UF.
- Endpoint protegido para servir anexos de orcamentos salvos em storage.
- Teste para montagem de endereco a partir dos retornos das APIs de CNPJ e CEP.

### Alterado
- Upload de anexos de orcamentos passa a gravar em storage/uploads/supplier_quotes, evitando dependencia de escrita em public/uploads.
- Consulta de CNPJ passa a aceitar diferentes nomes de campos de logradouro retornados pela API.

### Corrigido
- Mensagem e log de falha de upload agora indicam permissao de escrita na pasta de uploads quando o servidor bloqueia a gravacao.

## [1.5.10] - 2026-06-16

### Adicionado
- Anexo II passa a exibir a data da proposta nas informacoes dos fornecedores.
- Alerta de possivel preco discrepante no Anexo II, exigindo analise e justificativa antes de exclusao.
- Testes para media aritmetica simples, arredondamento monetario e sinalizacao de outliers.

### Alterado
- Anexo II padronizado como "Anexo II ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ Planilha de Pesquisa e Estimativa de PreÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â§os".
- Calculo do Anexo II passa a usar media aritmetica simples dos precos validos, arredondando o valor unitario estimado antes do total.
- Anexo II passa a separar quantidade e unidade em colunas distintas.
- Resumo financeiro do projeto passa a usar o mesmo valor global estimado calculado para o Anexo II.

## [1.5.9] - 2026-06-16

### Adicionado
- Campo de pesquisa rapida nas demandas do projeto.
- Testes em PHP puro para os calculos do Anexo II e do valor global estimado.

### Alterado
- Demandas do projeto passam a ser exibidas como cards responsivos.
- Calculo do Anexo II passa a usar medias ponderadas por quantidade e a mesma base de calculo do resumo financeiro do projeto.

### Corrigido
- Resumo financeiro passa a manter itens sem cotacao no total estimado usando a estimativa manual quando outros itens da demanda possuem orcamento.

## [1.5.8] - 2026-06-16

### Adicionado
- Cadastro de fornecedores passa a aceitar nome fantasia, cidade, UF, CEP, dados bancarios e dados do proprietario.
- Anexo II passa a exibir CNPJ, razao social, nome fantasia, endereco, contato, e-mail e telefone dos fornecedores cotantes.

### Alterado
- Demandas no projeto passam a ser exibidas em lista responsiva com acoes sempre visiveis.

## [1.5.7] - 2026-06-16

### Adicionado
- Menu Licitacao no projeto com Anexo I e Anexo II exportaveis em PDF institucional, Word e Excel.
- Anexo I com itens sequenciais, especificacao tecnica, unidade com conteudo, quantitativo e memoria de calculo por demanda.
- Anexo II com comparativo de fornecedores por item, media de valor unitario, valor total e valor global estimado, separando itens por combinacao de fornecedores cotantes.

### Alterado
- Acoes do projeto foram reorganizadas para reduzir a quantidade de botoes soltos no topo e melhorar a responsividade.

## [1.5.6] - 2026-06-13

### Adicionado
- Lancamento de orcamento geral do projeto por fornecedor em lista consolidada por produto, distribuindo os valores para todas as demandas vinculadas.

### Alterado
- Tela do projeto ficou mais responsiva em desktops medios, telas pequenas e mobile, com cabecalho, acoes, tabelas e abas mais adaptaveis.
- Resumos financeiros do projeto, consolidacao geral, resumo por secretaria e detalhamento por demanda passam a usar medias calculadas dos orcamentos quando disponiveis.

### Corrigido
- Relatorios gerencial, PDF institucional e Word do projeto passam a exibir valor medio unitario e total estimado calculados, com indicacao quando o valor vem de media de orcamento.

## [1.5.5] - 2026-06-10

### Adicionado
- Exportacao Excel geral separado por grupo para a solicitacao de orcamento do projeto.
- Exportacao Excel individual por grupo/categoria a partir do menu de Excel do fornecedor no projeto.

### Alterado
- Menu principal passa a agrupar cadastros e administracao em dropdowns, reduzindo a quantidade de itens no header.
- Acoes do projeto foram reorganizadas em menus de relatorios e Excel do fornecedor, removendo exportacoes repetidas na tabela de demandas.

## [1.5.4] - 2026-06-10

### Adicionado
- Relatorio de solicitacao formal de orcamento para fornecedores a partir do projeto, consolidando as demandas por item com codigo, item, marca/modelo de referencia, caracteristicas minimas, tipo de unidade, conteudo, unidade do conteudo e quantidade.
- Exportacao Excel da solicitacao de orcamento do projeto com campos de preenchimento para o fornecedor informar marca/modelo ofertado, valor unitario, valor total, prazo, validade e observacoes.

## [1.5.3] - 2026-06-09

### Alterado
- Status dos itens agora usam cores semanticas nas listagens, dashboard, visualizacao do item e versoes.

### Corrigido
- PDF institucional do projeto deixa de ser servido como arquivo Word e passa a abrir como pagina HTML para imprimir/salvar PDF.
- PDFs de catalogo e demanda passam a enviar `Content-Type: text/html` explicitamente.

## [1.5.2] - 2026-06-09

### Adicionado
- Campos de conteudo da embalagem e unidade do conteudo no cadastro de itens.
- Exibicao do conteudo da embalagem em listagens, visualizacao do item, demandas e relatorios PDF/Word.
- Versionamento de itens passa a preservar e restaurar a composicao da embalagem.
- Exportacao/importacao JSON de itens passa a incluir os novos campos de composicao.

## [1.5.1] - 2026-06-09

### Adicionado
- Log interno da aplicacao em `storage/logs/app.log` e log nativo do PHP em `storage/logs/php-error.log`.
- Cadastro de subunidades/departamentos vinculados a uma unidade demandante principal.
- Exportacao/importacao JSON de unidades demandantes passa a incluir `parent_id`.

### Alterado
- Demandas e listagens passam a exibir unidade/subunidade em formato composto quando houver unidade pai.
- Schema de demandantes foi ajustado para permitir nomes repetidos em unidades pai diferentes.

### Corrigido
- Tela de demanda registra falhas de schema pendente e evita quebra em recursos opcionais de orcamento ainda nao aplicados.

## [1.5.0] - 2026-06-09

### Adicionado
- Banco de Precos por demanda para selecionar manualmente orcamentos historicos do mesmo item.
- Media da demanda passa a considerar os precos historicos selecionados pelo usuario.
- Relatorio de orcamento geral exibe os precos historicos selecionados e suas origens.
- Exportacao/importacao JSON passa a incluir referencias historicas de precos.

## [1.4.2] - 2026-06-09

### Adicionado
- Reaproveitamento de precos ja cotados em outras demandas do mesmo projeto para itens com a mesma especificacao cadastrada.
- Rastreabilidade da origem do preco reaproveitado no item de orcamento.

### Alterado
- Formulario de orcamento passa a sugerir precos existentes do mesmo projeto e filtra sugestoes pelo fornecedor selecionado.
- Relatorio de orcamento geral passa a indicar a origem dos precos reaproveitados.

## [1.4.1] - 2026-06-09

### Adicionado
- Consulta de CNPJ no cadastro de fornecedores usando BrasilAPI para preencher razao social, contato e endereco.

### Alterado
- Itens demandados passam a exibir valor unitario medio e total medio estimado com base nos orcamentos dos fornecedores.
- Relatorios Word/PDF da demanda passam a usar o valor medio geral quando houver cotacoes.
- Orcamentos marcados como desconsiderados deixam de compor a media do orcamento geral.

### Corrigido
- Corrigido erro no resumo financeiro de projetos causado por chamada indevida do calculo de orcamento individual da demanda.

## [1.4.0] - 2026-06-08

### Adicionado
- Cadastro de fornecedores com dados de contato, documento, observacoes e status.
- Vinculo de fornecedores a demandas por meio de orcamentos, com anexo opcional do orcamento real.
- Lancamento de valores unitarios dos fornecedores para cada item da demanda.
- Relatorio de orcamento geral da Prefeitura com matriz comparativa, media por item e valor medio geral.
- Exportacao/importacao JSON passa a contemplar fornecedores e orcamentos das demandas.

## [1.3.1] - 2026-06-08

### Alterado
- Demandas cadastradas agora podem ter unidade/setor demandante, secretaria, responsavel e observacoes editados sem recriacao.
- Campo de busca para adicionar itens ao kit agora recebe foco automaticamente ao abrir o kit.

### Corrigido
- Corrigido o envio de campos booleanos para PostgreSQL ao editar unidades demandantes desativadas, secretarias, bibliotecas e kits.

## [1.3.0] - 2026-06-08

### Adicionado
- Cadastro de secretarias e unidades/setores demandantes com responsavel padrao.
- Vinculo de demandas a unidade/setor demandante e secretaria.
- Autopreenchimento da secretaria e do responsavel no cadastro da demanda ao selecionar a unidade/setor.
- Resumo por secretaria no relatorio do projeto e nas exportacoes institucionais.

### Alterado
- Demandas passam a exibir secretaria, unidade/setor e responsavel de forma estruturada.
- Exportacao/importacao JSON passa a incluir secretarias, unidades demandantes e os novos vinculos das demandas.

## [1.2.1] - 2026-06-08

### Alterado
- Biblioteca de impactos ambientais agora e listada pelo codigo (`IA001`, `IA002`, etc.) antes do titulo, facilitando a selecao no cadastro de itens.

## [1.2.0] - 2026-06-08

### Adicionado
- Exibicao legivel das especificacoes tecnicas em blocos, tabelas e listas, sem expor JSON nas telas de leitura e exportacoes.
- Estilos especificos para especificacoes tecnicas estruturadas na interface web, Word e PDF.

### Alterado
- Exportacoes Word e PDF reformuladas com cabecalho institucional, resumo da emissao, metadados em tabela e melhor hierarquia visual.
- Responsividade geral aprimorada para mobile, com botoes empilhados, tabelas com rolagem previsivel, inputs mais confortaveis e cards mais consistentes.

## [1.1.2] - 2026-06-03

### Corrigido
- Especificacao tecnica agora e exibida no formulario, visualizacao, versoes e exportacoes mantendo a ordem padrao das chaves, com `observacoes` ao final.
- Formatacao da especificacao deixa de depender da ordem retornada pelo tipo `JSONB` do PostgreSQL.

## [1.1.1] - 2026-06-03

### Corrigido
- Corrigido o carregamento de dados nas telas de visualizar e editar item, evitando conflito entre a variavel do produto e a variavel de navegacao do cabecalho.
- Exibicao e busca do codigo de rastreio passam a usar fallback `CL000000` quando registros antigos ainda estiverem com `tracking_code` nulo.
- Novos itens agora reforcam o preenchimento do codigo de rastreio apos o cadastro, mesmo quando o trigger do banco ainda nao tiver sido aplicado.

## [1.1.0] - 2026-06-03

### Adicionado
- Ordenacao ascendente/descendente nos cabecalhos da listagem de itens.
- Padrao de especificacao tecnica com campos estruturados e observacoes obrigatorias.
- Editor de impactos ambientais em formato de lista, com selecao pela biblioteca e inclusao manual.
- Codigos reutilizaveis para impactos ambientais (`IA001` a `IA008`) no schema.
- Download de template JSON de importacao por escopo na pagina **Dados**.
- Suporte a brasao municipal nos relatorios via `MUNICIPAL_LOGO_PATH`.

### Alterado
- Importacao JSON de itens agora normaliza especificacao tecnica e impactos ambientais.
- Sugestao por IA alinhada ao novo formato de especificacao e impactos em lista.
- README atualizado com padrao de especificacao, impactos ambientais, templates JSON e brasao.

## [1.0.0] - 2026-06-03

### Adicionado
- Repositorio preparado para arquitetura com `app/`, `config/`, `public/`, `storage/`, `database/`, `.env` e README.
- Tela de dados para exportar e importar JSON por escopo: base completa, itens, projetos, categorias, tipos de unidade, kits e biblioteca.
- Schema unico em `database/schema.sql` para PostgreSQL com tabelas, indices, triggers, seeds, versionamento, kits, demandas, projetos e biblioteca.
- Busca rapida ao adicionar item em kit.
- Navegacao responsiva com Bootstrap Icons e item ativo no menu.

### Corrigido
- Geracao do codigo de rastreio dos itens no banco usando trigger `BEFORE INSERT`.
- `create_item()` corrigido para inserir colunas e valores na ordem certa.
- Duplicacao de item preservando unidade, status e imagem principal.
- Rota de itens semelhantes corrigida para `/item_similar_check.php`.

### Alterado
- Configuracao movida para `config/` com leitura de `.env`.
- README atualizado com finalidade, acesso, localizacao, tecnologia, banco, responsaveis, backup, restore e Nginx.
- SQLs avulsos consolidados em um unico schema.
