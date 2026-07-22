# Possíveis Melhorias e Features

Este arquivo mantém o backlog consultivo do Sistema Interno de Compras e Licitações, ordenado por prioridade operacional. Cada item inclui um prompt sugerido para facilitar futuras solicitações ao Codex.

## Como usar este arquivo

- A ordem das seções representa a prioridade recomendada, considerando risco, segurança, continuidade, ganho administrativo e esforço.
- Itens marcados como [PARCIALMENTE IMPLEMENTADO] já possuem alguma base no sistema, mas ainda não atendem ao escopo completo descrito.
- Itens marcados como [JÁ IMPLEMENTADO] ficam no final apenas como histórico e não fazem parte do backlog pendente.
- Antes de iniciar uma feature, confirme o estado atual no CHANGELOG.md e divida entregas muito grandes em fases.
- Para mudanças com impacto em banco ou produção, solicite também schema, testes, README, changelog e procedimento seguro de atualização.

## Prioridade 1 - Crítica: Segurança, Produção e Continuidade

### Migrações incrementais versionadas
- Objetivo: substituir a evolução exclusiva pelo schema único por migrações rastreáveis e seguras, reduzindo os riscos recorrentes de divergência entre desenvolvimento e produção.
- Prompt sugerido: Implemente migrações incrementais versionadas para o PostgreSQL, mantendo database/schema.sql como instalação completa. Crie tabela de controle, comando de status, aplicação transacional, validação prévia, backup recomendado, rollback quando seguro, testes e documentação de atualização em produção.

### Proteção CSRF e limitação de tentativas
- Objetivo: proteger formulários autenticados e endpoints sensíveis contra requisições forjadas e abuso automatizado.
- Prompt sugerido: Implemente proteção CSRF centralizada em todos os formulários e endpoints de escrita, com token por sessão, validação segura, tratamento de expiração e testes de cobertura. Adicione rate limiting para login, tokens públicos de assinatura, consultas externas e ações críticas, registrando bloqueios sem expor dados sensíveis.

### Segurança de uploads e varredura de arquivos
- Objetivo: validar anexos de fornecedores, comprovantes, imagens e documentos privados além da extensão informada pelo navegador.
- Prompt sugerido: Reforce o pipeline de uploads com validação real de MIME, assinatura do arquivo, tamanho, extensão permitida, nome aleatório, armazenamento privado, bloqueio de execução e quarentena. Integre opcionalmente ClamAV por .env, crie relatório de arquivos rejeitados e testes de segurança.

### [PARCIALMENTE IMPLEMENTADO] Auditoria granular de alterações
- Estado atual: existem históricos específicos para status, aprovações, assinaturas, versões de itens e snapshots, mas não há uma trilha genérica de antes/depois para todas as entidades.
- Objetivo pendente: centralizar alterações relevantes em itens, fornecedores, projetos, demandas, orçamentos, documentos e configurações.
- Prompt sugerido: Implemente auditoria granular centralizada, registrando usuário, data, IP, rota, entidade, ação, dados anteriores e dados novos para itens, fornecedores, projetos, demandas, orçamentos e documentos. Proteja campos sensíveis, crie tela administrativa com filtros e atualize schema, testes, README e changelog.

### Política de retenção e expurgo de evidências
- Objetivo: controlar o prazo de guarda de documentos pessoais, imagens de identificação, assinaturas, logs e anexos sensíveis em conformidade com a LGPD e normas administrativas.
- Prompt sugerido: Implemente política de retenção de dados e evidências, com classificação, fundamento da guarda, prazo por tipo de arquivo, bloqueio legal, anonimização quando aplicável, relatório de documentos sensíveis e rotina administrativa de expurgo controlado e auditável.

### [PARCIALMENTE IMPLEMENTADO] Rotina de backup e restore verificável
- Estado atual: README possui comandos de backup/restore e o sistema oferece exportação JSON, mas não existe rotina automatizada com teste periódico de restauração.
- Objetivo pendente: garantir recuperação conjunta do PostgreSQL, storage privado, uploads e configurações essenciais.
- Prompt sugerido: Crie rotina automatizada e verificável de backup e restore, incluindo PostgreSQL, storage/uploads, anexos privados e .env protegido. Adicione retenção, criptografia opcional, checksum, relatório de execução, teste periódico de restauração e checklist pós-restore para produção.

### Observabilidade e monitoramento de produção
- Objetivo: detectar lentidão, erros recorrentes, falhas de banco, consumo de disco e indisponibilidade antes de afetarem o processo administrativo.
- Prompt sugerido: Implemente observabilidade de produção com health check protegido, métricas de tempo por rota e consulta, erros por período, uso de disco do storage, disponibilidade do PostgreSQL e alertas configuráveis. Não exponha segredos e inclua painel administrativo, logs estruturados, testes e documentação.

### Testes end-to-end e regressão visual de documentos
- Objetivo: validar em navegador os fluxos críticos e impedir regressões de layout em DOD, anexos, relatórios, PDF e mobile.
- Prompt sugerido: Adicione testes end-to-end com Playwright para login, projeto, demanda, aprovação, orçamento, DOD, anexos e assinatura. Inclua screenshots de regressão visual em desktop/mobile e validações específicas de impressão A4, cabeçalho, rodapé, tabelas e ausência de sobreposição.

## Prioridade 2 - Alta: Fluxo Administrativo Completo

### Perfil Solicitante com escopo por unidade
- Objetivo: permitir que colaboradores visualizem, preencham, acompanhem e assinem somente demandas vinculadas às próprias unidades administrativas.
- Prompt sugerido: Crie o perfil Solicitante com escopo de dados por unidade administrativa e secretaria, integrado aos colaboradores e ao AD/LDAP. Permita criar, revisar, assinar e acompanhar as próprias demandas sem visualizar projetos ou dados de outras unidades, com testes de autorização horizontal.

### [PARCIALMENTE IMPLEMENTADO] Workflow completo de demandas
- Estado atual: demandas podem ser aprovadas, negadas ou aprovadas com ressalva quantitativa, possuem histórico e assinaturas, mas ainda não percorrem um ciclo formal completo de submissão e ajustes.
- Objetivo pendente: estruturar rascunho, envio, análise, devolução para ajuste, reenvio, aprovação e consolidação.
- Prompt sugerido: Evolua o workflow de demandas com status rascunho, enviada, em análise, ajustes solicitados, reenviada, aprovada, rejeitada e consolidada. Defina transições, responsáveis, bloqueios de edição, justificativas, notificações, histórico e relatórios de pendências por unidade e secretaria.

### [PARCIALMENTE IMPLEMENTADO] Trilhas de aprovação por perfil
- Estado atual: assinaturas de demanda já aceitam fluxo paralelo ou sequencial, mas não há uma esteira configurável de aprovação do processo por modalidade.
- Objetivo pendente: exigir validação do requisitante, área técnica, gestor, setor de compras e autoridade competente conforme o tipo de contratação.
- Prompt sugerido: Crie trilhas de aprovação configuráveis por modalidade e etapa do projeto, com perfis responsáveis, substitutos, fluxo paralelo ou sequencial, prazo, justificativa, bloqueio de edição, assinatura e histórico auditável. Reaproveite o mecanismo atual de assinaturas quando adequado.

### Checklist processual por modalidade
- Objetivo: acompanhar documentos, validações e etapas obrigatórias para licitação, compra direta e modalidades futuras.
- Prompt sugerido: Crie checklist processual configurável por modalidade, com etapas, documentos obrigatórios, responsáveis, prazos, dependências, status, anexos vinculados e painel de pendências. Bloqueie o avanço quando requisitos obrigatórios não forem atendidos, mediante permissão e justificativa de exceção.

### Central de notificações e monitor de prazos
- Objetivo: reunir avisos de validade de orçamentos, demandas devolvidas, assinaturas pendentes, respostas de fornecedores, retificações e documentos vencendo.
- Prompt sugerido: Crie central de notificações no sistema com alertas por usuário, perfil, unidade e projeto. Monitore validade de orçamentos, prazos de resposta, assinaturas, ajustes de demanda, reabertura/retificação e documentos próximos do vencimento. Inclua leitura, prioridade, filtros, e-mail opcional e rotina agendada.

### [PARCIALMENTE IMPLEMENTADO] Relatório unificado de pendências administrativas
- Estado atual: existem alertas no BI e painel de assinaturas pendentes, mas as verificações ainda estão distribuídas.
- Objetivo pendente: apontar em um único lugar tudo que impede o andamento, fechamento ou emissão documental do projeto.
- Prompt sugerido: Crie relatório unificado de pendências administrativas por projeto, verificando demandas não aprovadas, assinaturas, memórias quantitativas, itens sem orçamento, menos de três cotações, outliers, documentos ausentes, anexos desatualizados, DOD incompleto, lotes sem vínculo e prazos vencidos.

### [PARCIALMENTE IMPLEMENTADO] Central de documentos do processo
- Estado atual: anexos, versões, hashes, propostas e comprovantes existem em módulos separados.
- Objetivo pendente: oferecer visão única de documentos gerados e recebidos, com origem, categoria, versão e permissão.
- Prompt sugerido: Crie central de documentos por projeto, consolidando anexos gerados, propostas, comprovantes, DOD, atas e evidências. Inclua upload, categoria, origem, responsável, data, versão, hash, validade, permissão por perfil, pesquisa, download e vínculo ao checklist processual.

### Fila de tarefas e processamento em segundo plano
- Objetivo: retirar da requisição web exportações pesadas, envio de e-mails, importações, geração de documentos e rotinas agendadas.
- Prompt sugerido: Implemente uma fila de tarefas persistente para geração de PDF/Excel, importações, notificações, e-mails e rotinas administrativas. Crie worker seguro, tentativas, idempotência, progresso, cancelamento, logs, painel de falhas e instruções para executar pelo systemd ou Supervisor.

### Geração de Estudo Técnico Preliminar simplificado
- Objetivo: gerar ETP a partir do projeto, demandas, memórias de cálculo, justificativas, riscos, impactos e pesquisa de preços.
- Prompt sugerido: Crie geração de ETP simplificado para compra direta e licitação, reaproveitando projeto, demandas aprovadas, DOD, memórias quantitativas, itens, riscos, impactos ambientais e pesquisa de preços. Permita tópicos configuráveis, TipTap, Word/PDF, versionamento, hash e validação pública.

### Termo de Referência preliminar
- Objetivo: montar documento base com objeto, justificativa, itens, requisitos, critérios de aceitação, prazos, obrigações e estimativa de preços.
- Prompt sugerido: Implemente Termo de Referência preliminar gerado a partir do projeto, com tópicos configuráveis, editor TipTap, itens consolidados, requisitos, critérios de aceitação, prazos, obrigações, pesquisa de preços, anexos, assinaturas, Word/PDF e controle de versão/hash.

### Matriz de riscos da contratação
- Objetivo: registrar riscos administrativos, técnicos, financeiros, ambientais e operacionais com probabilidade, impacto e resposta.
- Prompt sugerido: Adicione matriz de riscos por projeto, com biblioteca de riscos por categoria, causa, consequência, probabilidade, impacto, nível, estratégia de resposta, ação preventiva, contingência, responsável, prazo e exportação em Word/PDF.

### Plano de Contratações Anual
- Objetivo: planejar necessidades por exercício, secretaria, categoria, estimativa, prioridade e período pretendido antes da abertura dos projetos.
- Prompt sugerido: Crie módulo de Plano de Contratações Anual, permitindo registrar necessidades por secretaria, exercício, categoria, justificativa, estimativa, prioridade e mês previsto. Permita aprovação, revisão, consolidação, vínculo posterior a projetos, indicadores e exportação institucional.

### Resultado, adjudicação e gestão pós-compra
- Objetivo: dar continuidade ao processo depois da pesquisa de preços, registrando vencedores, autorizações, empenhos, contratos e execução.
- Prompt sugerido: Crie módulo pós-cotação para registrar resultado por item ou global, fornecedor vencedor, adjudicação, autorização de compra, empenho, contrato, vigência, fiscal, entregas, ocorrências e saldo. Preserve o histórico do orçamento que fundamentou a decisão e gere documentos institucionais.

## Prioridade 3 - Média: Orçamentos, Fornecedores e Mercado

### Importação de planilha preenchida pelo fornecedor
- Objetivo: importar automaticamente os valores do Excel enviado ao fornecedor, reduzindo digitação e erros.
- Prompt sugerido: Implemente importação da planilha de orçamento preenchida pelo fornecedor, validando versão do modelo, projeto, denominação, código do item, quantidade, unidade, marca/modelo, valor unitário, prazo, validade e observações. Exiba prévia, conflitos e erros antes de salvar no orçamento geral.

### Excel do fornecedor com validação e proteção
- Objetivo: proteger colunas institucionais e orientar o fornecedor no preenchimento dos campos permitidos.
- Prompt sugerido: Melhore o Excel enviado ao fornecedor com aba de instruções, identificação e versão do projeto, colunas fixas protegidas, células de entrada destacadas, validação numérica e de datas, listas permitidas, fórmulas de total e mensagens de erro. Garanta compatibilidade com a futura importação.

### Rodadas de cotação e controle de convites
- Objetivo: registrar fornecedores convidados, datas, prazos, retornos, recusas e novas rodadas de pesquisa.
- Prompt sugerido: Crie módulo de rodadas de cotação por projeto, registrando fornecedores convidados, denominações/itens enviados, data, canal, prazo, status de resposta, recusa, anexos e responsável. Permita nova rodada sem perder o histórico e gere indicadores de taxa de resposta.

### Envio de solicitação por e-mail
- Objetivo: enviar solicitações formais ao fornecedor diretamente pelo sistema, com anexos e rastreabilidade.
- Prompt sugerido: Implemente envio de solicitação de orçamento por e-mail usando SMTP configurado por .env, anexando PDF/Excel por item, lote ou denominação. Registre destinatários, modelo utilizado, data, responsável, status, erro, tentativas e histórico no projeto.

### Portal restrito para fornecedor responder cotação
- Objetivo: permitir que o fornecedor preencha a proposta por link seguro sem possuir usuário interno.
- Prompt sugerido: Crie portal de resposta de cotação por token seguro e expirável, permitindo ao fornecedor revisar os itens, preencher preços, marca/modelo, prazo e validade, anexar propostas e assinar declaração de responsabilidade. Inclua confirmação, auditoria, bloqueio após envio e proteção contra abuso.

### Gestão de documentos e regularidade do fornecedor
- Objetivo: acompanhar certidões, alvarás, comprovantes, validade documental e pendências cadastrais.
- Prompt sugerido: Adicione gestão documental do fornecedor com tipos configuráveis, upload privado, emissão, validade, situação, responsável pela conferência e alertas. Exiba pendências no orçamento sem impedir automaticamente a cotação e mantenha histórico das versões dos documentos.

### Integração com o PNCP e fontes públicas
- Objetivo: reaproveitar dados públicos de contratações, atas e preços para apoiar planejamento e pesquisa de mercado.
- Prompt sugerido: Integre o sistema ao PNCP por API oficial para consultar contratações, itens, fornecedores, atas e valores relacionados. Permita importar referências selecionadas para o banco de preços com origem, data, órgão, link, integridade, deduplicação e atualização controlada.

### Índice de confiabilidade do fornecedor
- Objetivo: resumir participação, resposta, documentação, divergências e comportamento de preços sem produzir sanção automática.
- Prompt sugerido: Crie índice explicável de confiabilidade do fornecedor com base em convites, respostas, propostas anexadas, documentos válidos, divergências e outliers. Exiba os fatores usados, permita revisão administrativa e deixe claro que o indicador é apoio à análise, não decisão automática.

### Relatório de cobertura de fornecedores
- Objetivo: identificar categorias com poucos fornecedores cadastrados ou baixa participação nas pesquisas.
- Prompt sugerido: Crie relatório de cobertura de fornecedores por categoria, subcategoria, CNAE e região, mostrando quantidade cadastrada, ativa, convidada, respondente, taxa de resposta e lacunas de mercado, com filtros e exportação Excel.

### Curva ABC de itens e fornecedores
- Objetivo: identificar itens, categorias e fornecedores de maior impacto financeiro por projeto ou período.
- Prompt sugerido: Adicione análise de Curva ABC por projeto, período, secretaria, categoria, item e fornecedor, com critérios configuráveis, gráficos, tabela acumulada, filtros e exportação, destacando concentrações financeiras relevantes.

## Prioridade 3 - Média: Catálogo, Dados e Inteligência

### [PARCIALMENTE IMPLEMENTADO] Assistente de saneamento de itens duplicados
- Estado atual: há verificação de itens semelhantes, duplicação e versionamento, mas não existe fluxo completo de consolidação cadastral.
- Objetivo pendente: orientar escolha do item principal e tratar referências históricas sem perda de rastreabilidade.
- Prompt sugerido: Evolua o assistente de itens semelhantes para um fluxo de saneamento de duplicidades. Compare nome, código, categoria, unidade, especificação e embalagem; permita escolher item principal, redirecionar usos futuros, inativar duplicados, preservar projetos históricos e gerar relatório auditável.

### Biblioteca de especificações técnicas reutilizáveis
- Objetivo: criar modelos de especificação por categoria, subcategoria, tipo de unidade e natureza do item.
- Prompt sugerido: Crie biblioteca versionada de especificações técnicas reutilizáveis por categoria, subcategoria, tipo de unidade e natureza produto/serviço. Permita aplicar e adaptar modelos no item, identificar a versão de origem e atualizar sugestões sem sobrescrever conteúdo já aprovado.

### Classificação por natureza de despesa e elemento contábil
- Objetivo: vincular itens a classificações orçamentárias para relatórios e integração futura com contabilidade.
- Prompt sugerido: Adicione classificação orçamentária dos itens por natureza de despesa, elemento, subelemento, fonte e centro de custo quando aplicável. Inclua biblioteca, vigência, filtros, importação/exportação JSON e uso nos relatórios e no planejamento anual.

### [PARCIALMENTE IMPLEMENTADO] Regras de sustentabilidade por categoria
- Estado atual: existe biblioteca de impactos ambientais e vínculo manual aos itens, mas não há regra automática por categoria/subcategoria.
- Objetivo pendente: sugerir impactos, requisitos ambientais, descarte e documentação conforme o tipo de objeto.
- Prompt sugerido: Crie regras versionadas de sustentabilidade por categoria e subcategoria, sugerindo impactos ambientais, logística reversa, eficiência energética, critérios de aceitação e documentação. Permita aceitar, editar ou rejeitar cada sugestão e registre a origem no item e nos documentos.

### Validador de especificação potencialmente restritiva
- Objetivo: alertar sobre marca direcionada, termos exclusivos, exigências excessivas ou combinações que possam limitar a competitividade.
- Prompt sugerido: Implemente validador de especificação potencialmente restritiva, com regras determinísticas e apoio opcional de IA. Analise marca/modelo, termos exclusivos, medidas exatas e certificados excessivos; apresente evidências, nível de risco e sugestões, sempre exigindo decisão humana justificada.

### Painel de qualidade e consistência dos dados
- Objetivo: localizar cadastros incompletos, textos corrompidos, unidades incompatíveis, CNPJs duplicados e vínculos órfãos.
- Prompt sugerido: Crie painel de qualidade de dados com verificações de itens sem classificação, especificações incompletas, unidades incoerentes, fornecedores duplicados, CNPJ inválido, CNAE ausente, demandas sem unidade, orçamentos inconsistentes e possíveis problemas de UTF-8. Permita filtrar, exportar e corrigir com segurança.

### [PARCIALMENTE IMPLEMENTADO] Painel executivo por período
- Estado atual: dashboard, Gestão de Projetos e comparativo anual oferecem indicadores, filtros, estatísticas e outliers, mas ainda não há visão executiva completa do ciclo de contratação.
- Objetivo pendente: consolidar planejamento, valores estimados, resultados, economia, prazos e concentração por secretaria e modalidade.
- Prompt sugerido: Evolua o BI para um painel executivo por período com filtros por exercício, mês, secretaria, modalidade, status e categoria. Exiba valores planejados, estimados, vencedores, economia, projetos, demandas, fornecedores, prazos, concentração, cobertura e itens críticos, com consultas otimizadas e exportação.

### Pesquisa global administrativa
- Objetivo: localizar rapidamente projetos, demandas, itens, fornecedores, documentos e números de processo em uma única interface.
- Prompt sugerido: Crie pesquisa global com atalho no sidebar, busca textual por projeto, processo, demanda, item, código, fornecedor, CNPJ e documento. Respeite permissões e escopo por unidade, agrupe resultados por tipo, destaque correspondências e registre métricas sem guardar consultas sensíveis.

### Previsão de demanda e preços
- Objetivo: usar o histórico como apoio ao planejamento, sem substituir a decisão técnica.
- Prompt sugerido: Crie módulo de previsão assistida de demanda e preços por item, categoria e secretaria, usando histórico, sazonalidade, média móvel e intervalos de confiança. Exiba metodologia, qualidade dos dados, limitações e permita ao usuário aceitar ou justificar valor diferente.

## Prioridade 4 - Estratégica: Documentos, Integrações e Arquitetura

### QR Code nos documentos gerados
- Objetivo: facilitar a validação de autenticidade dos anexos, DOD e futuros documentos.
- Prompt sugerido: Inclua QR Code nos documentos versionados, apontando para a página de validação de hash. Exiba tipo do documento, projeto, versão, data de emissão, situação atual e hash completo, garantindo funcionamento em PDF, Word e impressão.

### [PARCIALMENTE IMPLEMENTADO] Comparador de versões de documentos
- Estado atual: anexos e entidades possuem versões, hashes e snapshots em partes do sistema, mas não existe comparação visual consolidada.
- Objetivo pendente: mostrar diferenças de estrutura, textos, itens, quantidades, preços e responsáveis.
- Prompt sugerido: Crie comparador de versões para anexos, DOD, itens e futuros documentos. Destaque campos, textos, itens adicionados/removidos, mudanças de quantidade e preço, responsável e motivo; permita exportar o comparativo e validar os hashes das duas versões.

### [PARCIALMENTE IMPLEMENTADO] Modelos institucionais configuráveis
- Estado atual: o DOD permite configurar conteúdo, logos adicionais, cabeçalho, rodapé e padrões do editor, porém não há biblioteca reutilizável por secretaria/departamento.
- Objetivo pendente: selecionar modelos institucionais versionados ao criar documentos.
- Prompt sugerido: Crie biblioteca de modelos institucionais por secretaria e departamento, com cabeçalho, rodapé, logos, CNPJ, endereço, contato, estilos, assinaturas e campos padrão. Permita versionar, definir modelo padrão, aplicar ao DOD e futuros documentos sem alterar versões já emitidas.

### API interna para integrações
- Objetivo: expor dados controlados para protocolo, transparência, patrimônio, contabilidade ou outros sistemas municipais.
- Prompt sugerido: Crie API interna versionada para consultar projetos, demandas, fornecedores, itens, orçamentos, hashes e documentos. Use autenticação por cliente, escopos, expiração, rate limiting, logs, paginação, filtros, documentação OpenAPI e testes de autorização.

### Seeds separados e idempotentes
- Objetivo: separar dados estruturais do schema e permitir atualização sem duplicar registros.
- Prompt sugerido: Separe seeds estruturais do database/schema.sql em scripts idempotentes e versionados para categorias padrão, tipos de unidade, impactos ambientais, status, CNAEs e templates. Crie comando de aplicação, controle de versão, testes e documentação de execução segura.

### Abstração de armazenamento de arquivos
- Objetivo: retirar dependências diretas de caminhos locais e preparar storage compartilhado, S3 compatível ou migração para Laravel.
- Prompt sugerido: Crie uma camada de armazenamento para uploads públicos e privados, com drivers local e S3 compatível configuráveis por .env. Centralize gravação, leitura, hash, autorização, exclusão, migração de arquivos existentes e testes, sem alterar URLs públicas de forma incompatível.

### [PARCIALMENTE IMPLEMENTADO] Preparação para migração Laravel
- Estado atual: existe o guia docs/como_migrar.md, mas as regras ainda estão concentradas principalmente em arquivos PHP e no repository.
- Objetivo pendente: extrair contratos e serviços gradualmente, preservando comportamento e testes.
- Prompt sugerido: Inicie a preparação gradual para Laravel extraindo um módulo de baixo risco para controllers, services, repositories, validadores e views com contratos claros. Preserve o PostgreSQL, mantenha compatibilidade com o sistema atual, amplie testes de regressão e atualize docs/como_migrar.md com o progresso real.

## Features Já Implementadas

### [JÁ IMPLEMENTADO] Login integrado ao AD/LDAP
- Entregue: autenticação configurável por .env, fallback local, autocadastro/espelhamento, sincronização de perfil, grupos, logs, diagnóstico, README e testes.
- Referência: app/auth_ldap.php, environment_diagnostics.php, README.md e CHANGELOG.md versão 1.6.12.

### [JÁ IMPLEMENTADO] Configuração Nginx e PHP-FPM de exemplo
- Entregue: exemplo com public/ como raiz, try_files, PHP-FPM, bloqueio de arquivos ocultos, limite de upload e instruções de instalação.
- Referência: seção Configuração para Nginx do README.md.
