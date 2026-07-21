# Sistema Interno de Compras e Licitacoes

## Finalidade
Sistema interno para cadastrar itens, servicos, kits, fornecedores, demandas, projetos de licitacao e compras diretas, com orcamentos, documentos oficiais, anexos e relatorios administrativos.

## URL de Acesso
https://catalogo-licitacao.esturvo.intra

## Localizacao no Servidor
/srv/apps/internos/sistema-de-catalogo-de-licitacao

## Tecnologia
- PHP 8.x sem framework
- PostgreSQL
- Nginx
- Bootstrap 5

## Banco de Dados
- Nome do banco: catalogo_licitacao
- Usuario do banco: user_catalogo_licitacao
- Host: localhost

## Responsavel Tecnico
Departamento de Tecnologia da Informacao

## Responsavel pela Regra de Negocio
Departamento solicitante

## Dependencias
- PHP 8.x
- Extensoes PHP `pdo_pgsql`, `mbstring`, `fileinfo`, `curl`, `dom` e `ldap` quando AD/LDAP estiver habilitado
- PostgreSQL 13+
- Nginx
- Bootstrap Icons via CDN
- TipTap 3 via `esm.sh` para edicao de textos ricos; os navegadores clientes precisam de acesso HTTPS ao CDN
- OpenAI API opcional para sugestao assistida de itens e apoio textual ao DOD
- Acesso HTTPS a BrasilAPI opcional para consulta de CNPJ de fornecedores

## Procedimento de Backup
Salvar:
- Dump do banco PostgreSQL `catalogo_licitacao`.
- Arquivo `.env`.
- Diretorio `public/uploads/`.
- Diretorio `storage/`, incluindo logs e arquivos privados de confirmacao de demanda em `storage/uploads/demand_confirmations/`.
- Brasao em `public/assets/brasao-municipio.png`, quando utilizado.

Exemplo:

```bash
pg_dump -U postgres -h localhost -Fc catalogo_licitacao > catalogo_licitacao.dump
```

## Procedimento de Restore
Restaurar o banco, publicar os arquivos da aplicacao e conferir o `.env`.

```bash
createdb -U postgres -h localhost catalogo_licitacao
pg_restore -U postgres -h localhost -d catalogo_licitacao catalogo_licitacao.dump
psql -U postgres -h localhost -d catalogo_licitacao -f database/schema.sql
```

Depois, restaurar `public/uploads/`, `storage/` e o brasao municipal, se houver.

## Observacoes
- O Nginx deve apontar o `root` para `/srv/apps/internos/sistema-de-catalogo-de-licitacao/public`.
- Arquivos de aplicacao, configuracao, schema e storage ficam fora da raiz publica.
- O codigo de rastreio dos itens e gerado automaticamente no banco no formato `CL000001`.
- O primeiro acesso administrativo deve ser feito em `/setup_admin.php` apos aplicar o schema. Depois disso, o acesso normal ocorre em `/login.php`.
- Perfis disponiveis: Administrador, Gestor, Operador e Consulta. O menu e as rotas administrativas respeitam as permissoes de cada perfil.
- A importacao/exportacao JSON esta disponivel no menu **Dados**.
- A pagina **Dados** oferece um template JSON de importacao para cada escopo.
- Orcamentos reais de fornecedores podem ser anexados em public/uploads/supplier_quotes/.
- Confirmacoes formais de demanda aceitam multiplos assinantes em fluxo paralelo ou sequencial, link individual por token, assinatura em canvas, multiplos comprovantes e hash por assinante. O acompanhamento fica no menu **Assinaturas** e os arquivos permanecem em `storage/uploads/demand_confirmations/`, disponiveis somente para usuarios autenticados com permissao. Preserve o PostgreSQL e o `storage` no backup; esses registros nao integram a exportacao JSON operacional.
- A consulta de CNPJ de fornecedores utiliza `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` quando houver acesso externo.
- Unidades administrativas podem ter uma unidade pai para representar subunidades ou departamentos internos.
- Itens podem informar conteudo da embalagem e unidade do conteudo, por exemplo pacote com 100 unidades ou caixa com 305 metros.
- Novos itens e itens editados devem informar classificacao e prazos estruturados. Materiais permanentes usam garantia minima de 12 meses; materiais de consumo usam minimo de 3 meses; pereciveis tambem exigem validade remanescente em meses.
- Itens antigos permanecem no formato legado ate a primeira edicao. Ao salvar, os textos anteriores ficam no historico e as clausulas passam a ser geradas pelos valores estruturados.
- Servicos recebem classificacao interna propria e clausula de garantia adequada, sem prazo de validade de produto.
- Erros da aplicacao sao registrados em `storage/logs/app.log`; erros nativos do PHP ficam em `storage/logs/php-error.log`.
- Os impactos ambientais dos itens sao armazenados como lista estruturada.
- As sugestoes de IA sao apoio inicial e precisam de revisao tecnica antes de uso em processo administrativo, compra direta ou licitatorio.

## Autenticacao AD/LDAP
O sistema permite autenticacao hibrida: primeiro tenta AD/LDAP quando `AUTH_LDAP_ENABLED=true`; se falhar e `AUTH_LDAP_LOCAL_FALLBACK=true`, permite o login local do administrador cadastrado em `/setup_admin.php`.

Configuracao principal no `.env`:

```env
AUTH_LDAP_ENABLED=true
AUTH_LDAP_HOST=athena.esturvo.intra
AUTH_LDAP_PORT=389
AUTH_LDAP_USE_SSL=false
AUTH_LDAP_USE_TLS=false
AUTH_LDAP_BASE_DN="DC=esturvo,DC=intra"
AUTH_LDAP_BIND_DN="CN=usuario-servico,OU=Usuarios,DC=esturvo,DC=intra"
AUTH_LDAP_BIND_PASSWORD="senha-do-bind"
AUTH_LDAP_USER_FILTER="(|(sAMAccountName={login})(userPrincipalName={login})(mail={login}))"
AUTH_LDAP_ACCOUNT_SUFFIX="@esturvo.intra"
AUTH_LDAP_DOMAIN=ESTURVO
AUTH_LDAP_AUTO_CREATE=true
AUTH_LDAP_SYNC_PROFILE=true
AUTH_LDAP_SYNC_ROLE=true
AUTH_LDAP_DEFAULT_ROLE=viewer
AUTH_LDAP_LOCAL_FALLBACK=true
AUTH_LDAP_ADMIN_GROUPS="Catalogo Licitacao Admins"
AUTH_LDAP_MANAGER_GROUPS="Catalogo Licitacao Gestores"
AUTH_LDAP_OPERATOR_GROUPS="Catalogo Licitacao Operadores"
AUTH_LDAP_VIEWER_GROUPS="Catalogo Licitacao Consulta"
```

Usuarios autenticados pelo AD/LDAP sao espelhados em `app_users` com senha local aleatoria, preservando permissoes, auditoria e fallback administrativo. O mapeamento de perfil segue a ordem Administrador, Gestor, Operador e Consulta; se nenhum grupo bater, usa `AUTH_LDAP_DEFAULT_ROLE`.

Use `/environment_diagnostics.php` para validar extensao PHP `ldap`, host, porta, bind de servico e busca por login. Falhas de autenticacao sao registradas em `storage/logs/app.log` sem gravar senha.
## Padrao de Especificacao Tecnica
Novos itens usam o seguinte JSON base:

```json
{
  "marca_referencia": "",
  "modelo_referencia": "",
  "descricao_minima": "",
  "caracteristicas_minimas": [],
  "criterios_aceitacao": [],
  "documentacao_exigida": [],
  "certificados": [],
  "observacoes": []
}
```

As observacoes padrao sobre imagem ilustrativa, equivalencia tecnica, produtos novos, procedencia e suporte de garantia sao adicionadas automaticamente ao salvar o item.

## Impactos Ambientais
A biblioteca possui codigos reutilizaveis, como `IA001`, `IA002` e demais modelos criados em `database/schema.sql`.

No cadastro do item, e possivel selecionar impactos da biblioteca ou adicionar impactos manualmente. O sistema salva os impactos como lista JSON.

## Brasao do Municipio
Para exibir o brasao nos relatorios:

1. Salvar o arquivo oficial em `public/assets/brasao-municipio.png`.
2. Conferir no `.env`:

```env
MUNICIPAL_LOGO_PATH=/assets/brasao-municipio.png
```

Se o arquivo nao existir, os relatorios continuam sendo gerados sem imagem.

## Variaveis Institucionais do DOD
O DOD da Compra Direta usa as seguintes variaveis no `.env` para padronizar cabecalho e rodape:

```env
MUNICIPAL_LOGO_PATH=/assets/brasao-municipio.png
DOD_ENTITY_NAME="PREFEITURA MUNICIPAL DE ESPIRITO SANTO DO TURVO"
DOD_ENTITY_STATE="ESTADO DE SAO PAULO"
DOD_ENTITY_CITY="Espirito Santo do Turvo - SP"
DOD_ENTITY_CNPJ=57.264.509/0001-69
DOD_LOGO_LEFT_PATH=/assets/municipio-agro.png
DOD_LOGO_RIGHT_PATH=/assets/municipio-verde-azul.png
```

Logos especificas de secretaria ou departamento podem ser adicionadas na propria tela do DOD, uma por linha.

## Toolkit Flutuante
O toolkit de ferramentas rapidas e carregado nas telas internas pelo layout compartilhado. A integracao pode ser configurada no `.env`:

```env
TOOLKIT_ENABLED=true
TOOLKIT_SCRIPT_URL=https://assets.esturvo.intra/toolkit/current/toolkit.min.js
TOOLKIT_TITLE=Ferramentas rápidas
TOOLKIT_SUBTITLE=Apoio ao trabalho diário
TOOLKIT_ACCENT=#2f6f4f
TOOLKIT_ACCENT_DARK=#245a3f
TOOLKIT_POSITION=right
TOOLKIT_SHORTCUT=Alt+T
```

Use `TOOLKIT_ENABLED=false` para desativar a integracao. `TOOLKIT_POSITION` aceita `left` ou `right`, e as cores devem usar o formato hexadecimal completo `#RRGGBB`.

## Padroes do Editor e dos Documentos
Administradores podem acessar `Administracao > Editor e documentos` ou
`/editor_settings.php` para configurar os padroes globais do TipTap e do DOD:

- fonte e tamanho;
- alinhamento padrao e aplicacao obrigatoria a todo o texto;
- espacamento entre linhas e paragrafos;
- margens superior, direita, inferior e esquerda;
- exibicao de `Pagina X de Y`.

As margens superior e inferior possuem limites que reservam espaco para o
cabecalho e o rodape institucionais. Na impressao/PDF, esses elementos sao
repetidos em todas as folhas; no Word, sao gerados como cabecalho e rodape da
secao. Aplique `database/schema.sql` para criar a tabela de configuracao antes
de salvar os padroes pela primeira vez.

## Estrutura

```txt
sistema-de-catalogo-de-licitacao/
|-- app/
|-- public/
|-- storage/
|-- config/
|-- database/
|-- README.md
`-- .env
```

## Configuracao para Nginx

```nginx
server {
    listen 80;
    server_name catalogo-licitacao.esturvo.intra;
    root /srv/apps/internos/sistema-de-catalogo-de-licitacao/public;
    index index.php;
    client_max_body_size 12m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Os links publicos de confirmacao de demanda usam o gateway
`/?public_action=demand_confirmation_sign&token=...`. Mantenha o `try_files`
acima para encaminhar essa rota ao `public/index.php`. O endpoint direto
`/demand_confirmation_sign.php` permanece disponivel para links antigos.

## Instalacao
1. Criar o banco PostgreSQL.
2. Copiar `.env.example` para `.env` e ajustar credenciais.
3. Importar `database/schema.sql`.
4. Configurar o Nginx com `public/` como raiz.
5. Garantir permissao de escrita para `public/uploads/` e `storage/`.

## Testes
Execute a suite automatizada em PHP puro com:

```bash
php tests/run.php
```

Os testes cobrem autenticacao, permissoes, calculos de medias, dados de relatorios, bloqueio de projeto fechado, hash de documentos, importacao/exportacao e leitura de logs.
