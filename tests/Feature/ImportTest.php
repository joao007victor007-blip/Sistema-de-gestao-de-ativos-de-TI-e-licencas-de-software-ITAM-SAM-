<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\Location;
use App\Models\User;
use App\Services\Import\InventoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    /** Monta um .xlsx no formato do modelo, com as linhas informadas */
    private function workbook(array $data): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'importacao') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $first = true;

        foreach (InventoryImporter::COLUMNS as $sheetName => $columns) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($sheetName);
            $first = false;

            $writer->addRow(Row::fromValues(array_values($columns)));

            foreach ($data[$sheetName] ?? [] as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($key) => $row[$key] ?? null, array_keys($columns))));
            }
        }

        $writer->close();

        return new UploadedFile($path, 'inventario.xlsx', null, null, true);
    }

    private function validData(): array
    {
        return [
            'Locais' => [['nome' => 'Obra Jardins', 'tipo' => 'Obra', 'cidade' => 'São Paulo', 'uf' => 'SP']],
            'Colaboradores' => [[
                'nome' => 'Joana Importada', 'matricula' => '9001', 'email' => 'joana@exemplo.com.br',
                'cargo' => 'Engenheira', 'setor' => 'Engenharia', 'local' => 'Obra Jardins',
            ]],
            'Ativos' => [[
                'patrimonio' => '0999', 'categoria' => 'Notebook', 'fabricante' => 'Dell', 'modelo' => 'Latitude 5440',
                'serie' => 'SERIE-IMP-1', 'local' => 'Obra Jardins', 'situacao' => 'Em uso',
                'responsavel' => 'Joana Importada', 'data_entrega' => '01/03/2025', 'termo' => 'Sim', 'valor' => '5.899,90',
            ]],
            'Licenças' => [[
                'software' => 'Microsoft 365', 'nome' => 'M365 Importado', 'tipo' => 'Assinatura',
                'atribuicao' => 'Por usuário', 'quantidade' => 5, 'vencimento' => today()->addYear()->format('d/m/Y'),
            ]],
            'Uso de licenças' => [['licenca' => 'M365 Importado', 'colaborador' => 'Joana Importada', 'data' => '05/01/2026']],
        ];
    }

    public function test_preview_writes_nothing_and_confirmation_imports_everything(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('import.preview'), ['planilha' => $this->workbook($this->validData())])
            ->assertOk()
            ->assertSee('Nenhum problema encontrado');

        $this->assertSame(0, Asset::count());
        $this->assertSame(0, Employee::count());

        $this->actingAs($admin)->post(route('import.store'))->assertRedirect(route('import.index'));

        $asset = Asset::where('asset_tag', '0999')->firstOrFail();
        $this->assertSame(AssetStatus::EmUso, $asset->status);
        $this->assertTrue($asset->currentAssignment->term_signed);
        $this->assertSame('5899.90', $asset->purchase_price);
        $this->assertSame(1, License::where('name', 'M365 Importado')->first()->activeAssignments()->count());
        $this->assertDatabaseHas('categories', ['name' => 'Notebook']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'importacao']);
    }

    public function test_errors_are_listed_and_nothing_is_imported(): void
    {
        $admin = User::factory()->admin()->create();
        $data = $this->validData();
        $data['Ativos'][0]['responsavel'] = 'Pessoa Inexistente';
        $data['Licenças'][0]['vencimento'] = null;

        $this->actingAs($admin)
            ->post(route('import.preview'), ['planilha' => $this->workbook($data)])
            ->assertOk()
            ->assertSee('Pessoa Inexistente')
            ->assertSee('Licenças por assinatura precisam de Vencimento')
            ->assertDontSee('Nenhum problema encontrado');

        $this->actingAs($admin)->post(route('import.store'))->assertRedirect(route('import.index'));

        $this->assertSame(0, Asset::count());
        $this->assertSame(0, License::count());
    }

    public function test_forgotten_example_row_is_flagged(): void
    {
        $data = $this->validData();
        $data['Colaboradores'][0]['email'] = 'maria.silva@exemplo.com.br';

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('import.preview'), ['planilha' => $this->workbook($data)])
            ->assertSee('linha de exemplo do modelo');
    }

    public function test_existing_serial_number_is_rejected(): void
    {
        Asset::factory()->create(['serial_number' => 'SERIE-IMP-1']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('import.preview'), ['planilha' => $this->workbook($this->validData())])
            ->assertSee('SERIE-IMP-1 já está cadastrado');
    }

    public function test_existing_location_is_reused(): void
    {
        $admin = User::factory()->admin()->create();
        Location::factory()->create(['name' => 'Obra Jardins']);

        $this->actingAs($admin)->post(route('import.preview'), ['planilha' => $this->workbook($this->validData())]);
        $this->actingAs($admin)->post(route('import.store'));

        $this->assertSame(1, Location::where('name', 'Obra Jardins')->count());
        $this->assertSame(1, Asset::count());
    }

    public function test_only_admin_can_import(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())->get(route('import.index'))->assertForbidden();
        $this->actingAs(User::factory()->leitura()->create())->get(route('import.index'))->assertForbidden();
    }
}