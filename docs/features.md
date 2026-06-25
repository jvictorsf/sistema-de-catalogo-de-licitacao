# Possiveis Melhorias e Features

Este arquivo registra ideias consultivas para evolucao do Catalogo de Licitacao. Cada item possui um prompt sugerido para facilitar pedidos futuros ao Codex.

## Governanca e Acesso

### Controle de usuarios e permissoes
- Objetivo: criar perfis como administrador, tecnico, solicitante, consulta e auditor.
- Prompt sugerido: `Implemente controle de usuarios e permissoes no Catalogo de Licitacao, com perfis administrador, tecnico, solicitante, consulta e auditor. Atualize banco, telas, menus e bloqueios de acesso. Gere changelog e valide com php -l.`

### Login integrado ao AD/LDAP
- Objetivo: autenticar usuarios com credenciais institucionais.
- Prompt sugerido: `Adicione autenticacao via AD/LDAP ao sistema, mantendo fallback local para administrador. Configure por .env, documente no README e crie tela de login/logout segura.`

### Auditoria de alteracoes
- Objetivo: registrar usuario, data, tabela, acao e dados alterados.
- Prompt sugerido: `Crie auditoria de alteracoes para projetos, demandas, itens, fornecedores, orcamentos e anexos, registrando usuario, IP, data, acao e antes/depois quando aplicavel.`

### Historico de status do projeto
- Objetivo: manter trilha formal de mudancas de status.
- Prompt sugerido: `Adicione historico de status do projeto, com data, usuario, status anterior, novo status e justificativa quando for fechamento ou retificacao.`

### Workflow de retificacao com justificativa obrigatoria
- Objetivo: exigir motivo quando um projeto fechado entra em retificacao.
- Prompt sugerido: `Ao alterar projeto de Fechado para Retificacao, exija justificativa obrigatoria, registre no historico e mostre aviso no projeto e nos anexos gerados durante a retificacao.`

## Demandas e Aprovacoes

### Fluxo de aprovacao de demandas
- Objetivo: permitir que setores enviem demandas para revisao tecnica antes da consolidacao.
- Prompt sugerido: `Crie fluxo de aprovacao para demandas, com status rascunho, enviada, em analise, aprovada, recusada e ajustes solicitados. Bloqueie edicoes conforme o status.`

### Relatorio de demandas pendentes por unidade
- Objetivo: identificar unidades que ainda nao finalizaram suas demandas.
- Prompt sugerido: `Crie relatorio de demandas pendentes por unidade e secretaria, com filtros por projeto, status e responsavel, exportavel em PDF, Word e Excel.`

### Divergencia entre quantidade solicitada e aprovada
- Objetivo: evidenciar cortes ou ajustes de quantidade.
- Prompt sugerido: `Adicione relatorio de divergencia entre quantidade solicitada e aprovada, agrupado por secretaria, unidade e item, com justificativa quando existir.`

### Bloqueio de exclusao para registros usados
- Objetivo: evitar perda de historico quando item, fornecedor ou unidade ja foi usado.
- Prompt sugerido: `Substitua exclusoes definitivas por inativacao quando registros ja estiverem vinculados a projetos, demandas, orcamentos ou anexos.`

## Orcamentos e Precos

### Importacao de planilha preenchida pelo fornecedor
- Objetivo: permitir importar os precos de Excel enviado ao fornecedor.
- Prompt sugerido: `Implemente importacao da planilha de orcamento preenchida pelo fornecedor, validando codigo/item, quantidade, unidade, valor unitario e observacoes antes de salvar.`

### Excel com validacao de celulas
- Objetivo: reduzir erros no preenchimento pelo fornecedor.
- Prompt sugerido: `Melhore o Excel enviado ao fornecedor com instrucoes, colunas protegidas, validacao de valores numericos e destaque para campos obrigatorios.`

### Relatorio de itens sem orcamento
- Objetivo: localizar itens ainda sem pesquisa de preco.
- Prompt sugerido: `Crie relatorio de itens sem orcamento por projeto, demanda, secretaria, unidade, categoria e lote, com exportacao PDF, Word e Excel.`

### Relatorio de itens com apenas um fornecedor cotado
- Objetivo: apoiar saneamento da pesquisa de precos.
- Prompt sugerido: `Crie relatorio de itens com apenas um fornecedor cotado, indicando item, quantidade, fornecedor, valor e demanda de origem.`

### Comparativo visual de precos
- Objetivo: melhorar analise entre fornecedores, medias e historico.
- Prompt sugerido: `Adicione comparativo visual de precos por item, com menor preco, maior preco, media, fornecedores cotados e referencias historicas selecionadas.`

### Base de precos avancada
- Objetivo: consultar historico por periodo, fornecedor, categoria e item.
- Prompt sugerido: `Evolua a base de precos com filtros por periodo, fornecedor, item, categoria, secretaria e projeto, permitindo selecionar referencias para compor medias.`

### Relatorio de economicidade
- Objetivo: comparar valores estimados, menores precos e medias historicas.
- Prompt sugerido: `Crie relatorio de economicidade por item e por lote, comparando media estimada, menor preco cotado e referencias historicas.`

## Anexos, Documentos e Validacao

### Assinatura digital ou validacao formal
- Objetivo: dar mais confiabilidade aos documentos emitidos.
- Prompt sugerido: `Adicione suporte a assinatura digital ou validacao formal dos anexos, mantendo hash, data, versao do documento e responsavel pela emissao.`

### QR Code de validacao publica
- Objetivo: facilitar validacao dos anexos pelo hash.
- Prompt sugerido: `Inclua QR Code nos anexos apontando para a pagina de validacao de hash, com exibicao do hash completo e dados do documento validado.`

### Comparacao entre versoes de anexos
- Objetivo: ver o que mudou entre v1, v2, v3.
- Prompt sugerido: `Crie tela de comparacao entre versoes de anexos, destacando itens adicionados, removidos, alterados, mudancas de quantidade e mudancas de preco.`

### Central de documentos do processo
- Objetivo: organizar anexos, orcamentos, PDFs, atas e comprovantes.
- Prompt sugerido: `Crie uma central de documentos por projeto, com upload, categorizacao, historico, download e permissao por perfil.`

### Geracao de termo de referencia preliminar
- Objetivo: gerar um documento base a partir dos itens, justificativas, memoria e precos.
- Prompt sugerido: `Gere termo de referencia preliminar a partir do projeto, consolidando objeto, justificativa, itens, quantitativos, pesquisa de precos, criterios de aceitacao e observacoes.`

## Itens, Catalogo e Bibliotecas

### Biblioteca de especificacoes tecnicas
- Objetivo: reutilizar especificacoes por categoria, subcategoria ou tipo de produto.
- Prompt sugerido: `Crie biblioteca de especificacoes tecnicas reutilizaveis, vinculada a categoria/subcategoria, com aplicacao automatica no cadastro de item.`

### Biblioteca de justificativas por tipo de contratacao
- Objetivo: padronizar textos institucionais.
- Prompt sugerido: `Evolua a biblioteca de justificativas para separar modelos por tipo de contratacao, categoria, secretaria e natureza do item.`

### Busca global
- Objetivo: pesquisar item, projeto, fornecedor, demanda, secretaria e unidade em uma unica tela.
- Prompt sugerido: `Implemente busca global no sistema, pesquisando itens, projetos, demandas, fornecedores, secretarias, unidades e documentos.`

### Itens semelhantes e possiveis duplicidades
- Objetivo: reduzir duplicidade no catalogo.
- Prompt sugerido: `Melhore a deteccao de itens semelhantes usando nome, categoria, especificacao, unidade e codigo, com tela para comparar e decidir se deve unir ou manter separado.`

### Clonagem seletiva de projetos
- Objetivo: copiar um projeto antigo escolhendo o que reaproveitar.
- Prompt sugerido: `Melhore a duplicacao de projeto para permitir escolher se copia demandas, itens, lotes, fornecedores, orcamentos, anexos e numeros de licitacao.`

## Relatorios e Dashboard

### Pagina central de relatorios do projeto
- Objetivo: reduzir excesso de opcoes no menu.
- Prompt sugerido: `Crie uma pagina central de relatorios do projeto, organizada por abas: gerenciais, demandas, orcamentos, anexos por item, anexos por lote e fornecedor.`

### Dashboard por secretaria e unidade
- Objetivo: acompanhar volumes e valores por estrutura administrativa.
- Prompt sugerido: `Evolua o dashboard com indicadores por secretaria e unidade, mostrando quantidade de demandas, itens, valor estimado, projetos abertos e projetos fechados.`

### Alertas de anexos desatualizados
- Objetivo: chamar atencao quando itens, ordem ou precos invalidam anexos.
- Prompt sugerido: `Adicione alertas visuais no dashboard e no projeto para anexos desatualizados, com botao direto para regenerar cada anexo.`

### Filtros avancados salvos
- Objetivo: permitir que usuarios mantenham filtros frequentes.
- Prompt sugerido: `Implemente filtros avancados salvos por usuario nas telas de itens, projetos, fornecedores, demandas e relatorios.`

## Operacao e Infraestrutura

### Log de erros para administrador
- Objetivo: facilitar suporte sem depender apenas do Nginx/PHP log.
- Prompt sugerido: `Crie uma tela administrativa de logs da aplicacao, lendo storage/logs, com filtros por data, nivel, usuario, rota e mensagem.`

### Pagina de diagnostico do ambiente
- Objetivo: verificar banco, storage, extensoes PHP e permissoes.
- Prompt sugerido: `Crie pagina de diagnostico do ambiente com status do PostgreSQL, permissao de escrita no storage, extensoes PHP, versao do PHP e configuracoes principais.`

### Migracoes incrementais
- Objetivo: substituir schema unico por historico de migrations.
- Prompt sugerido: `Organize o schema em migrations incrementais versionadas, mantendo compatibilidade com o schema atual e instrucoes de atualizacao segura para producao.`

### Seeds separados dos dados estruturais
- Objetivo: evitar confusao entre estrutura e dados iniciais.
- Prompt sugerido: `Separe seeds estruturais do schema, com scripts idempotentes para unidades, impactos ambientais, status e templates padrao.`

### Testes automatizados
- Objetivo: proteger relatorios, calculos e bloqueios de projeto fechado.
- Prompt sugerido: `Adicione testes automatizados para repository, calculo de medias, relatorios, bloqueio de projeto fechado, hash de documentos e importacao/exportacao.`

### Melhorias de performance
- Objetivo: manter o sistema rapido em projetos grandes.
- Prompt sugerido: `Analise gargalos de performance em projetos grandes e otimize consultas, indices, paginacao, relatorios e calculos de orcamento.`

### Configuracao Nginx de exemplo
- Objetivo: facilitar implantacao padronizada.
- Prompt sugerido: `Adicione arquivo de exemplo de configuracao Nginx para este sistema, usando public como root, PHP-FPM, cache de assets e bloqueio de acesso a app, config, database, storage e .env.`

### Plano de backup e restore testavel
- Objetivo: documentar e validar recuperacao.
- Prompt sugerido: `Crie documentacao operacional de backup e restore, incluindo banco PostgreSQL, uploads, .env, storage, logs e validacao pos-restore.`

