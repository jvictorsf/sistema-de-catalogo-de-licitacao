# Sistema de Catalogo de Licitacao

## Finalidade
Sistema interno para cadastrar itens padronizados de licitacao, organizar categorias, tipos de unidade, kits, projetos de contratacao, demandas por setor, fornecedores, orcamentos e relatorios institucionais.

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
- OpenAI API opcional para sugestao assistida de itens
- Acesso HTTPS a BrasilAPI opcional para consulta de CNPJ de fornecedores

## Procedimento de Backup
Salvar:
- Dump do banco PostgreSQL `catalogo_licitacao`.
- Arquivo `.env`.
- Diretorio `public/uploads/`.
- Diretorio `storage/`, quando houver arquivos de importacao, exportacao ou logs relevantes.
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
- A importacao/exportacao JSON esta disponivel no menu **Dados**.
- A pagina **Dados** oferece um template JSON de importacao para cada escopo.
- Orcamentos reais de fornecedores podem ser anexados em `public/uploads/supplier_quotes/`.
- A consulta de CNPJ de fornecedores utiliza `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` quando houver acesso externo.
- Os impactos ambientais dos itens sao armazenados como lista estruturada.
- As sugestoes de IA sao apoio inicial e precisam de revisao tecnica antes de uso em processo licitatorio.

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
