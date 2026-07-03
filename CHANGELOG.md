# Changelog

Todas as alteracoes relevantes deste sistema serao registradas aqui.

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
- Anexo II padronizado como "Anexo II – Planilha de Pesquisa e Estimativa de Preços".
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
