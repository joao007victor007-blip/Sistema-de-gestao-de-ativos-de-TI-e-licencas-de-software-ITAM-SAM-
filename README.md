# Intranet de TI: gestão de ativos e licenças (ITAM/SAM)

Sistema web para controlar o ciclo de vida dos equipamentos de TI e das licenças de software de uma construtora com escritório, obras e almoxarifados. Desenvolvido sob medida, está **em uso em produção** desde novembro de 2026.

> Esta é uma versão higienizada do projeto original, publicada com autorização da empresa. Nomes, marca, endereços, dados e informações de infraestrutura foram removidos ou substituídos por valores fictícios.

## O problema

O inventário de TI vivia em planilhas soltas e na memória da equipe: ninguém sabia com certeza quem estava com qual notebook, quantas licenças de cada software estavam em uso, nem quando cada assinatura seria cobrada. Colaboradores desligados saíam sem devolver equipamentos, e contratos venciam sem aviso.

## Funcionalidades

- **Ativos de hardware**: notebooks, monitores, celulares, linhas móveis e periféricos, com patrimônio, número de série, garantia, nota fiscal anexada e histórico completo.
- **Entregas e devoluções** a colaboradores, com termo de responsabilidade e controle de pendências no desligamento.
- **Licenças de software**: por usuário, por dispositivo ou corporativas, com controle de vagas, chaves de ativação criptografadas e alertas de vencimento.
- **Renovação automática**: assinaturas mensais ou anuais avançam sozinhas para o próximo ciclo, com um único aviso por e-mail antes de cada cobrança.
- **Painel de controle** com indicadores e listas de ação (licenças vencendo, termos pendentes, desligados com equipamento, licenças quase esgotadas, próximas cobranças).
- **Importação em lote** por planilha-modelo, com prévia linha a linha antes de gravar.
- **Auditoria imutável** de todas as ações, sem nenhum dado sensível registrado.
- **Backups diários** do banco com retenção, tela de download e alerta de backup atrasado.
- **Controle de acesso** por papel: administrador, técnico e somente leitura.

## Decisões técnicas

**Deploy em hospedagem compartilhada sem SSH.** O ambiente disponível era cPanel com LiteSpeed, sem acesso de terminal e com o document root restrito ao `public_html`. O sistema fica fora da área pública, com apenas uma porta de entrada mínima exposta. Migrations e ajustes de permissões rodam por tarefas únicas do Cron, e as atualizações seguem um roteiro com troca atômica de pastas e caminho de volta documentado.

**Prévia de importação fiel por construção.** A prévia executa a importação de verdade, usando os mesmos serviços das telas (entregas, atribuições, controle de vagas), dentro de uma transação que é sempre desfeita. Assim não existem duas versões das regras que possam divergir. A regra é tudo ou nada: um único erro impede a gravação.

**Auditoria sem vazamento.** Um trait registra criações e alterações campo a campo; senhas, tokens e chaves de licença são ignorados ou mascarados. Os registros são imutáveis no próprio modelo, e testes garantem que nenhuma senha (nem o hash dela) chega à tabela de auditoria.

**Concorrência nas movimentações.** Entregas e atribuições usam transação com `lockForUpdate`, evitando que dois técnicos entreguem o mesmo equipamento ou ocupem a última vaga de uma licença ao mesmo tempo.

**Segurança em camadas.** Cabeçalhos HTTP de segurança, limite de requisições em ações sensíveis, recuperação de senha que não revela e-mails cadastrados, troca de senha obrigatória no primeiro acesso, desconexão imediata de usuários desativados, anexos com nome aleatório e tipo validado pelo conteúdo, e um teste que percorre **todas** as rotas e falha se alguma ficar acessível sem login.

**Datas de licença com dois significados.** Em vez de uma migração de esquema, a mesma coluna representa a próxima cobrança (renovação automática) ou o vencimento (renovação manual), com escopos de consulta e estados que separam os dois casos.

## Stack

- PHP 8.2 e Laravel 12, com Breeze (Blade) para autenticação
- MariaDB 10.11
- Tailwind CSS e Alpine.js, compilados com Vite
- OpenSpout para leitura de planilhas `.xlsx`
- PHPUnit com banco de testes isolado (protegido por uma trava contra execução no banco de desenvolvimento)

## Como rodar localmente

Requisitos: PHP 8.2+, Composer, Node.js, MariaDB ou MySQL.

```bash
git clone <url-deste-repositorio>
cd <pasta>
composer install
npm install
npm run build

cp .env.example .env
php artisan key:generate
```

Crie dois bancos, um para uso e outro para testes (o nome do segundo precisa terminar em `_testing`), ajuste o `.env` e rode:

```bash
php artisan migrate
php artisan intranet:criar-admin
php artisan serve
```

## Testes

```bash
php artisan test
```

A suíte cobre regras de negócio (vagas de licença, descarte, desligamento), permissões por papel, auditoria, importação, backups, renovação automática e a proteção de todas as rotas.

## Tarefas agendadas

| Comando | Frequência | Função |
|---|---|---|
| `php artisan intranet:backup` | Diário, madrugada | Backup do banco com retenção de 14 dias |
| `php artisan intranet:alertas-licencas` | Diário, manhã | Avança renovações automáticas e envia avisos de licenças |

## Autor

Desenvolvido por **Seu Nome**. [LinkedIn](#) · [E-mail](#)
