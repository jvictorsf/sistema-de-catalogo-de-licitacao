# Possiveis Melhorias e Features

Este arquivo registra ideias consultivas ainda nao implementadas para evolucao do Sistema Interno de Compras e Licitacoes. Cada item possui um prompt sugerido para facilitar pedidos futuros ao Codex.

## Como usar este arquivo

- Use os prompts como ponto de partida e ajuste o escopo conforme a prioridade do momento.
- Antes de implementar, confirme se a feature ainda nao foi entregue em versoes recentes do `CHANGELOG.md`.
- Para features com impacto em banco, pedir sempre schema, README, changelog, testes e orientacoes de migracao.

## Governanca, Auditoria e Seguranca

### Auditoria granular de alteracoes
- Objetivo: registrar antes/depois de alteracoes relevantes em itens, fornecedores, projetos, demandas, orcamentos, documentos e configuracoes, alem dos eventos de status ja existentes.
- Prompt sugerido: `Implemente auditoria granular de alteracoes no sistema, registrando usuario, data, IP, rota, entidade, acao, dados anteriores e dados novos para itens, fornecedores, projetos, demandas, orcamentos e documentos. Crie tela administrativa com filtros e atualize schema, testes, README e changelog.`

### Login integrado ao AD/LDAP
- Objetivo: permitir autenticacao com credenciais institucionais, mantendo fallback local para contingencia.
- Prompt sugerido: `Adicione autenticacao via AD/LDAP ao sistema, configuravel por .env, mantendo fallback local para administrador. Valide conexao, bind, grupos/perfis, logs de falha, README e testes de configuracao.`

### Perfil Solicitante externo controlado
- Objetivo: permitir que unidades administrativas preencham ou confirmem demandas sem acesso amplo ao sistema.
- Prompt sugerido: `Crie perfil Solicitante com acesso restrito somente as demandas da propria unidade administrativa, permitindo preencher, revisar, assinar e acompanhar demandas sem visualizar projetos de outras unidades.`

### Politica de retencao e expurgo de evidencias
- Objetivo: controlar prazo de guarda de documentos pessoais, assinaturas, logs e anexos sensiveis.
- Prompt sugerido: `Implemente politica de retencao de dados e evidencias, com classificacao de arquivos, prazo de guarda, bloqueio de exclusao indevida, relatorio de documentos sensiveis e rotina administrativa de expurgo controlado.`

### Trilhas de aprovacao por perfil
- Objetivo: exigir aprovacao por responsavel tecnico, requisitante, gestor e autoridade competente conforme etapa do processo.
- Prompt sugerido: `Crie trilhas de aprovacao configuraveis por modalidade do projeto, com etapas, responsaveis, prazos, justificativas, bloqueios de edicao e historico auditavel.`

## Fluxos de Compra Direta e Licitacao

### Workflow completo de demandas
- Objetivo: transformar demandas em fluxo formal com rascunho, envio, analise tecnica, ajustes, aprovacao e consolidacao.
- Prompt sugerido: `Implemente workflow completo de demandas com status rascunho, enviada, em analise, ajustes solicitados, aprovada e consolidada. Bloqueie edicoes por status, registre historico e crie relatorios de pendencias por unidade e secretaria.`

### Geracao de Estudo Tecnico Preliminar simplificado
- Objetivo: gerar ETP simplificado a partir dos dados do projeto, demandas, justificativas, riscos, impactos e pesquisa de precos.
- Prompt sugerido: `Crie geracao de ETP simplificado para compra direta e licitacao, reaproveitando dados do projeto, demandas, DOD, itens, justificativas, impactos ambientais e pesquisa de precos. Permita editar topicos, exportar Word/PDF e versionar com hash.`

### Termo de Referencia preliminar
- Objetivo: montar documento base com objeto, justificativa, itens, criterios de aceitacao, prazos, obrigacoes e estimativa de precos.
- Prompt sugerido: `Implemente Termo de Referencia preliminar gerado a partir do projeto, com topicos editaveis, itens consolidados, criterios de aceitacao, requisitos, prazos, obrigacoes, pesquisa de precos, anexos, Word/PDF e controle de versao/hash.`

### Matriz de riscos da contratacao
- Objetivo: listar riscos administrativos, tecnicos, financeiros, ambientais e operacionais com probabilidade, impacto e mitigacao.
- Prompt sugerido: `Adicione matriz de riscos por projeto, com riscos padrao por categoria, probabilidade, impacto, nivel, plano de mitigacao, responsavel e exportacao em Word/PDF.`

### Checklist processual por modalidade
- Objetivo: acompanhar documentos e etapas obrigatorias para licitacao, compra direta e outras modalidades futuras.
- Prompt sugerido: `Crie checklist processual configuravel por modalidade, com documentos obrigatorios, responsaveis, prazos, status, anexos vinculados e painel de pendencias do projeto.`

## Orcamentos, Precos e Fornecedores

### Importacao de planilha preenchida pelo fornecedor
- Objetivo: importar automaticamente os valores preenchidos no Excel enviado ao fornecedor.
- Prompt sugerido: `Implemente importacao da planilha de orcamento preenchida pelo fornecedor, validando codigo do item, quantidade, unidade, marca/modelo, valor unitario, prazo, validade e observacoes antes de salvar no orcamento geral do projeto.`

### Excel do fornecedor com validacao e protecao
- Objetivo: reduzir erro de preenchimento pelo fornecedor, protegendo colunas fixas e validando valores.
- Prompt sugerido: `Melhore o Excel enviado ao fornecedor com abas de instrucoes, colunas protegidas, validacao de valores numericos, destaque de campos obrigatorios e mensagens de erro para preenchimento inadequado.`

### Rodada de cotacao e controle de convites
- Objetivo: registrar fornecedores convidados, data de envio, prazo de resposta, retornos e recusas.
- Prompt sugerido: `Crie modulo de rodada de cotacao por projeto, registrando fornecedores convidados, data de envio, prazo, status de resposta, motivo de recusa, anexos recebidos e alertas de atraso.`

### Envio de solicitacao por e-mail
- Objetivo: enviar solicitacoes formais ao fornecedor diretamente pelo sistema com anexos e rastreio.
- Prompt sugerido: `Implemente envio de solicitacao de orcamento por e-mail pelo sistema, usando SMTP via .env, anexando PDF/Excel, registrando destinatarios, data de envio, status, erro e historico no projeto.`

### Portal publico restrito para fornecedor responder cotacao
- Objetivo: permitir que fornecedor acesse link com token e preencha valores sem precisar de usuario interno.
- Prompt sugerido: `Crie portal de resposta de cotacao por link seguro/token para fornecedores, permitindo preencher valores, anexar proposta, informar validade e assinar declaracao de responsabilidade, com expiracao e registro auditavel.`

### Curva ABC de itens e fornecedores
- Objetivo: identificar itens e fornecedores de maior impacto financeiro no projeto ou no periodo.
- Prompt sugerido: `Adicione analise de Curva ABC por projeto, secretaria, categoria e fornecedor, com graficos, filtros e exportacao, destacando itens de maior impacto financeiro.`

### Indice de confiabilidade do fornecedor
- Objetivo: classificar fornecedores por historico de participacao, respostas, divergencias, documentos e valores discrepantes.
- Prompt sugerido: `Crie indice de confiabilidade do fornecedor com base em participacoes, respostas, propostas anexadas, valores discrepantes, documentos incompletos e historico de cotacoes, exibindo alertas no cadastro e nos orcamentos.`

## Catalogo, Itens e Bibliotecas

### Biblioteca de especificacoes tecnicas reutilizaveis
- Objetivo: criar modelos de especificacao por categoria, subcategoria, tipo de unidade ou natureza do item.
- Prompt sugerido: `Crie biblioteca de especificacoes tecnicas reutilizaveis por categoria, subcategoria, tipo de unidade e natureza produto/servico, com aplicacao automatica no cadastro de item e versionamento dos modelos.`

### Assistente de saneamento de itens duplicados
- Objetivo: comparar itens semelhantes e orientar unificacao, inativacao ou manutencao separada.
- Prompt sugerido: `Implemente assistente de saneamento de itens duplicados, comparando nome, codigo, categoria, unidade, especificacao, embalagem e uso em projetos. Permita marcar como duplicado, sugerir item principal e gerar relatorio de saneamento.`

### Classificacao por natureza de despesa e elemento contabil
- Objetivo: vincular itens a classificacoes orcamentarias para melhorar relatorios administrativos.
- Prompt sugerido: `Adicione classificacao orcamentaria dos itens por natureza de despesa, elemento, subelemento e fonte quando aplicavel, com filtros, importacao JSON e uso nos relatorios do projeto.`

### Regras de sustentabilidade por categoria
- Objetivo: aplicar requisitos ambientais padrao conforme categoria do item ou servico.
- Prompt sugerido: `Crie regras de sustentabilidade por categoria/subcategoria, sugerindo impactos ambientais, criterios de aceitacao e documentacao exigida no cadastro do item e nos documentos do projeto.`

### Validador de especificacao restritiva
- Objetivo: alertar quando uma especificacao parece direcionar marca/modelo ou restringir competitividade.
- Prompt sugerido: `Implemente validador de especificacao restritiva, analisando marca/modelo, termos exclusivos, exigencias excessivas e caracteristicas que possam limitar competitividade, com alertas e sugestoes de ajuste.`

## Relatorios, BI e Indicadores

### Painel executivo por periodo
- Objetivo: consolidar valores, modalidades, secretarias, fornecedores, status e economia por periodo.
- Prompt sugerido: `Crie painel executivo por periodo com filtros por ano, mes, secretaria, modalidade, status e categoria, exibindo valores estimados, valores vencidos, economia, quantidade de projetos, fornecedores e itens criticos.`

### Relatorio de pendencias administrativas
- Objetivo: listar tudo que impede fechamento, emissao de anexos ou andamento do projeto.
- Prompt sugerido: `Crie relatorio de pendencias administrativas do projeto, apontando demandas nao aprovadas, itens sem orcamento, menos de tres cotacoes, documentos ausentes, anexos desatualizados, DOD incompleto e assinaturas pendentes.`

### Monitor de prazos
- Objetivo: acompanhar validade de orcamentos, prazos de resposta, reaberturas e fechamentos automaticos.
- Prompt sugerido: `Implemente monitor de prazos com alertas para validade de orcamentos, prazo de resposta de fornecedor, prazo de retificacao/reabertura e documentos proximos do vencimento, com dashboard e filtros.`

### Relatorio de cobertura de fornecedores
- Objetivo: identificar categorias com poucos fornecedores cadastrados ou baixa participacao em cotacoes.
- Prompt sugerido: `Crie relatorio de cobertura de fornecedores por categoria e subcategoria, mostrando quantidade cadastrada, quantidade ativa, participacao em cotacoes, taxa de resposta e lacunas de mercado.`

## Documentos, Assinaturas e Validacao

### QR Code nos documentos gerados
- Objetivo: facilitar validacao de hash e autenticidade dos anexos e documentos.
- Prompt sugerido: `Inclua QR Code nos anexos e documentos gerados, apontando para a pagina de validacao de hash, exibindo tipo do documento, projeto, versao, data de emissao e hash completo.`

### Comparador de versoes de documentos
- Objetivo: mostrar diferencas entre versoes de anexos, DOD, ETP ou termo de referencia.
- Prompt sugerido: `Crie comparador de versoes de documentos, destacando itens adicionados, removidos, alterados, mudancas de quantidade, preco, texto e responsaveis, com exportacao do comparativo.`

### Central de documentos do processo
- Objetivo: organizar documentos gerados, anexos recebidos, atas, comprovantes, assinaturas e evidencias.
- Prompt sugerido: `Crie central de documentos por projeto, com upload, categorizacao, origem, responsavel, data, hash, permissao por perfil, busca, download e vinculacao a etapas do checklist processual.`

### Assinatura institucional avancada
- Objetivo: permitir assinatura por multiplos responsaveis com ordem, status, data e evidencias.
- Prompt sugerido: `Evolua o modulo de assinaturas para permitir fluxo sequencial ou paralelo, multiplos assinantes, assinatura por token, anexos de comprovacao, hash individual e painel de pendencias.`

### Modelos institucionais configuraveis
- Objetivo: configurar cabecalhos, rodapes, logos, campos e textos padrao por secretaria/departamento.
- Prompt sugerido: `Crie modelos institucionais configuraveis por secretaria e departamento, permitindo cabecalho, rodape, logos adicionais, CNPJ, endereco, telefone, e-mail, assinatura e campos padrao para documentos.`

## Integracoes e Infraestrutura

### Migracoes incrementais versionadas
- Objetivo: substituir a evolucao via schema unico por migrations aplicaveis com seguranca em producao.
- Prompt sugerido: `Organize o banco em migrations incrementais versionadas, mantendo compatibilidade com database/schema.sql, criando tabela de controle, comandos de aplicar/rollback e documentacao de atualizacao segura em producao.`

### Seeds separados e idempotentes
- Objetivo: separar dados estruturais de schema e permitir atualizacao segura sem duplicar registros.
- Prompt sugerido: `Separe seeds estruturais do schema em scripts idempotentes para categorias padrao, tipos de unidade, impactos ambientais, status, CNAEs e templates, com documentacao de execucao segura.`

### Configuracao Nginx e PHP-FPM de exemplo
- Objetivo: padronizar implantacao em servidor Linux com `public/` como raiz.
- Prompt sugerido: `Adicione exemplos de configuracao Nginx e PHP-FPM para o sistema, usando public como root, bloqueando app/config/database/storage/.env, configurando uploads, logs, cache de assets e limites de upload.`

### Rotina de backup e restore verificavel
- Objetivo: garantir recuperacao real do banco, uploads, .env, logs e documentos.
- Prompt sugerido: `Crie rotina documentada e testavel de backup/restore, incluindo PostgreSQL, storage/uploads, .env, logs e validacao pos-restore com checklist e comandos para producao.`

### API interna para integracoes futuras
- Objetivo: expor dados controlados para portal, painel externo, protocolo ou sistema contabil.
- Prompt sugerido: `Crie uma API interna autenticada para consultar projetos, demandas, fornecedores, itens, orcamentos, hashes e documentos, com tokens, permissoes, logs e documentacao de endpoints.`

### Preparacao para migracao Laravel
- Objetivo: reduzir risco ao migrar futuramente para Laravel, isolando camadas e contratos.
- Prompt sugerido: `Prepare o sistema para migracao gradual ao Laravel, separando controllers, services, repositories, validadores e views, documentando contratos e criando testes de regressao para fluxos criticos.`
