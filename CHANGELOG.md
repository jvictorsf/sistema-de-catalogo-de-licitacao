# Changelog

Todas as alteracoes relevantes deste sistema serao registradas aqui.

## [1.3.1] - 2026-06-08

### Alterado
- Demandas cadastradas agora podem ter unidade/setor demandante, secretaria, responsavel e observacoes editados sem recriacao.

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
