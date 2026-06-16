# Changelog

Todas as alteracoes relevantes deste sistema serao registradas aqui.

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
