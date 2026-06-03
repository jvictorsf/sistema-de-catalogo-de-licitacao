# Changelog

Todas as alteracoes relevantes deste sistema serao registradas aqui.

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
