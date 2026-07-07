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
- Extensoes PHP `pdo_pgsql`, `mbstring`, `fileinfo` e `curl`
- PostgreSQL 13+
- Nginx
- Bootstrap Icons via CDN
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
- Confirmacoes formais de demanda usam link por token, assinatura em canvas e documento pessoal armazenado em `storage/uploads/demand_confirmations/`, sem download publico direto. Essas confirmacoes formais devem ser preservadas por dump do PostgreSQL e backup do `storage`, nao por exportacao JSON operacional.
- A consulta de CNPJ de fornecedores utiliza `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` quando houver acesso externo.
- Unidades demandantes podem ter uma unidade pai para representar subunidades ou departamentos internos.
- Itens podem informar conteudo da embalagem e unidade do conteudo, por exemplo pacote com 100 unidades ou caixa com 305 metros.
- Erros da aplicacao sao registrados em `storage/logs/app.log`; erros nativos do PHP ficam em `storage/logs/php-error.log`.
- Os impactos ambientais dos itens sao armazenados como lista estruturada.
- As sugestoes de IA sao apoio inicial e precisam de revisao tecnica antes de uso em processo administrativo, compra direta ou licitatorio.

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