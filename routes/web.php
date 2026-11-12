<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\DepartmentController;
use App\Http\Controllers\Catalog\ManufacturerController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Catalog\SoftwareController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\AssetMovementController;
use App\Http\Controllers\LicenseAssignmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AttachmentController;

// Página inicial: vai direto para o painel (quem não estiver logado cai no login)
Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

// Perfil do próprio usuário (sem autoexclusão de conta)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Gestão de usuários: somente administrador
Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('usuarios')
    ->name('users.')
    ->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/novo', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::patch('/{user}/situacao', [UserController::class, 'toggleActive'])->name('toggle-active');
        Route::put('/{user}/senha', [UserController::class, 'resetPassword'])->name('reset-password');
    });

    // Auditoria: somente administrador
Route::get('/auditoria', [AuditLogController::class, 'index'])
    ->middleware(['auth', 'verified', 'can:view-audit'])
    ->name('audit.index');


    // Importação de planilha: somente administrador
Route::middleware(['auth', 'verified', 'can:import-data', 'throttle:sensitive'])
    ->prefix('importacao')
    ->name('import.')
    ->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('/modelo', [ImportController::class, 'template'])->name('template');
        Route::post('/previa', [ImportController::class, 'preview'])->name('preview');
        Route::post('/confirmar', [ImportController::class, 'store'])->name('store');
    });

    // Backups: somente administrador
Route::middleware(['auth', 'verified', 'can:manage-backups'])
    ->prefix('backups')
    ->name('backups.')
    ->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'store'])->middleware('throttle:sensitive')->name('store');
                Route::get('/anexos.zip', [BackupController::class, 'downloadAttachments'])
            ->middleware('throttle:sensitive')
            ->name('attachments');
        Route::get('/{name}', [BackupController::class, 'download'])
            ->where('name', 'backup-[0-9-]+\.sql(\.gz)?')
            ->middleware('throttle:sensitive')
            ->name('download');
    });


// Cadastros: consulta para qualquer usuário logado, alterações para técnico e admin
Route::middleware(['auth', 'verified'])->group(function () {

    // ---------- Locais e obras ----------
    Route::get('/locais', [LocationController::class, 'index'])->name('locations.index');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('/locais/novo', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locais', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/locais/{location}/editar', [LocationController::class, 'edit'])->name('locations.edit');
        Route::put('/locais/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::patch('/locais/{location}/situacao', [LocationController::class, 'toggleActive'])->name('locations.toggle-active');
    });

        // ---------- Ativos de hardware ----------
    Route::get('/ativos', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/ativos/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('assets.show');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('/ativos/novo', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/ativos', [AssetController::class, 'store'])->name('assets.store');
        Route::get('/ativos/{asset}/editar', [AssetController::class, 'edit'])->whereNumber('asset')->name('assets.edit');
        Route::put('/ativos/{asset}', [AssetController::class, 'update'])->whereNumber('asset')->name('assets.update');
    });

    // Descarte: somente administrador
    Route::patch('/ativos/{asset}/descartar', [AssetController::class, 'dispose'])
        ->whereNumber('asset')
        ->middleware('can:dispose-assets')
        ->name('assets.dispose');


    // ---------- Entrega e devolução de equipamentos ----------
    Route::middleware('can:move-assets')->group(function () {
        Route::get('/ativos/{asset}/entregar', [AssetMovementController::class, 'checkoutForm'])->whereNumber('asset')->name('assets.checkout');
        Route::post('/ativos/{asset}/entregar', [AssetMovementController::class, 'checkout'])->whereNumber('asset')->name('assets.checkout.store');
        Route::get('/ativos/{asset}/devolver', [AssetMovementController::class, 'checkinForm'])->whereNumber('asset')->name('assets.checkin');
        Route::post('/ativos/{asset}/devolver', [AssetMovementController::class, 'checkin'])->whereNumber('asset')->name('assets.checkin.store');
        Route::patch('/entregas/{assignment}/termo', [AssetMovementController::class, 'signTerm'])->whereNumber('assignment')->name('assignments.sign-term');
    });


            // ---------- Licenças de software ----------
    Route::get('/licencas', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('/licencas/{license}', [LicenseController::class, 'show'])->whereNumber('license')->name('licenses.show');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('/licencas/nova', [LicenseController::class, 'create'])->name('licenses.create');
        Route::post('/licencas', [LicenseController::class, 'store'])->name('licenses.store');
        Route::get('/licencas/{license}/editar', [LicenseController::class, 'edit'])->whereNumber('license')->name('licenses.edit');
        Route::put('/licencas/{license}', [LicenseController::class, 'update'])->whereNumber('license')->name('licenses.update');
    });

    // Ver a chave: técnico e administrador
        Route::post('/licencas/{license}/chave', [LicenseController::class, 'revealKey'])
        ->whereNumber('license')
        ->middleware(['can:reveal-license-keys', 'throttle:sensitive'])
        ->name('licenses.reveal-key');

    // Baixa: somente administrador
    Route::patch('/licencas/{license}/baixar', [LicenseController::class, 'retire'])
        ->whereNumber('license')
        ->middleware('can:dispose-assets')
        ->name('licenses.retire');

        // ---------- Atribuição de licenças ----------
    Route::middleware('can:move-assets')->group(function () {
        Route::get('/licencas/{license}/atribuir', [LicenseAssignmentController::class, 'create'])->whereNumber('license')->name('licenses.assign');
        Route::post('/licencas/{license}/atribuir', [LicenseAssignmentController::class, 'store'])->whereNumber('license')->name('licenses.assign.store');
        Route::patch('/atribuicoes-licenca/{assignment}/liberar', [LicenseAssignmentController::class, 'revoke'])->whereNumber('assignment')->name('license-assignments.revoke');
    });    

            // ---------- Anexos (notas fiscais e documentos) ----------
    Route::get('/anexos/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('attachments.show');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::post('/ativos/{asset}/anexos', [AttachmentController::class, 'storeForAsset'])->whereNumber('asset')->name('assets.attachments.store');
        Route::post('/licencas/{license}/anexos', [AttachmentController::class, 'storeForLicense'])->whereNumber('license')->name('licenses.attachments.store');
        Route::delete('/anexos/{attachment}', [AttachmentController::class, 'destroy'])->whereNumber('attachment')->name('attachments.destroy');
    });


        // ---------- Colaboradores ----------
    Route::get('/colaboradores', [EmployeeController::class, 'index'])->name('employees.index');

        Route::get('/colaboradores/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');

    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('/colaboradores/novo', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/colaboradores', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/colaboradores/{employee}/editar', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/colaboradores/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('/colaboradores/{employee}/situacao', [EmployeeController::class, 'toggleActive'])->name('employees.toggle-active');
    });

    // ---------- Setores, categorias e fabricantes ----------
        $catalogs = [
        'setores' => ['departments', DepartmentController::class],
        'categorias' => ['categories', CategoryController::class],
        'fabricantes' => ['manufacturers', ManufacturerController::class],
        'softwares' => ['software', SoftwareController::class],
    ];

    foreach ($catalogs as $uri => [$name, $controller]) {
        // Consulta: qualquer usuário logado
        Route::get("/{$uri}", [$controller, 'index'])->name("{$name}.index");

        // Alterações: somente técnico e administrador
        Route::middleware('can:manage-inventory')->group(function () use ($uri, $name, $controller) {
            Route::get("/{$uri}/novo", [$controller, 'create'])->name("{$name}.create");
            Route::post("/{$uri}", [$controller, 'store'])->name("{$name}.store");
            Route::get("/{$uri}/{id}/editar", [$controller, 'edit'])->whereNumber('id')->name("{$name}.edit");
            Route::put("/{$uri}/{id}", [$controller, 'update'])->whereNumber('id')->name("{$name}.update");
            Route::patch("/{$uri}/{id}/situacao", [$controller, 'toggleActive'])->whereNumber('id')->name("{$name}.toggle-active");
        });
    }
});

require __DIR__.'/auth.php';