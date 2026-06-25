# Como Migrar o Projeto para Laravel

Este documento descreve a melhor forma de migrar o Catalogo de Licitacao para Laravel sem interromper a operacao e sem reescrever tudo de uma vez.

## Melhor Estrategia

A melhor abordagem e uma migracao gradual, em paralelo, usando o Laravel como nova base enquanto o sistema atual continua funcionando. Evite tentar "converter tudo de uma vez"; isso aumenta risco, dificulta testes e atrasa a entrega.

Recomendacao principal:

1. Criar um novo projeto Laravel em paralelo.
2. Manter PostgreSQL como banco.
3. Preservar inicialmente o schema atual.
4. Mapear as regras existentes em services/repositories Laravel.
5. Migrar modulo por modulo.
6. Validar cada modulo contra o comportamento atual.
7. Trocar o Nginx para apontar para o Laravel apenas quando os fluxos essenciais estiverem prontos.

## Fase 1 - Inventario do Sistema Atual

Antes de criar codigo Laravel, documente:

- Entidades principais: itens, categorias, unidades, secretarias, demandas, fornecedores, orcamentos, projetos, lotes, anexos e versoes.
- Fluxos criticos: cadastro de item, demanda, orcamento, projeto, anexos e fechamento.
- Relatorios existentes.
- Regras de bloqueio: projeto fechado, retificacao, anexos invalidados.
- Arquivos usados em upload.
- Configuracoes do `.env`.
- Tabelas e indices do PostgreSQL.

Prompt sugerido:

```text
Analise o sistema atual de catalogo de licitacao e gere um inventario tecnico para migracao ao Laravel, listando entidades, fluxos, tabelas, regras de negocio, relatorios, uploads e riscos.
```

## Fase 2 - Criar o Projeto Laravel

Crie um projeto Laravel novo, preferencialmente fora da pasta atual ate a migracao estabilizar.

Exemplo:

```bash
composer create-project laravel/laravel catalogo-licitacao-laravel
```

Configure `.env`:

```env
APP_NAME="Catalogo de Licitacao"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://catalogo-licitacao.esturvo.intra

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=catalogo_licitacao
DB_USERNAME=user_catalogo_licitacao
DB_PASSWORD=senha_segura
```

## Fase 3 - Escolher a Estrategia do Banco

Existem duas opcoes.

### Opcao A - Preservar o schema atual primeiro

E a opcao mais segura.

Vantagens:

- Menor risco para producao.
- Permite comparar o Laravel com o sistema atual.
- Evita migracao de dados logo no inicio.
- Reduz retrabalho.

Como fazer:

- Criar Models apontando para as tabelas atuais.
- Informar nomes de tabelas quando nao seguirem convencao Laravel.
- Usar casts para JSON, booleanos, datas e decimais.
- Criar migrations apenas como baseline documental no primeiro momento.

### Opcao B - Redesenhar o schema

So vale a pena depois que as regras estiverem totalmente dominadas.

Riscos:

- Exige migracao de dados.
- Pode quebrar relatorios.
- Pode alterar sem querer calculos de preco, anexos e hashes.

Recomendacao: comece pela Opcao A.

Prompt sugerido:

```text
Crie os Models Laravel para o schema PostgreSQL atual do Catalogo de Licitacao, preservando nomes de tabelas e relacionamentos existentes. Use casts adequados para JSON, booleanos, datas e decimais.
```

## Fase 4 - Mapear Models e Relacionamentos

Models provaveis:

- Category
- UnitType
- ProcurementItem
- ProcurementItemImage
- ProcurementItemVersion
- ProcurementProject
- DemandList
- DemandItem
- Secretariat
- RequesterUnit
- Supplier
- DemandSupplierQuote
- DemandSupplierQuoteItem
- DemandPriceReference
- ProjectLicitationItem
- ProjectAnnexVersion
- ProjectLotDenomination
- ProjectLotAssignment

Relacionamentos importantes:

- Projeto possui muitas demandas.
- Demanda pertence a projeto, secretaria e unidade.
- Demanda possui muitos itens.
- Item pertence a categoria, subcategoria e unidade de fornecimento.
- Fornecedor possui muitos orcamentos.
- Orcamento possui itens cotados.
- Projeto possui numeracao de licitacao.
- Projeto possui versoes de anexos.
- Projeto possui denominacoes de lotes.

## Fase 5 - Migrar Regras para Services

No sistema atual, muitas regras estao em `app/repository.php`. No Laravel, evite colocar tudo em Controllers.

Organizacao sugerida:

```text
app/
├── Models/
├── Services/
│   ├── ProjectClosureService.php
│   ├── DemandReportService.php
│   ├── BudgetCalculationService.php
│   ├── AnnexVersionService.php
│   ├── SupplierQuoteService.php
│   └── LotDenominationService.php
├── Http/
│   ├── Controllers/
│   └── Requests/
└── Policies/
```

Regras que devem virar Services:

- Calculo de media de precos.
- Consolidacao de itens do projeto.
- Geracao de anexos.
- Hash e validacao de documentos.
- Bloqueio de projeto fechado.
- Retificacao.
- Relatorios por unidade/secretaria.
- Reuso de orcamentos.
- Banco de precos.

Prompt sugerido:

```text
Extraia as regras do repository.php para Services Laravel, com foco em ProjectClosureService, DemandReportService, BudgetCalculationService e AnnexVersionService. Preserve comportamento e crie testes.
```

## Fase 6 - Rotas e Controllers

Use controllers separados por dominio.

Sugestao:

```text
ProjectController
DemandController
DemandItemController
SupplierController
SupplierQuoteController
ProjectReportController
ProjectAnnexController
ProjectLotController
DocumentValidationController
CatalogItemController
LibraryController
DashboardController
```

Rotas web:

```php
Route::resource('projects', ProjectController::class);
Route::resource('demands', DemandController::class);
Route::resource('suppliers', SupplierController::class);
Route::get('documents/validate', [DocumentValidationController::class, 'show']);
```

## Fase 7 - Validacao com Form Requests

Troque validacoes manuais por Form Requests:

- StoreProjectRequest
- UpdateProjectRequest
- StoreDemandRequest
- StoreSupplierRequest
- StoreQuoteRequest
- StoreItemRequest
- StoreLotDenominationRequest

Beneficios:

- Validacao centralizada.
- Mensagens padronizadas.
- Menos codigo nos controllers.
- Mais facil de testar.

## Fase 8 - Autenticacao e Autorizacao

Use Laravel Breeze ou Laravel Fortify se precisar de login simples.

Para ambiente interno, avalie:

- Login local no primeiro momento.
- Integracao AD/LDAP em fase posterior.
- Policies para bloquear alteracoes em projeto fechado.
- Gates para perfis administrativos.

Exemplos de policies:

- ProjectPolicy
- DemandPolicy
- SupplierQuotePolicy
- AnnexPolicy

Regra importante:

```text
Se projeto esta fechado, bloquear alteracoes em demandas, itens, orcamentos, banco de precos, lotes e numeracao, exceto mudanca para retificacao.
```

## Fase 9 - Views e Frontend

Use Blade com Bootstrap para manter compatibilidade visual.

Sugestao:

```text
resources/views/
├── layouts/app.blade.php
├── projects/
├── demands/
├── suppliers/
├── reports/
├── annexes/
└── dashboard.blade.php
```

Migre primeiro:

1. Layout base e menu.
2. Dashboard.
3. Projetos.
4. Demandas.
5. Fornecedores.
6. Orcamentos.
7. Relatorios.
8. Anexos.

## Fase 10 - Relatorios, Word, Excel e PDF

Para relatorios:

- HTML/print pode continuar no inicio.
- Para Excel, usar `maatwebsite/excel`.
- Para PDF, avaliar `barryvdh/laravel-dompdf` ou manter impressao via navegador se for suficiente.
- Para Word, avaliar `phpoffice/phpword`.

Pacotes provaveis:

```bash
composer require maatwebsite/excel
composer require phpoffice/phpword
composer require barryvdh/laravel-dompdf
```

Observacao: introduza esses pacotes gradualmente. Nao comece a migracao pelos anexos mais complexos.

## Fase 11 - Uploads e Storage

No Laravel, arquivos devem ir para `storage/app`.

Use:

```bash
php artisan storage:link
```

Mapeie:

- Imagens de itens.
- Anexos de orcamentos.
- Documentos do projeto.
- Logs.

Crie uma rotina de migracao dos arquivos atuais de `public/uploads` para `storage/app/public`.

## Fase 12 - Migrations

Mesmo preservando o schema atual, crie uma migration baseline para registrar o estado inicial.

Estrategia recomendada:

1. Criar uma migration baseline baseada no `database/schema.sql`.
2. Marcar essa baseline como aplicada no ambiente de producao.
3. A partir dai, toda alteracao nova vira migration incremental.

Evite continuar rodando um schema unico manual no longo prazo.

Prompt sugerido:

```text
Converta o database/schema.sql atual para migrations Laravel, separando baseline e migrations incrementais futuras. Preserve PostgreSQL, indices, constraints e inserts idempotentes.
```

## Fase 13 - Testes

Priorize testes das regras mais sensiveis:

- Calculo de media de precos.
- Consolidacao por projeto.
- Relatorios por unidade/secretaria.
- Bloqueio de projeto fechado.
- Retificacao.
- Hash de fechamento.
- Validacao de hash.
- Versionamento de anexos.
- Importacao de Excel de fornecedor.

Use:

```bash
php artisan test
```

Prompt sugerido:

```text
Crie testes Laravel para os calculos de orcamento, relatorios por unidade/secretaria, fechamento de projeto, retificacao, hash de anexos e bloqueio de alteracoes.
```

## Fase 14 - Migracao Gradual de Modulos

Ordem recomendada:

1. Autenticacao e layout.
2. Cadastros basicos: categorias, unidades, secretarias, fornecedores.
3. Catalogo de itens.
4. Projetos e demandas.
5. Orcamentos.
6. Banco de precos.
7. Lotes e denominacoes.
8. Relatorios.
9. Anexos.
10. Hash, validacao e fechamento.
11. Importacao/exportacao JSON.
12. Dashboard final.

## Fase 15 - Homologacao

Antes de trocar producao:

- Comparar relatorios do PHP atual e Laravel com o mesmo banco.
- Validar valores totais e medias.
- Validar anexos por item e por lote.
- Validar hash e versoes.
- Validar projeto fechado e retificacao.
- Validar uploads.
- Validar permissao do storage.
- Validar Nginx.
- Validar backup e restore.

## Fase 16 - Deploy com Nginx

Nginx deve apontar para:

```text
/srv/apps/internos/catalogo-licitacao-laravel/public
```

Nunca aponte o Nginx para a raiz do projeto Laravel.

Comandos comuns:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Permissoes:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache
```

## Fase 17 - Plano de Rollback

Antes da virada:

- Fazer backup do banco.
- Fazer backup dos uploads.
- Manter sistema antigo intacto.
- Fazer deploy Laravel em pasta separada.
- Trocar Nginx apenas no final.
- Se falhar, voltar o Nginx para o sistema antigo.

## Riscos Principais

- Divergencia de calculo de medias.
- Divergencia na consolidacao de itens.
- Quebra de anexos.
- Perda de uploads.
- Mudanca acidental de hash.
- Migrations aplicadas sem backup.
- Permissoes incorretas no storage.
- Rotas antigas usadas por usuarios.

## Prompt Geral para Iniciar a Migracao

```text
Quero migrar o Catalogo de Licitacao PHP puro para Laravel de forma gradual e segura. Primeiro, analise o projeto atual, gere um plano tecnico por fases, preserve PostgreSQL e schema atual no inicio, proponha Models, Services, Controllers, Policies, migrations baseline, estrategia de testes, plano de deploy Nginx e rollback. Nao implemente tudo de uma vez; comece pelo inventario e pela estrutura base.
```

