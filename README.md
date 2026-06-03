# Sistema de Catalogo de Licitacao

## Finalidade
Sistema interno para cadastrar itens padronizados de licitacao, organizar categorias, tipos de unidade, kits, projetos de contratacao, demandas por setor e relatorios institucionais.

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
- Usuario do banco: postgres
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

## Procedimento de Backup
Salvar:
- Dump do banco PostgreSQL `catalogo_licitacao`.
- Arquivo `.env`.
- Diretorio `public/uploads/`.
- Diretorio `storage/`, quando houver arquivos de importacao, exportacao ou logs relevantes.

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

Depois, restaurar `public/uploads/` e `storage/` conforme o backup.

## Observacoes
- O Nginx deve apontar o `root` para `/srv/apps/internos/sistema-de-catalogo-de-licitacao/public`.
- Arquivos de aplicacao, configuracao, schema e storage ficam fora da raiz publica.
- O codigo de rastreio dos itens e gerado automaticamente no banco no formato `CL000001`.
- A importacao/exportacao JSON esta disponivel no menu **Dados**.
- As sugestoes de IA sao apoio inicial e precisam de revisao tecnica antes de uso em processo licitatorio.

## Estrutura

```txt
sistema-de-catalogo-de-licitacao/
├── app/
├── public/
├── storage/
├── config/
├── database/
├── README.md
└── .env
```

## Configuracao para Nginx

```nginx
server {
    listen 80;
    server_name catalogo-licitacao.esturvo.intra;
    root /srv/apps/internos/sistema-de-catalogo-de-licitacao/public;
    index index.php;

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
