<?php

namespace App\Services\Import;

use App\Enums\AuditAction;
use App\Exceptions\MovementException;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\License;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Software;
use App\Models\User;
use App\Services\AssetMovementService;
use App\Services\LicenseAssignmentService;
use App\Support\Audit;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Importa a planilha-modelo de inventário.
 * Tudo roda numa única transação: na prévia ela é sempre desfeita;
 * na confirmação, só é gravada se não houver nenhum erro.
 */
class InventoryImporter
{
    /** Colunas de cada aba: chave interna => título no modelo (sem o asterisco) */
    public const COLUMNS = [
        'Locais' => [
            'nome' => 'Nome', 'tipo' => 'Tipo', 'cidade' => 'Cidade', 'uf' => 'UF',
        ],
        'Colaboradores' => [
            'nome' => 'Nome completo', 'matricula' => 'Matrícula', 'email' => 'E-mail',
            'cargo' => 'Cargo', 'setor' => 'Setor', 'local' => 'Local de lotação',
        ],
        'Ativos' => [
            'patrimonio' => 'Nº de patrimônio', 'categoria' => 'Categoria', 'fabricante' => 'Fabricante',
            'modelo' => 'Modelo', 'serie' => 'Número de série', 'descricao' => 'Descrição', 'local' => 'Local',
            'situacao' => 'Situação', 'responsavel' => 'Responsável', 'data_entrega' => 'Data de entrega',
            'termo' => 'Termo assinado', 'data_compra' => 'Data de compra', 'valor' => 'Valor de compra (R$)',
            'nota' => 'Nota fiscal', 'fornecedor' => 'Fornecedor', 'garantia' => 'Fim da garantia',
            'observacoes' => 'Observações',
        ],
        'Licenças' => [
            'software' => 'Software', 'nome' => 'Nome da licença', 'tipo' => 'Tipo',
            'atribuicao' => 'Forma de atribuição', 'quantidade' => 'Licenças contratadas',
            'aquisicao' => 'Data de aquisição', 'vencimento' => 'Vencimento',
            'alerta' => 'Alertar quantos dias antes', 'periodicidade' => 'Periodicidade', 'valor' => 'Valor (R$)',
            'renovacao' => 'Renovação automática', 'fornecedor' => 'Fornecedor',
            'pedido' => 'Nota fiscal ou pedido', 'conta' => 'Conta administradora', 'observacoes' => 'Observações',
        ],
        'Uso de licenças' => [
            'licenca' => 'Nome da licença', 'colaborador' => 'Colaborador',
            'equipamento' => 'Equipamento (patrimônio ou nº de série)', 'data' => 'Data da atribuição',
        ],
    ];

    private const LOCATION_TYPES = ['escritório' => 'escritorio', 'obra' => 'obra', 'almoxarifado' => 'almoxarifado'];
    private const ASSET_STATUSES = ['disponível' => 'disponivel', 'em uso' => 'em_uso', 'em manutenção' => 'manutencao', 'extraviado' => 'extraviado'];
    private const LICENSE_TYPES = ['assinatura' => 'assinatura', 'perpétua' => 'perpetua', 'oem' => 'oem'];
    private const ASSIGNMENT_TYPES = ['por usuário' => 'per_user', 'por dispositivo' => 'per_device', 'corporativa' => 'corporate'];
    private const BILLING_CYCLES = ['mensal' => 'mensal', 'anual' => 'anual', 'pagamento único' => 'unica'];

    /** Valores da linha de exemplo do modelo, para detectar quando ela não foi apagada */
    private const EXAMPLES = [
        'Colaboradores' => ['email' => 'maria.silva@exemplo.com.br'],
        'Ativos' => ['serie' => 'ABC1234XYZ'],
        'Licenças' => ['pedido' => 'Pedido 889'],
        'Uso de licenças' => ['licenca' => 'Microsoft 365 Business Standard 2026', 'colaborador' => 'Maria da Silva'],
    ];

    private ImportResult $result;
    private User $by;
    private array $locations = [];
    private array $employees = [];
    private array $registrations = [];
    private array $assetsByTag = [];
    private array $assetsBySerial = [];
    private array $licenses = [];

    public function __construct(
        private AssetMovementService $movements,
        private LicenseAssignmentService $licenseAssignments,
    ) {
    }

    public function run(string $path, User $by, bool $commit): ImportResult
    {
        $this->result = new ImportResult();
        $this->by = $by;
        $this->locations = $this->employees = $this->registrations = [];
        $this->assetsByTag = $this->assetsBySerial = $this->licenses = [];

        $sheets = $this->readWorkbook($path);

        if ($this->result->hasErrors()) {
            return $this->result;
        }

        DB::beginTransaction();

        try {
            $this->importLocations($sheets['Locais']);
            $this->importEmployees($sheets['Colaboradores']);
            $this->importAssets($sheets['Ativos']);
            $this->importLicenses($sheets['Licenças']);
            $this->importLicenseUsage($sheets['Uso de licenças']);

            if ($commit && ! $this->result->hasErrors()) {
                Audit::record(AuditAction::Imported, null, 'Importou a planilha de inventário', $this->result->auditSummary());
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $this->result;
    }

    // ------------------------------------------------------------------
    // Leitura do arquivo
    // ------------------------------------------------------------------

    private function readWorkbook(string $path): array
    {
        $sheets = array_fill_keys(array_keys(self::COLUMNS), []);
        $found = [];
        $reader = new Reader();

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $name = $sheet->getName();

                if (! isset(self::COLUMNS[$name])) {
                    continue; // Instruções e Listas
                }

                $found[] = $name;
                $map = null;

                foreach ($sheet->getRowIterator() as $line => $row) {
                    $cells = $row->toArray();

                    if ($map === null) {
                        $map = $this->headerMap($name, $cells);
                        if ($map === null) {
                            break;
                        }
                        continue;
                    }

                    $values = [];
                    foreach ($map as $key => $index) {
                        $values[$key] = $this->clean($cells[$index] ?? null);
                    }

                    if (array_filter($values, fn ($value) => $value !== null) !== []) {
                        $sheets[$name][$line] = $values;
                    }
                }
            }

            $reader->close();
        } catch (Throwable) {
            $this->result->error('Arquivo', null, 'Não foi possível ler o arquivo. Envie a planilha-modelo em formato .xlsx.');

            return $sheets;
        }

        foreach (array_keys(self::COLUMNS) as $name) {
            if (! in_array($name, $found, true)) {
                $this->result->error($name, null, 'Aba não encontrada. Use o modelo da intranet sem renomear as abas.');
            }
        }

        if (! $this->result->hasErrors() && array_sum(array_map('count', $sheets)) === 0) {
            $this->result->error('Arquivo', null, 'A planilha não tem nenhuma linha preenchida.');
        }

        return $sheets;
    }

    private function headerMap(string $sheet, array $cells): ?array
    {
        $headers = array_map(fn ($cell) => mb_strtolower(trim(str_replace('*', '', (string) $cell))), $cells);
        $map = [];

        foreach (self::COLUMNS[$sheet] as $key => $title) {
            $index = array_search(mb_strtolower($title), $headers, true);

            if ($index === false) {
                $this->result->error($sheet, 1, "Coluna \"{$title}\" não encontrada. Não renomeie os títulos do modelo.");

                return null;
            }

            $map[$key] = $index;
        }

        return $map;
    }

    // ------------------------------------------------------------------
    // Abas
    // ------------------------------------------------------------------

    private function importLocations(array $rows): void
    {
        foreach ($rows as $line => $row) {
            $errors = [];
            $name = $this->text($row['nome']);
            $type = $this->option(self::LOCATION_TYPES, $row['tipo']);
            $state = $this->upper($row['uf']);

            if (! $name) {
                $errors[] = 'Nome é obrigatório.';
            } elseif (isset($this->locations[mb_strtolower($name)])) {
                $errors[] = "Local {$name} repetido na planilha.";
            }
            if (! $type) {
                $errors[] = 'Tipo inválido. Use Escritório, Obra ou Almoxarifado.';
            }
            if ($state && ! preg_match('/^[A-Z]{2}$/', $state)) {
                $errors[] = 'UF deve ter 2 letras, como PE.';
            }

            if ($errors) {
                $this->fail('Locais', $line, $errors);
                continue;
            }

            if ($existing = Location::where('name', $name)->first()) {
                $this->locations[mb_strtolower($name)] = $existing;
                $this->result->reused('locais');
                continue;
            }

            $this->createRow('Locais', $line, function () use ($name, $type, $state, $row) {
                $this->locations[mb_strtolower($name)] = Location::create([
                    'name' => $name,
                    'type' => $type,
                    'city' => $this->text($row['cidade']),
                    'state' => $state,
                ]);
                $this->result->created('locais');
            });
        }
    }

    private function importEmployees(array $rows): void
    {
        foreach ($rows as $line => $row) {
            if ($this->isExample('Colaboradores', $row)) {
                $this->exampleError('Colaboradores', $line);
                continue;
            }

            $errors = [];
            $name = $this->text($row['nome']);
            $registration = $this->text($row['matricula']);
            $email = $this->text($row['email']);
            $locationName = $this->text($row['local']);
            $location = null;

            if (! $name) {
                $errors[] = 'Nome completo é obrigatório.';
            } elseif (isset($this->employees[mb_strtolower($name)])) {
                $errors[] = "Colaborador {$name} repetido na planilha.";
            }
            if ($registration && isset($this->registrations[$registration])) {
                $errors[] = "Matrícula {$registration} repetida na planilha.";
            }
            if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'E-mail inválido.';
            }
            if ($locationName && ! ($location = $this->resolveLocation($locationName))) {
                $errors[] = "Local {$locationName} não existe na aba Locais nem no sistema.";
            }

            if ($errors) {
                $this->fail('Colaboradores', $line, $errors);
                continue;
            }

            if ($registration) {
                $this->registrations[$registration] = true;
            }

            $existing = $registration
                ? Employee::where('registration', $registration)->first()
                : Employee::where('name', $name)->first();

            if ($existing) {
                $this->employees[mb_strtolower($name)] = $existing;
                $this->result->reused('colaboradores');
                continue;
            }

            $this->createRow('Colaboradores', $line, function () use ($name, $registration, $email, $location, $row) {
                $employee = Employee::create([
                    'name' => $name,
                    'registration' => $registration,
                    'email' => $email ? mb_strtolower($email) : null,
                    'job_title' => $this->text($row['cargo']),
                    'department_id' => $this->catalog(Department::class, $this->text($row['setor']), 'setores'),
                    'location_id' => $location?->id,
                ]);

                // Recarrega para trazer os valores padrão do banco (como is_active)
                $this->employees[mb_strtolower($name)] = $employee->refresh();
                $this->result->created('colaboradores');
            });
        }
    }

    private function importAssets(array $rows): void
    {
        foreach ($rows as $line => $row) {
            if ($this->isExample('Ativos', $row)) {
                $this->exampleError('Ativos', $line);
                continue;
            }

            $errors = [];
            $tag = $this->upper($row['patrimonio']);
            $serial = $this->upper($row['serie']);
            $categoryName = $this->text($row['categoria']);
            $status = $this->option(self::ASSET_STATUSES, $row['situacao']);
            $locationName = $this->text($row['local']);
            $responsibleName = $this->text($row['responsavel']);
            $deliveredAt = $this->date($row['data_entrega']);
            $purchasedAt = $this->date($row['data_compra']);
            $warrantyUntil = $this->date($row['garantia']);
            $price = $this->number($row['valor']);
            $termText = $this->text($row['termo']);
            $termSigned = $this->yesNo($termText);
            $location = null;
            $employee = null;

            if (! $categoryName) {
                $errors[] = 'Categoria é obrigatória.';
            }
            if (! $status) {
                $errors[] = 'Situação inválida. Use Disponível, Em uso, Em manutenção ou Extraviado.';
            }
            if ($tag && (isset($this->assetsByTag[$tag]) || Asset::where('asset_tag', $tag)->exists())) {
                $errors[] = "Patrimônio {$tag} já está cadastrado ou repetido na planilha.";
            }
            if ($serial && (isset($this->assetsBySerial[$serial]) || Asset::where('serial_number', $serial)->exists())) {
                $errors[] = "Número de série {$serial} já está cadastrado ou repetido na planilha.";
            }
            if ($locationName && ! ($location = $this->resolveLocation($locationName))) {
                $errors[] = "Local {$locationName} não existe na aba Locais nem no sistema.";
            }
            foreach (['Data de entrega' => $deliveredAt, 'Data de compra' => $purchasedAt, 'Fim da garantia' => $warrantyUntil] as $label => $value) {
                if ($value === false) {
                    $errors[] = "{$label} inválida. Use o formato dd/mm/aaaa.";
                }
            }
            if (is_string($purchasedAt) && $purchasedAt > today()->toDateString()) {
                $errors[] = 'Data de compra no futuro.';
            }
            if (is_string($purchasedAt) && is_string($warrantyUntil) && $warrantyUntil < $purchasedAt) {
                $errors[] = 'Fim da garantia anterior à data de compra.';
            }
            if ($price === false) {
                $errors[] = 'Valor de compra inválido. Use só números, como 5899,90.';
            }
            if ($termText && $termSigned === null) {
                $errors[] = 'Termo assinado deve ser Sim ou Não.';
            }

            if ($status === 'em_uso') {
                if (! $responsibleName) {
                    $errors[] = 'Ativo em uso precisa de Responsável.';
                } elseif (! ($employee = $this->resolveEmployee($responsibleName))) {
                    $errors[] = "Responsável {$responsibleName} não existe na aba Colaboradores nem no sistema.";
                }
                if ($deliveredAt === null) {
                    $errors[] = 'Ativo em uso precisa de Data de entrega.';
                } elseif (is_string($deliveredAt) && $deliveredAt > today()->toDateString()) {
                    $errors[] = 'Data de entrega no futuro.';
                }
            } elseif ($responsibleName) {
                $errors[] = 'Responsável só deve ser preenchido quando a situação for Em uso.';
            }

            if ($errors) {
                $this->fail('Ativos', $line, $errors);
                continue;
            }

            $this->createRow('Ativos', $line, function () use (
                $row, $tag, $serial, $categoryName, $status, $location, $purchasedAt, $price, $warrantyUntil,
                $employee, $deliveredAt, $termSigned, $line
            ) {
                $asset = Asset::create([
                    'asset_tag' => $tag,
                    'serial_number' => $serial,
                    'category_id' => $this->catalog(Category::class, $categoryName, 'categorias'),
                    'manufacturer_id' => $this->catalog(Manufacturer::class, $this->text($row['fabricante']), 'fabricantes'),
                    'model' => $this->text($row['modelo']),
                    'description' => $this->text($row['descricao']),
                    'location_id' => $location?->id,
                    // Ativos em uso nascem disponíveis e passam pela entrega oficial logo abaixo
                    'status' => $status === 'em_uso' ? 'disponivel' : $status,
                    'purchase_date' => $purchasedAt,
                    'purchase_price' => $price,
                    'invoice_number' => $this->text($row['nota']),
                    'supplier' => $this->text($row['fornecedor']),
                    'warranty_until' => $warrantyUntil,
                    'notes' => $this->text($row['observacoes']),
                ]);
                $this->result->created('ativos');

                if ($tag) {
                    $this->assetsByTag[$tag] = $asset;
                }
                if ($serial) {
                    $this->assetsBySerial[$serial] = $asset;
                }

                if ($status === 'em_uso') {
                    try {
                        $this->movements->checkout($asset, $employee, $this->by, [
                            'checked_out_at' => $deliveredAt,
                            'location_id' => $location?->id,
                            'term_signed' => $termSigned === true,
                        ]);
                        $this->result->created('entregas');
                    } catch (MovementException $exception) {
                        $this->fail('Ativos', $line, [$exception->getMessage()]);
                    }
                }
            });
        }
    }

    private function importLicenses(array $rows): void
    {
        foreach ($rows as $line => $row) {
            if ($this->isExample('Licenças', $row)) {
                $this->exampleError('Licenças', $line);
                continue;
            }

            $errors = [];
            $softwareName = $this->text($row['software']);
            $name = $this->text($row['nome']);
            $type = $this->option(self::LICENSE_TYPES, $row['tipo']);
            $assignment = $this->option(self::ASSIGNMENT_TYPES, $row['atribuicao']);
            $seats = $this->number($row['quantidade']);
            $purchasedAt = $this->date($row['aquisicao']);
            $expiresAt = $this->date($row['vencimento']);
            $alert = $this->number($row['alerta']);
            $cycleText = $this->text($row['periodicidade']);
            $cycle = $this->option(self::BILLING_CYCLES, $cycleText);
            $cost = $this->number($row['valor']);
            $renewText = $this->text($row['renovacao']);
            $renew = $this->yesNo($renewText);
            $account = $this->text($row['conta']);

            if (! $softwareName) {
                $errors[] = 'Software é obrigatório.';
            }
            if (! $name) {
                $errors[] = 'Nome da licença é obrigatório.';
            } elseif (isset($this->licenses[mb_strtolower($name)]) || License::where('name', $name)->exists()) {
                $errors[] = "Licença {$name} já está cadastrada ou repetida na planilha.";
            }
            if (! $type) {
                $errors[] = 'Tipo inválido. Use Assinatura, Perpétua ou OEM.';
            }
            if (! $assignment) {
                $errors[] = 'Forma de atribuição inválida. Use Por usuário, Por dispositivo ou Corporativa.';
            }
            if ($assignment && $assignment !== 'corporate'
                && (! is_float($seats) || $seats < 1 || floor($seats) !== $seats)) {
                $errors[] = 'Licenças contratadas deve ser um número inteiro a partir de 1.';
            }
            foreach (['Data de aquisição' => $purchasedAt, 'Vencimento' => $expiresAt] as $label => $value) {
                if ($value === false) {
                    $errors[] = "{$label} inválido(a). Use o formato dd/mm/aaaa.";
                }
            }
            if ($type === 'assinatura' && $expiresAt === null) {
                $errors[] = 'Licenças por assinatura precisam de Vencimento.';
            }
            if ($alert === false || (is_float($alert) && ($alert < 1 || $alert > 365))) {
                $errors[] = 'Alertar quantos dias antes deve ser um número entre 1 e 365.';
            }
            if ($cycleText && ! $cycle) {
                $errors[] = 'Periodicidade inválida. Use Mensal, Anual ou Pagamento único.';
            }
            if ($cost === false) {
                $errors[] = 'Valor inválido. Use só números, como 7200,00.';
            }
            if ($renewText && $renew === null) {
                $errors[] = 'Renovação automática deve ser Sim ou Não.';
            }
                        if ($renew === true && $type === 'assinatura' && ! in_array($cycle, ['mensal', 'anual'], true)) {
                $errors[] = 'Renovação automática precisa de Periodicidade Mensal ou Anual.';
            }
            if ($account && ! filter_var($account, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Conta administradora deve ser um e-mail válido.';
            }

            if ($errors) {
                $this->fail('Licenças', $line, $errors);
                continue;
            }

            $this->createRow('Licenças', $line, function () use (
                $row, $softwareName, $name, $type, $assignment, $seats, $purchasedAt, $expiresAt, $alert,
                $cycle, $cost, $renew, $account
            ) {
                $license = License::create([
                    'software_id' => $this->catalog(Software::class, $softwareName, 'softwares'),
                    'name' => $name,
                    'license_type' => $type,
                    'assignment_type' => $assignment,
                    'seats' => $assignment === 'corporate' ? null : (int) $seats,
                    'purchase_date' => $purchasedAt,
                    'expires_at' => $expiresAt,
                    'alert_days' => is_float($alert) ? (int) $alert : 10,
                    'billing_cycle' => $cycle ?: null,
                    'cost' => $cost,
                    'auto_renew' => $renew === true && $type === 'assinatura',
                    'supplier' => $this->text($row['fornecedor']),
                    'invoice_number' => $this->text($row['pedido']),
                    'admin_account_email' => $account ? mb_strtolower($account) : null,
                    'notes' => $this->text($row['observacoes']),
                ]);

                $this->licenses[mb_strtolower($name)] = $license;
                $this->result->created('licencas');
            });
        }
    }

    private function importLicenseUsage(array $rows): void
    {
        foreach ($rows as $line => $row) {
            if ($this->isExample('Uso de licenças', $row)) {
                $this->exampleError('Uso de licenças', $line);
                continue;
            }

            $errors = [];
            $licenseName = $this->text($row['licenca']);
            $employeeName = $this->text($row['colaborador']);
            $assetRef = $this->upper($row['equipamento']);
            $assignedAt = $this->date($row['data']);
            $license = null;
            $employee = null;
            $asset = null;

            if (! $licenseName) {
                $errors[] = 'Nome da licença é obrigatório.';
            } elseif (! ($license = $this->resolveLicense($licenseName))) {
                $errors[] = "Licença {$licenseName} não existe na aba Licenças nem no sistema.";
            }
            if ($employeeName && ! ($employee = $this->resolveEmployee($employeeName))) {
                $errors[] = "Colaborador {$employeeName} não existe na aba Colaboradores nem no sistema.";
            }
            if ($assetRef && ! ($asset = $this->resolveAsset($assetRef))) {
                $errors[] = "Equipamento {$assetRef} não encontrado (patrimônio ou número de série).";
            }
            if ($assignedAt === null) {
                $errors[] = 'Data da atribuição é obrigatória.';
            } elseif ($assignedAt === false) {
                $errors[] = 'Data da atribuição inválida. Use o formato dd/mm/aaaa.';
            } elseif ($assignedAt > today()->toDateString()) {
                $errors[] = 'Data da atribuição no futuro.';
            }

            if ($errors) {
                $this->fail('Uso de licenças', $line, $errors);
                continue;
            }

            try {
                $this->licenseAssignments->assign($license, $this->by, [
                    'employee_id' => $employee?->id,
                    'asset_id' => $asset?->id,
                    'assigned_at' => $assignedAt,
                ]);
                $this->result->created('atribuicoes');
            } catch (MovementException $exception) {
                $this->fail('Uso de licenças', $line, [$exception->getMessage()]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Busca de itens já lidos na planilha ou existentes no sistema
    // ------------------------------------------------------------------

    private function resolveLocation(string $name): ?Location
    {
        return $this->locations[mb_strtolower($name)] ?? Location::where('name', $name)->first();
    }

    private function resolveEmployee(string $name): ?Employee
    {
        return $this->employees[mb_strtolower($name)] ?? Employee::where('name', $name)->first();
    }

    private function resolveAsset(string $reference): ?Asset
    {
        return $this->assetsByTag[$reference]
            ?? $this->assetsBySerial[$reference]
            ?? Asset::where('asset_tag', $reference)->orWhere('serial_number', $reference)->first();
    }

    private function resolveLicense(string $name): ?License
    {
        return $this->licenses[mb_strtolower($name)] ?? License::where('name', $name)->first();
    }

    /** Busca ou cria um item de cadastro simples pelo nome; devolve o id */
    private function catalog(string $class, ?string $name, string $counter): ?int
    {
        if (! $name) {
            return null;
        }

        $item = $class::firstOrCreate(['name' => $name]);

        if ($item->wasRecentlyCreated) {
            $this->result->created($counter);
        }

        return $item->id;
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    /** Grava a linha; se o banco recusar (por exemplo, texto longo demais), registra o erro */
    private function createRow(string $sheet, int $line, callable $create): void
    {
        try {
            $create();
        } catch (QueryException) {
            $this->fail($sheet, $line, ['Algum valor desta linha é longo demais ou inválido. Revise os textos.']);
        }
    }

    private function fail(string $sheet, int $line, array $messages): void
    {
        foreach ($messages as $message) {
            $this->result->error($sheet, $line, $message);
        }
    }

    private function isExample(string $sheet, array $row): bool
    {
        if (! isset(self::EXAMPLES[$sheet])) {
            return false;
        }

        foreach (self::EXAMPLES[$sheet] as $key => $value) {
            if (mb_strtolower((string) $this->text($row[$key] ?? null)) !== mb_strtolower($value)) {
                return false;
            }
        }

        return true;
    }

    private function exampleError(string $sheet, int $line): void
    {
        $this->result->error($sheet, $line, 'Esta é a linha de exemplo do modelo. Apague-a antes de importar.');
    }

    private function clean(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function upper(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : mb_strtoupper($text);
    }

    private function option(array $options, mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : ($options[mb_strtolower($text)] ?? null);
    }

    private function yesNo(?string $text): ?bool
    {
        return match (mb_strtolower((string) $text)) {
            'sim' => true,
            'não', 'nao' => false,
            default => null,
        };
    }

    /** Data no formato do banco (aaaa-mm-dd); null se vazia; false se inválida */
    private function date(mixed $value): string|false|null
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_int($value) || is_float($value)) {
            // Número de série de data do Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        $text = trim((string) $value);

        foreach (['d/m/Y', 'j/n/Y', 'Y-m-d'] as $format) {
            $date = DateTime::createFromFormat('!' . $format, $text);
            if ($date && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }

    /** Número decimal; null se vazio; false se inválido. Aceita "5.899,90" e "5899.90" */
    private function number(mixed $value): float|false|null
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = str_replace(['R$', ' '], '', (string) $value);

        if (str_contains($text, ',')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        }

        return is_numeric($text) ? (float) $text : false;
    }
}