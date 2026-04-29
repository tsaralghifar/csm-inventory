<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FuelLogController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\MaterialRequestController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SuratJalanController;
use App\Models\Category;
use App\Models\DeliveryOrder;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalaryComponent;
use App\Models\MainCashAccount;
use App\Models\MainCashTransaction;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PettyCashAccount;
use App\Models\PettyCashTransaction;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('api.limit:strict');

// Protected routes
Route::middleware(['auth:sanctum', 'api.limit:standard'])->group(function () {

    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::middleware('api.limit:relaxed')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    // Notifikasi
    Route::prefix('notifications')->middleware('api.limit:relaxed')->group(function () {
        Route::get('/',              [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/low-stock',    [NotificationController::class, 'lowStockSummary']);
        Route::post('/read-all',    [NotificationController::class, 'markAllRead']);
        Route::post('/{id}/read',   [NotificationController::class, 'markRead']);
    });

    // Warehouses
    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::post('/warehouses', [WarehouseController::class, 'store']);
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
    Route::get('/warehouses/{warehouse}/stocks', [WarehouseController::class, 'stocks']);

    // Items
    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{item}', [ItemController::class, 'show']);
    Route::put('/items/{item}', [ItemController::class, 'update']);
    Route::delete('/items/{item}', [ItemController::class, 'destroy']);
    Route::post('/items/{item}/stock-in', [ItemController::class, 'stockIn']);
    Route::post('/items/{item}/stock-out', [ItemController::class, 'stockOut']);
    Route::get('/items/{item}/movements', [ItemController::class, 'movements']);
    Route::get('/items/{item}/price-history', function (Request $req, \App\Models\Item $item) {
        $query = \App\Models\ItemPriceHistory::with(['warehouse', 'creator'])
            ->where('item_id', $item->id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');
        if ($req->warehouse_id) $query->where('warehouse_id', $req->warehouse_id);
        $data = $query->paginate($req->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $data->items(), 'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });

    // Categories
    Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);

    // Units
    Route::get('/units', function (Request $req) {
        $q = Unit::with('warehouse');
        if ($req->warehouse_id) $q->where('warehouse_id', $req->warehouse_id);
        if ($req->search) $q->where('unit_code', 'ilike', "%{$req->search}%");
        return response()->json(['success' => true, 'data' => $q->get()]);
    });
    Route::get('/units/{unit}/parts-history', function (Request $req, Unit $unit) {
        $query = \App\Models\BonPengeluaran::with(['items.item', 'warehouse', 'creator'])
            ->where('unit_code', $unit->unit_code)
            ->where('status', 'issued')
            ->orderBy('issue_date', 'desc');
        if ($req->date_from) $query->whereDate('issue_date', '>=', $req->date_from);
        if ($req->date_to)   $query->whereDate('issue_date', '<=', $req->date_to);
        $data = $query->paginate($req->per_page ?? 20);
        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    });
    Route::post('/units', function (Request $req) {
        $v = $req->validate(['unit_code'=>'required|unique:units','type_unit'=>'required','brand'=>'nullable|string','warehouse_id'=>'nullable|exists:warehouses,id','status'=>'nullable|in:active,standby,maintenance,retired']);
        $unit = Unit::create($v);
        broadcast(new \App\Events\MasterDataUpdated('unit','created',$unit->id))->toOthers();
        return response()->json(['success'=>true,'data'=>$unit],201);
    });
    Route::put('/units/{unit}', function (Request $req, Unit $unit) {
        $unit->update($req->only(['type_unit','brand','hm_current','warehouse_id','status','is_active']));
        broadcast(new \App\Events\MasterDataUpdated('unit','updated',$unit->id))->toOthers();
        return response()->json(['success'=>true,'data'=>$unit]);
    });

    // Material Requests
    Route::get('/material-requests', [MaterialRequestController::class, 'index']);
    Route::post('/material-requests', [MaterialRequestController::class, 'store']);
    Route::get('/material-requests/{materialRequest}', [MaterialRequestController::class, 'show']);
    Route::post('/material-requests/{mr}/submit', [MaterialRequestController::class, 'submit']);
    Route::post('/material-requests/{mr}/authorize-chief', [MaterialRequestController::class, 'authorizeChief']);
    Route::post('/material-requests/{mr}/approve-manager', [MaterialRequestController::class, 'approveManager']);
    Route::post('/material-requests/{mr}/approve-ho', [MaterialRequestController::class, 'approveHO']);
    Route::post('/material-requests/{mr}/reject', [MaterialRequestController::class, 'reject']);
    Route::post('/material-requests/{mr}/dispatch', [MaterialRequestController::class, 'dispatchMR']);

    // Transfer Barang
    Route::get('/transfer-barang', [\App\Http\Controllers\Api\TransferBarangController::class, 'index']);
    Route::post('/transfer-barang', [\App\Http\Controllers\Api\TransferBarangController::class, 'store']);
    Route::get('/transfer-barang/{mr}', [\App\Http\Controllers\Api\TransferBarangController::class, 'show']);
    Route::post('/transfer-barang/{mr}/submit', [\App\Http\Controllers\Api\TransferBarangController::class, 'submit']);
    Route::post('/transfer-barang/{mr}/approve-admin', [\App\Http\Controllers\Api\TransferBarangController::class, 'approveAdmin']);
    Route::post('/transfer-barang/{mr}/approve-atasan', [\App\Http\Controllers\Api\TransferBarangController::class, 'approveAtasan']);
    Route::post('/transfer-barang/{mr}/kirim', [\App\Http\Controllers\Api\TransferBarangController::class, 'kirim']);
    Route::post('/transfer-barang/{mr}/reject', [\App\Http\Controllers\Api\TransferBarangController::class, 'reject']);
    Route::delete('/transfer-barang/{mr}', [\App\Http\Controllers\Api\TransferBarangController::class, 'destroy']);
    Route::post('/transfer-barang/delivery/{do}/terima', [\App\Http\Controllers\Api\TransferBarangController::class, 'terima']);

    // Purchase Orders
    Route::get('/purchase-orders', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'show']);
    Route::post('/purchase-orders/{purchaseOrder}/send', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'sendToVendor']);

    // Retur Barang
    Route::get('/retur-barang', [\App\Http\Controllers\Api\ReturBarangController::class, 'index']);
    Route::post('/retur-barang', [\App\Http\Controllers\Api\ReturBarangController::class, 'store']);
    Route::get('/retur-barang/{returBarang}', [\App\Http\Controllers\Api\ReturBarangController::class, 'show']);
    Route::post('/retur-barang/{returBarang}/confirm', [\App\Http\Controllers\Api\ReturBarangController::class, 'confirm']);

    // Bon Pengeluaran
    Route::get('/bon-pengeluaran', [\App\Http\Controllers\Api\BonPengeluaranController::class, 'index']);
    Route::post('/bon-pengeluaran', [\App\Http\Controllers\Api\BonPengeluaranController::class, 'store']);
    Route::get('/bon-pengeluaran/{bonPengeluaran}', [\App\Http\Controllers\Api\BonPengeluaranController::class, 'show']);
    Route::post('/bon-pengeluaran/{bonPengeluaran}/issue', [\App\Http\Controllers\Api\BonPengeluaranController::class, 'issue']);

    // Surat Jalan
    
    Route::prefix('surat-jalan')->group(function () {
        Route::get('/', [SuratJalanController::class, 'index']);
        Route::post('/', [SuratJalanController::class, 'store']);

        // Endpoint khusus harus didaftarkan SEBELUM /{suratJalan} agar tidak tertangkap sebagai ID
        // GET /api/surat-jalan/po/{po}/remaining
        Route::get('/po/{po}/remaining', [SuratJalanController::class, 'remaining']);

        Route::get('/{suratJalan}', [SuratJalanController::class, 'show']);
        Route::post('/{suratJalan}/receive', [SuratJalanController::class, 'receive']);
    });

    // Route::get('/surat-jalan', [\App\Http\Controllers\Api\SuratJalanController::class, 'index']);
    // Route::post('/surat-jalan', [\App\Http\Controllers\Api\SuratJalanController::class, 'store']);
    // Route::get('/surat-jalan/{suratJalan}', [\App\Http\Controllers\Api\SuratJalanController::class, 'show']);
    // Route::post('/surat-jalan/{suratJalan}/receive', [\App\Http\Controllers\Api\SuratJalanController::class, 'receive']);

    // Permintaan Material
    Route::get('/permintaan-material', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'index']);
    Route::post('/permintaan-material', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'store']);
    Route::get('/permintaan-material/{permintaanMaterial}', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'show']);
    Route::get('/permintaan-material/{pm}/export-excel', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'exportExcel']);
    Route::post('/permintaan-material/{pm}/submit', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'submit']);
    Route::post('/permintaan-material/{pm}/authorize-chief', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'authorizeChief']);
    Route::post('/permintaan-material/{pm}/approve-manager', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'approveManager']);
    Route::post('/permintaan-material/{pm}/approve-ho', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'approveHO']);
    Route::post('/permintaan-material/{pm}/submit-purchasing', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'submitPurchasing']);
    Route::post('/permintaan-material/{pm}/reject', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'reject']);
    Route::delete('/permintaan-material/{pm}', [\App\Http\Controllers\Api\PermintaanMaterialController::class, 'destroy']);

    // Delivery Orders
    Route::get('/delivery-orders', function (Request $req) {
        $q = DeliveryOrder::with(['fromWarehouse','toWarehouse','sender','materialRequest'])->orderBy('created_at','desc');
        if ($req->status) $q->where('status', $req->status);
        $dos = $q->paginate(15);
        return response()->json(['success'=>true,'data'=>$dos->items(),'meta'=>['total'=>$dos->total(),'page'=>$dos->currentPage(),'last_page'=>$dos->lastPage()]]);
    });
    Route::get('/delivery-orders/{do}', function (DeliveryOrder $do) {
        return response()->json(['success'=>true,'data'=>$do->load(['fromWarehouse','toWarehouse','sender','receiver','items.item','materialRequest'])]);
    });
    Route::post('/delivery-orders/{do}/receive', function (Request $req, DeliveryOrder $do) {
        app(\App\Services\MaterialRequestService::class)->confirmReceive($do, $req->all(), $req->user()->id);
        return response()->json(['success'=>true,'message'=>'Barang berhasil dikonfirmasi diterima']);
    });

    // Stock Movements
    Route::get('/stock-movements', function (Request $req) {
        $q = StockMovement::with(['item','fromWarehouse','toWarehouse','creator'])->orderBy('created_at','desc');
        if ($req->warehouse_id) $q->where(fn($q2)=>$q2->where('from_warehouse_id',$req->warehouse_id)->orWhere('to_warehouse_id',$req->warehouse_id));
        if ($req->type) $q->where('type',$req->type);
        if ($req->date_from) $q->where('movement_date','>=',$req->date_from);
        if ($req->date_to) $q->where('movement_date','<=',$req->date_to);
        $movements = $q->paginate($req->per_page ?? 20);
        return response()->json(['success'=>true,'data'=>$movements->items(),'meta'=>['total'=>$movements->total(),'page'=>$movements->currentPage(),'last_page'=>$movements->lastPage()]]);
    });

    // Fuel Logs
    Route::get('/fuel-logs', [FuelLogController::class, 'index']);
    Route::post('/fuel-logs', [FuelLogController::class, 'store']);
    Route::put('/fuel-logs/{fuelLog}', [FuelLogController::class, 'update']);
    Route::delete('/fuel-logs/{fuelLog}', [FuelLogController::class, 'destroy']);

    // APD
    Route::get('/apd', function(Request $req) {
        $q = \App\Models\ApdDistribution::with(['employee','item','warehouse','creator'])->orderBy('distribution_date','desc');
        if ($req->warehouse_id) $q->where('warehouse_id',$req->warehouse_id);
        if ($req->employee_id) $q->where('employee_id',$req->employee_id);
        if ($req->month) $q->whereRaw("TO_CHAR(distribution_date,'YYYY-MM') = ?",[$req->month]);
        $apd = $q->paginate(20);
        return response()->json(['success'=>true,'data'=>$apd->items(),'meta'=>['total'=>$apd->total(),'page'=>$apd->currentPage()]]);
    });
    Route::post('/apd', function(Request $req) {
        $v = $req->validate(['distribution_date'=>'required|date','employee_id'=>'required|exists:employees,id','item_id'=>'required|exists:items,id','warehouse_id'=>'required|exists:warehouses,id','qty'=>'required|numeric|min:0.01','size'=>'nullable|string','brand'=>'nullable|string','handed_by'=>'nullable|string','notes'=>'nullable|string']);
        $v['created_by'] = $req->user()->id;
        $apd = \App\Models\ApdDistribution::create($v);
        broadcast(new \App\Events\MasterDataUpdated('apd','created',$apd->id))->toOthers();
        return response()->json(['success'=>true,'data'=>$apd->load('employee','item','warehouse'),'message'=>'APD berhasil dicatat'],201);
    });

    // Employees
    Route::get('/employees', function(Request $req) {
        $q = Employee::with('warehouse');
        if ($req->warehouse_id) $q->where('warehouse_id',$req->warehouse_id);
        if ($req->search) $q->where('name','ilike',"%{$req->search}%");
        return response()->json(['success'=>true,'data'=>$q->get()]);
    });
    Route::post('/employees', function(Request $req) {
        $v = $req->validate(['employee_id'=>'required|unique:employees','name'=>'required','position'=>'required','warehouse_id'=>'nullable|exists:warehouses,id']);
        $emp = Employee::create($v);
        broadcast(new \App\Events\MasterDataUpdated('karyawan','created',$emp->id))->toOthers();
        return response()->json(['success'=>true,'data'=>$emp],201);
    });
    Route::put('/employees/{employee}', function(Request $req, Employee $employee) {
        $employee->update($req->only(['name','position','warehouse_id','is_active']));
        broadcast(new \App\Events\MasterDataUpdated('karyawan','updated',$employee->id))->toOthers();
        return response()->json(['success'=>true,'data'=>$employee]);
    });

    // Reports
    Route::get('/reports/stock', [ReportController::class, 'stockReport']);
    Route::get('/reports/movements', [ReportController::class, 'movementReport']);
    Route::get('/reports/pengeluaran', [ReportController::class, 'pengeluaranReport']);
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf']);

    // Admin: Users & Roles (Superuser only)
    Route::middleware('role:superuser')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('/roles', [UserController::class, 'roles']);
        Route::get('/permissions', [UserController::class, 'permissions']);
        Route::post('/roles/update-permissions', [UserController::class, 'updateRolePermissions']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // ACCOUNTING
    // ════════════════════════════════════════════════════════════════════════

    // Suppliers
    Route::get('/suppliers', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = Supplier::query();
        if ($req->search) $q->where(fn($x) => $x->where('name', 'ilike', "%{$req->search}%")->orWhere('code', 'ilike', "%{$req->search}%"));
        if ($req->has('active') && $req->input('active') !== '') $q->where('is_active', $req->boolean('active'));
        return response()->json(['success' => true, 'data' => $q->orderBy('name')->get()]);
    });
    Route::post('/suppliers', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate([
            'code' => 'required|string|max:20|unique:suppliers', 'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:100', 'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100', 'address' => 'nullable|string',
            'npwp' => 'nullable|string|max:30', 'notes' => 'nullable|string',
        ]);
        $supplier = Supplier::create($v);
        broadcast(new \App\Events\AccountingUpdated('supplier', 'created', $supplier->id))->toOthers();
        return response()->json(['success' => true, 'data' => $supplier, 'message' => 'Supplier berhasil ditambahkan'], 201);
    });
    Route::put('/suppliers/{supplier}', function (Request $req, Supplier $supplier) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $supplier->update($req->validate([
            'name' => 'sometimes|string|max:255', 'contact_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:100',
            'address' => 'nullable|string', 'npwp' => 'nullable|string|max:30',
            'is_active' => 'sometimes|boolean', 'notes' => 'nullable|string',
        ]));
        broadcast(new \App\Events\AccountingUpdated('supplier', 'updated', $supplier->id))->toOthers();
        return response()->json(['success' => true, 'data' => $supplier, 'message' => 'Supplier berhasil diperbarui']);
    });

    // Invoice Supplier
    Route::get('/supplier-invoices', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = SupplierInvoice::with('supplier')->orderBy('invoice_date', 'desc');
        if ($req->supplier_id) $q->where('supplier_id', $req->supplier_id);
        if ($req->status) { $q->whereIn('status', explode(',', $req->status)); }
        if ($req->search) $q->where(fn($x) => $x->where('invoice_number', 'ilike', "%{$req->search}%")->orWhere('internal_number', 'ilike', "%{$req->search}%"));
        $data = $q->paginate($req->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $data->items(),
            'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });
    Route::post('/supplier-invoices', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate([
            'invoice_number' => 'required|string|unique:supplier_invoices',
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
        ]);
        $v['tax_amount']       = $v['tax_amount'] ?? 0;
        $v['total_amount']     = $v['subtotal'] + $v['tax_amount'];
        $v['remaining_amount'] = $v['total_amount'];
        $v['internal_number']  = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $v['created_by']       = $req->user()->id;

        // Validasi PO jika dipilih
        if (!empty($v['purchase_order_id'])) {
            $po = \App\Models\PurchaseOrder::findOrFail($v['purchase_order_id']);

            // PO yang sudah completed (lunas/selesai) tidak bisa dibuat invoice baru
            if ($po->status === 'completed') {
                return response()->json(['success' => false, 'message' => "PO {$po->po_number} sudah berstatus selesai/lunas, tidak bisa dibuat invoice baru"], 422);
            }
            if ($po->status === 'cancelled') {
                return response()->json(['success' => false, 'message' => "PO {$po->po_number} sudah dibatalkan"], 422);
            }

            // Cek total invoice yang sudah dibuat untuk PO ini (exclude cancelled)
            $totalInvoiced = SupplierInvoice::where('purchase_order_id', $po->id)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');
            $poTotal = (float) $po->grand_total;
            $sisaBisaInvoice = $poTotal - $totalInvoiced;

            if ($sisaBisaInvoice <= 0) {
                return response()->json(['success' => false, 'message' => "PO {$po->po_number} sudah sepenuhnya diinvoice (total PO: Rp " . number_format($poTotal, 0, ',', '.') . ", sudah diinvoice: Rp " . number_format($totalInvoiced, 0, ',', '.') . ")"], 422);
            }

            if ($v['total_amount'] > $sisaBisaInvoice) {
                return response()->json(['success' => false, 'message' => "Nilai invoice melebihi sisa nilai PO yang belum diinvoice. Sisa yang bisa diinvoice: Rp " . number_format($sisaBisaInvoice, 0, ',', '.')], 422);
            }
        }

        $invoice = SupplierInvoice::create($v);
        $invoice->supplier->increment('outstanding_balance', $invoice->total_amount);
        broadcast(new \App\Events\AccountingUpdated('invoice', 'created', $invoice->id))->toOthers();
        return response()->json(['success' => true, 'data' => $invoice->load('supplier'), 'message' => 'Invoice berhasil ditambahkan'], 201);
    });
    Route::get('/supplier-invoices/{invoice}', function (SupplierInvoice $invoice) {
        return response()->json(['success' => true, 'data' => $invoice->load('supplier', 'payments')]);
    });

    // Pembayaran Supplier
    Route::get('/supplier-payments', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = SupplierPayment::with(['supplier', 'invoice'])->orderBy('payment_date', 'desc');
        if ($req->supplier_id) $q->where('supplier_id', $req->supplier_id);
        if ($req->status)      $q->where('status', $req->status);
        $data = $q->paginate($req->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $data->items(),
            'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });
    Route::post('/supplier-payments', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'main_cash_account_id' => 'nullable|exists:main_cash_accounts,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:transfer,cash,giro,cek',
            'reference_number' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $invoice = SupplierInvoice::findOrFail($v['supplier_invoice_id']);
        if ($invoice->status === 'paid')
            return response()->json(['success' => false, 'message' => 'Invoice ini sudah lunas, tidak bisa diproses lagi'], 422);
        if ($invoice->status === 'cancelled')
            return response()->json(['success' => false, 'message' => 'Invoice ini sudah dibatalkan'], 422);
        if ($invoice->remaining_amount <= 0)
            return response()->json(['success' => false, 'message' => 'Sisa tagihan sudah nol, invoice sudah lunas'], 422);
        if ($v['amount'] > $invoice->remaining_amount)
            return response()->json(['success' => false, 'message' => 'Jumlah pembayaran melebihi sisa tagihan (Rp ' . number_format($invoice->remaining_amount, 0, ',', '.') . ')'], 422);
        $v['payment_number'] = 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $v['created_by']     = $req->user()->id;
        $payment = SupplierPayment::create($v);
        broadcast(new \App\Events\AccountingUpdated('payment', 'created', $payment->id))->toOthers();
        return response()->json(['success' => true, 'data' => $payment->load('supplier', 'invoice'), 'message' => 'Pembayaran berhasil dicatat'], 201);
    });
    Route::post('/supplier-payments/{payment}/approve', function (Request $req, SupplierPayment $payment) {
        if (!$req->user()->hasPermissionTo('approve-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($payment->status !== 'pending')
            return response()->json(['success' => false, 'message' => 'Pembayaran sudah diproses'], 422);
        if ($payment->invoice->status === 'paid')
            return response()->json(['success' => false, 'message' => 'Invoice terkait sudah lunas'], 422);
        if ($payment->invoice->remaining_amount <= 0)
            return response()->json(['success' => false, 'message' => 'Sisa tagihan sudah nol, tidak perlu diproses'], 422);
        $payment->update(['status' => 'approved', 'approved_by' => $req->user()->id, 'approved_at' => now()]);
        $invoice = $payment->invoice;
        $invoice->increment('paid_amount', $payment->amount);
        $invoice->decrement('remaining_amount', $payment->amount);
        $invoice->update(['status' => $invoice->fresh()->remaining_amount <= 0 ? 'paid' : 'partial']);
        $payment->supplier->decrement('outstanding_balance', $payment->amount);
        if ($payment->main_cash_account_id) {
            MainCashTransaction::create([
                'transaction_number'   => 'MCT-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'main_cash_account_id' => $payment->main_cash_account_id,
                'type' => 'out', 'amount' => $payment->amount,
                'description' => "Pembayaran invoice {$invoice->invoice_number} ke {$payment->supplier->name}",
                'reference_number' => $payment->payment_number,
                'transaction_date' => $payment->payment_date,
                'status' => 'approved', 'created_by' => $req->user()->id,
                'approved_by' => $req->user()->id, 'approved_at' => now(),
            ]);
            $payment->mainCashAccount->decrement('balance', $payment->amount);
        }
        // Auto-create journal entry (double-entry)
        app(\App\Services\JournalService::class)->fromSupplierPayment($payment->fresh(), $req->user()->id);
        broadcast(new \App\Events\AccountingUpdated('payment', 'approved', $payment->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Pembayaran disetujui dan dicatat ke kas']);
    });
    Route::post('/supplier-payments/{payment}/reject', function (Request $req, SupplierPayment $payment) {
        if (!$req->user()->hasPermissionTo('approve-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($payment->status !== 'pending')
            return response()->json(['success' => false, 'message' => 'Pembayaran sudah diproses'], 422);
        $payment->update(['status' => 'rejected', 'approved_by' => $req->user()->id, 'approved_at' => now(),
            'notes' => $req->notes ? "Ditolak: {$req->notes}" : 'Ditolak oleh accounting']);
        broadcast(new \App\Events\AccountingUpdated('payment', 'rejected', $payment->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Pembayaran ditolak']);
    });

    // Kas Kecil
    Route::get('/petty-cash/accounts', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        return response()->json(['success' => true, 'data' => PettyCashAccount::with('warehouse')->get()]);
    });
    Route::post('/petty-cash/accounts', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate(['name' => 'required|string', 'warehouse_id' => 'nullable|exists:warehouses,id', 'limit' => 'nullable|numeric|min:0', 'notes' => 'nullable|string']);
        $pca = PettyCashAccount::create($v);
        broadcast(new \App\Events\AccountingUpdated('kas-kecil', 'created', $pca->id))->toOthers();
        return response()->json(['success' => true, 'data' => $pca], 201);
    });
    Route::get('/petty-cash/transactions', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = PettyCashTransaction::with(['account', 'creator', 'mainCashAccount'])->orderBy('transaction_date', 'desc');
        if ($req->account_id) $q->where('petty_cash_account_id', $req->account_id);
        if ($req->type)       $q->where('type', $req->type);
        if ($req->status)     $q->where('status', $req->status);
        if ($req->date_from)  $q->whereDate('transaction_date', '>=', $req->date_from);
        if ($req->date_to)    $q->whereDate('transaction_date', '<=', $req->date_to);
        $data = $q->paginate($req->per_page ?? 500);
        return response()->json(['success' => true, 'data' => $data->items(),
            'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });
    Route::post('/petty-cash/transactions', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate([
            'petty_cash_account_id' => 'required|exists:petty_cash_accounts,id',
            'main_cash_account_id'  => 'nullable|exists:main_cash_accounts,id',
            'type' => 'required|in:in,out', 'amount' => 'required|numeric|min:1',
            'description' => 'required|string', 'reference_number' => 'nullable|string',
            'transaction_date' => 'required|date', 'notes' => 'nullable|string',
        ]);
        // Top-up kas kecil (type=in) wajib memilih sumber rekening kas besar
        if ($v['type'] === 'in' && empty($v['main_cash_account_id'])) {
            return response()->json(['success' => false, 'message' => 'Pilih rekening kas besar sebagai sumber dana top-up'], 422);
        }
        // Cek saldo kas besar cukup (saat top-up)
        if ($v['type'] === 'in' && !empty($v['main_cash_account_id'])) {
            $mainAcc = MainCashAccount::findOrFail($v['main_cash_account_id']);
            if ($mainAcc->balance < $v['amount']) {
                return response()->json(['success' => false,
                    'message' => "Saldo rekening {$mainAcc->name} tidak cukup (saldo: Rp " . number_format($mainAcc->balance, 0, ',', '.') . ")"], 422);
            }
        }
        $v['transaction_number'] = 'PCK-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $v['created_by']         = $req->user()->id;
        $pct = PettyCashTransaction::create($v);
        broadcast(new \App\Events\AccountingUpdated('kas-kecil', 'created', $pct->id))->toOthers();
        return response()->json(['success' => true, 'data' => $pct->load('account', 'mainCashAccount'), 'message' => 'Transaksi kas kecil berhasil dicatat'], 201);
    });
    Route::post('/petty-cash/transactions/{trx}/approve', function (Request $req, PettyCashTransaction $trx) {
        if (!$req->user()->hasPermissionTo('approve-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($trx->status !== 'pending') return response()->json(['success' => false, 'message' => 'Sudah diproses'], 422);

        // Validasi ulang saldo kas besar saat approve (untuk top-up)
        if ($trx->type === 'in' && $trx->main_cash_account_id) {
            $mainAcc = MainCashAccount::findOrFail($trx->main_cash_account_id);
            if ($mainAcc->balance < $trx->amount) {
                return response()->json(['success' => false,
                    'message' => "Saldo rekening {$mainAcc->name} tidak cukup saat approve (saldo: Rp " . number_format($mainAcc->balance, 0, ',', '.') . ")"], 422);
            }
        }

        $trx->update(['status' => 'approved', 'approved_by' => $req->user()->id, 'approved_at' => now()]);

        if ($trx->type === 'in') {
            // Kas kecil bertambah
            $trx->account->increment('balance', $trx->amount);
            // Kas besar berkurang (sumber top-up)
            if ($trx->main_cash_account_id) {
                MainCashAccount::find($trx->main_cash_account_id)->decrement('balance', $trx->amount);
            }
        } else {
            // Kas kecil berkurang (pengeluaran biasa)
            $trx->account->decrement('balance', $trx->amount);
        }

        // Auto-create journal entry
        app(\App\Services\JournalService::class)->fromPettyCash($trx->fresh(), $req->user()->id);
        broadcast(new \App\Events\AccountingUpdated('kas-kecil', 'approved', $trx->id))->toOthers();
        broadcast(new \App\Events\AccountingUpdated('kas-besar', 'approved', $trx->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Transaksi kas kecil disetujui']);
    });

    // Kas Besar
    Route::get('/main-cash/accounts', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        return response()->json(['success' => true, 'data' => MainCashAccount::all()]);
    });
    Route::post('/main-cash/accounts', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate(['name' => 'required|string', 'account_number' => 'nullable|string', 'bank_name' => 'nullable|string', 'notes' => 'nullable|string']);
        $mca = MainCashAccount::create($v);
        broadcast(new \App\Events\AccountingUpdated('kas-besar', 'created', $mca->id))->toOthers();
        return response()->json(['success' => true, 'data' => $mca], 201);
    });
    Route::get('/main-cash/transactions', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = MainCashTransaction::with(['account', 'creator'])->orderBy('transaction_date', 'desc');
        if ($req->account_id) $q->where('main_cash_account_id', $req->account_id);
        if ($req->type)       $q->where('type', $req->type);
        if ($req->status)     $q->where('status', $req->status);
        if ($req->date_from)  $q->whereDate('transaction_date', '>=', $req->date_from);
        if ($req->date_to)    $q->whereDate('transaction_date', '<=', $req->date_to);
        $data = $q->paginate($req->per_page ?? 500);
        return response()->json(['success' => true, 'data' => $data->items(),
            'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });
    Route::post('/main-cash/transactions', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate([
            'main_cash_account_id' => 'required|exists:main_cash_accounts,id',
            'type' => 'required|in:in,out', 'amount' => 'required|numeric|min:1',
            'description' => 'required|string', 'reference_number' => 'nullable|string',
            'transaction_date' => 'required|date', 'notes' => 'nullable|string',
        ]);
        $v['transaction_number'] = 'MCT-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $v['created_by']         = $req->user()->id;
        $mct = MainCashTransaction::create($v);
        broadcast(new \App\Events\AccountingUpdated('kas-besar', 'created', $mct->id))->toOthers();
        return response()->json(['success' => true, 'data' => $mct->load('account'), 'message' => 'Transaksi kas besar berhasil dicatat'], 201);
    });
    Route::post('/main-cash/transactions/{trx}/approve', function (Request $req, MainCashTransaction $trx) {
        if (!$req->user()->hasPermissionTo('approve-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($trx->status !== 'pending') return response()->json(['success' => false, 'message' => 'Sudah diproses'], 422);
        $trx->update(['status' => 'approved', 'approved_by' => $req->user()->id, 'approved_at' => now()]);
        $trx->type === 'in' ? $trx->account->increment('balance', $trx->amount) : $trx->account->decrement('balance', $trx->amount);
        // Auto-create journal entry
        app(\App\Services\JournalService::class)->fromMainCash($trx->fresh(), $req->user()->id);
        broadcast(new \App\Events\AccountingUpdated('kas-besar', 'approved', $trx->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Transaksi kas besar disetujui']);
    });

    // Dashboard Accounting KPI
    Route::get('/dashboard/accounting', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting') && !$req->user()->isSuperuser())
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        return response()->json(['success' => true, 'data' => [
            'total_hutang_supplier' => SupplierInvoice::whereIn('status', ['unpaid', 'partial'])->sum('remaining_amount'),
            'invoice_jatuh_tempo'   => SupplierInvoice::whereIn('status', ['unpaid', 'partial'])->where('due_date', '<', now())->count(),
            'pembayaran_pending'    => SupplierPayment::where('status', 'pending')->count(),
            'total_kas_kecil'       => PettyCashAccount::where('is_active', true)->sum('balance'),
            'total_kas_besar'       => MainCashAccount::where('is_active', true)->sum('balance'),
            'kas_keluar_bulan_ini'  => MainCashTransaction::where('status', 'approved')->where('type', 'out')->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year)->sum('amount'),
            'total_gaji_bulan_ini'  => ($p = PayrollPeriod::whereIn('status', ['processing', 'approved'])->latest()->first()) ? PayrollItem::where('payroll_period_id', $p->id)->sum('net_salary') : 0,
            'periode_berjalan'      => PayrollPeriod::whereIn('status', ['processing', 'approved'])->latest()->value('name'),
        ]]);
    });

    // ════════════════════════════════════════════════════════════════════════
    // PAYROLL
    // ════════════════════════════════════════════════════════════════════════

    Route::get('/payroll/periods', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $data = PayrollPeriod::withCount('items')->orderBy('year', 'desc')->orderBy('month', 'desc')->paginate($req->per_page ?? 12);
        return response()->json(['success' => true, 'data' => $data->items(),
            'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });
    Route::post('/payroll/periods', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate(['month' => 'required|integer|between:1,12', 'year' => 'required|integer|min:2020', 'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start', 'notes' => 'nullable|string']);
        $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $v['name'] = $bulan[$v['month']] . ' ' . $v['year'];
        $v['created_by'] = $req->user()->id;
        $period = PayrollPeriod::create($v);
        broadcast(new \App\Events\PayrollUpdated('payroll', 'created', $period->id))->toOthers();
        return response()->json(['success' => true, 'data' => $period, 'message' => 'Periode penggajian dibuat'], 201);
    });
    Route::get('/payroll/periods/{period}', function (PayrollPeriod $period) {
        return response()->json(['success' => true, 'data' => $period->load(['items.employee'])]);
    });
    Route::post('/payroll/periods/{period}/generate', function (Request $req, PayrollPeriod $period) {
        if (!$req->user()->hasPermissionTo('manage-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($period->status !== 'draft')
            return response()->json(['success' => false, 'message' => 'Periode tidak dalam status draft'], 422);
        $employees = Employee::with('salaryComponent')->where('is_active', true)->get();
        $created = 0;
        foreach ($employees as $emp) {
            $comp = $emp->salaryComponent;
            if (!$comp || $comp->basic_salary <= 0) continue;
            if (PayrollItem::where('payroll_period_id', $period->id)->where('employee_id', $emp->id)->exists()) continue;
            $loanDeduction = EmployeeLoan::where('employee_id', $emp->id)->where('status', 'active')->sum('monthly_deduction');
            $gross      = $comp->basic_salary + $comp->allowance_transport + $comp->allowance_meal + $comp->allowance_position + $comp->allowance_other;
            $deductions = $comp->deduction_bpjs_tk + $comp->deduction_bpjs_kes + $loanDeduction;
            PayrollItem::create(['payroll_period_id' => $period->id, 'employee_id' => $emp->id, 'basic_salary' => $comp->basic_salary, 'allowance_transport' => $comp->allowance_transport, 'allowance_meal' => $comp->allowance_meal, 'allowance_position' => $comp->allowance_position, 'allowance_other' => $comp->allowance_other, 'deduction_bpjs_tk' => $comp->deduction_bpjs_tk, 'deduction_bpjs_kes' => $comp->deduction_bpjs_kes, 'deduction_loan' => $loanDeduction, 'gross_salary' => $gross, 'total_deduction' => $deductions, 'net_salary' => $gross - $deductions]);
            $created++;
        }
        $period->update(['status' => 'processing']);
        broadcast(new \App\Events\PayrollUpdated('payroll', 'updated', $period->id))->toOthers();
        return response()->json(['success' => true, 'message' => "{$created} data gaji berhasil digenerate"]);
    });
    Route::put('/payroll/periods/{period}/items/{item}', function (Request $req, PayrollPeriod $period, PayrollItem $item) {
        if (!$req->user()->hasPermissionTo('manage-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $item->fill($req->validate(['bonus' => 'nullable|numeric|min:0', 'thr' => 'nullable|numeric|min:0', 'overtime' => 'nullable|numeric|min:0', 'deduction_fine' => 'nullable|numeric|min:0', 'deduction_other' => 'nullable|numeric|min:0', 'notes' => 'nullable|string']));
        $item->gross_salary    = $item->basic_salary + $item->allowance_transport + $item->allowance_meal + $item->allowance_position + $item->allowance_other + ($item->bonus ?? 0) + ($item->thr ?? 0) + ($item->overtime ?? 0);
        $item->total_deduction = $item->deduction_bpjs_tk + $item->deduction_bpjs_kes + $item->deduction_loan + ($item->deduction_pph21 ?? 0) + ($item->deduction_fine ?? 0) + ($item->deduction_other ?? 0);
        $item->net_salary      = $item->gross_salary - $item->total_deduction;
        $item->save();
        broadcast(new \App\Events\PayrollUpdated('payroll', 'updated', $period->id))->toOthers();
        return response()->json(['success' => true, 'data' => $item->load('employee'), 'message' => 'Data gaji diperbarui']);
    });
    Route::post('/payroll/periods/{period}/approve', function (Request $req, PayrollPeriod $period) {
        if (!$req->user()->hasPermissionTo('approve-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($period->status !== 'processing')
            return response()->json(['success' => false, 'message' => 'Periode tidak dalam status processing'], 422);
        $period->update(['status' => 'approved', 'approved_by' => $req->user()->id, 'approved_at' => now()]);
        $period->items()->update(['status' => 'approved']);
        broadcast(new \App\Events\PayrollUpdated('payroll', 'approved', $period->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Penggajian disetujui']);
    });
    Route::post('/payroll/periods/{period}/pay', function (Request $req, PayrollPeriod $period) {
        if (!$req->user()->hasPermissionTo('approve-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($period->status !== 'approved')
            return response()->json(['success' => false, 'message' => 'Periode belum disetujui'], 422);
        $period->update(['status' => 'paid', 'payment_date' => now()]);
        $period->items()->update(['status' => 'paid']);
        foreach ($period->items as $item) {
            if ($item->deduction_loan > 0) {
                foreach (EmployeeLoan::where('employee_id', $item->employee_id)->where('status', 'active')->get() as $loan) {
                    $loan->increment('paid_installments');
                    $loan->decrement('remaining_balance', $loan->monthly_deduction);
                    if ($loan->paid_installments >= $loan->total_installments || $loan->remaining_balance <= 0)
                        $loan->update(['status' => 'completed', 'end_date' => now()]);
                }
            }
        }
        // Auto-create journal entry untuk payroll
        app(\App\Services\JournalService::class)->fromPayroll($period->fresh(), $req->user()->id);
        broadcast(new \App\Events\PayrollUpdated('payroll', 'paid', $period->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Penggajian ditandai sudah dibayar']);
    });

    // Pinjaman Karyawan
    Route::get('/payroll/loans', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = EmployeeLoan::with('employee')->orderBy('created_at', 'desc');
        if ($req->employee_id) $q->where('employee_id', $req->employee_id);
        if ($req->status)      $q->where('status', $req->status);
        $data = $q->paginate($req->per_page ?? 20);
        return response()->json(['success' => true, 'data' => $data->items(),
            'meta' => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()]]);
    });
    Route::post('/payroll/loans', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate(['employee_id' => 'required|exists:employees,id', 'loan_amount' => 'required|numeric|min:1', 'monthly_deduction' => 'required|numeric|min:1', 'total_installments' => 'required|integer|min:1', 'start_date' => 'required|date', 'notes' => 'nullable|string']);
        $v['remaining_balance'] = $v['loan_amount'];
        $v['loan_number']       = 'LOAN-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $v['created_by']        = $req->user()->id;
        $loan = EmployeeLoan::create($v);
        broadcast(new \App\Events\PayrollUpdated('pinjaman', 'created', $loan->id))->toOthers();
        return response()->json(['success' => true, 'data' => $loan->load('employee'), 'message' => 'Pinjaman berhasil dicatat'], 201);
    });
    Route::post('/payroll/loans/{loan}/approve', function (Request $req, EmployeeLoan $loan) {
        if (!$req->user()->hasPermissionTo('approve-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $loan->update(['approved_by' => $req->user()->id, 'approved_at' => now()]);
        broadcast(new \App\Events\PayrollUpdated('pinjaman', 'approved', $loan->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Pinjaman disetujui']);
    });

    // Komponen Gaji
    Route::get('/payroll/salary-components/{employee}', function (Employee $employee) {
        return response()->json(['success' => true, 'data' => EmployeeSalaryComponent::firstOrNew(['employee_id' => $employee->id])]);
    });
    Route::put('/payroll/salary-components/{employee}', function (Request $req, Employee $employee) {
        if (!$req->user()->hasPermissionTo('manage-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate(['basic_salary' => 'required|numeric|min:0', 'allowance_transport' => 'nullable|numeric|min:0', 'allowance_meal' => 'nullable|numeric|min:0', 'allowance_position' => 'nullable|numeric|min:0', 'allowance_other' => 'nullable|numeric|min:0', 'deduction_bpjs_tk' => 'nullable|numeric|min:0', 'deduction_bpjs_kes' => 'nullable|numeric|min:0']);
        $comp = EmployeeSalaryComponent::updateOrCreate(['employee_id' => $employee->id], $v);
        broadcast(new \App\Events\PayrollUpdated('komponen', 'updated', $employee->id))->toOthers();
        return response()->json(['success' => true, 'data' => $comp, 'message' => 'Komponen gaji diperbarui']);
    });

    // ════════════════════════════════════════════════════════════════════════
    // JOURNAL ENTRIES (General Ledger)
    // ════════════════════════════════════════════════════════════════════════

    // GET /journal-entries — daftar jurnal dengan filter
    Route::get('/journal-entries', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = \App\Models\JournalEntry::with(['items', 'creator'])->orderBy('entry_date', 'desc')->orderBy('id', 'desc');
        if ($req->status)       $q->where('status', $req->status);
        if ($req->date_from)    $q->whereDate('entry_date', '>=', $req->date_from);
        if ($req->date_to)      $q->whereDate('entry_date', '<=', $req->date_to);
        if ($req->reference_type) $q->where('reference_type', $req->reference_type);
        if ($req->search)       $q->where(fn($x) => $x->where('journal_number', 'ilike', "%{$req->search}%")->orWhere('description', 'ilike', "%{$req->search}%"));
        $data = $q->paginate($req->per_page ?? 20);
        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    });

    // GET /journal-entries/{id} — detail satu jurnal beserta item-nya
    Route::get('/journal-entries/{entry}', function (\App\Models\JournalEntry $entry) {
        return response()->json(['success' => true, 'data' => $entry->load('items', 'creator', 'poster')]);
    });

    // POST /journal-entries — buat jurnal manual
    Route::post('/journal-entries', function (Request $req) {
        if (!$req->user()->hasPermissionTo('manage-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $v = $req->validate([
            'entry_date'   => 'required|date',
            'description'  => 'required|string|max:255',
            'notes'        => 'nullable|string',
            'items'        => 'required|array|min:2',
            'items.*.account_code'  => 'required|string|max:20',
            'items.*.account_name'  => 'required|string|max:100',
            'items.*.account_type'  => 'required|in:asset,liability,equity,revenue,expense',
            'items.*.debit'         => 'required|numeric|min:0',
            'items.*.credit'        => 'required|numeric|min:0',
            'items.*.description'   => 'nullable|string',
        ]);
        $totalDebit  = collect($v['items'])->sum('debit');
        $totalCredit = collect($v['items'])->sum('credit');
        if (abs($totalDebit - $totalCredit) > 0.01)
            return response()->json(['success' => false, 'message' => 'Total debit harus sama dengan total credit'], 422);
        $entry = \App\Models\JournalEntry::create([
            'journal_number' => \App\Models\JournalEntry::generateNumber(),
            'entry_date'     => $v['entry_date'],
            'description'    => $v['description'],
            'total_debit'    => $totalDebit,
            'total_credit'   => $totalCredit,
            'status'         => 'draft',
            'created_by'     => $req->user()->id,
            'notes'          => $v['notes'] ?? null,
        ]);
        foreach ($v['items'] as $item) {
            \App\Models\JournalItem::create(array_merge($item, ['journal_entry_id' => $entry->id]));
        }
        broadcast(new \App\Events\AccountingUpdated('jurnal', 'created', $entry->id))->toOthers();
        return response()->json(['success' => true, 'data' => $entry->load('items'), 'message' => 'Jurnal berhasil dibuat'], 201);
    });

    // POST /journal-entries/{id}/post — ubah status draft -> posted
    Route::post('/journal-entries/{entry}/post', function (Request $req, \App\Models\JournalEntry $entry) {
        if (!$req->user()->hasPermissionTo('approve-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        if ($entry->status === 'posted')
            return response()->json(['success' => false, 'message' => 'Jurnal sudah diposting'], 422);
        $entry->update(['status' => 'posted', 'posted_by' => $req->user()->id, 'posted_at' => now()]);
        broadcast(new \App\Events\AccountingUpdated('jurnal', 'approved', $entry->id))->toOthers();
        return response()->json(['success' => true, 'message' => 'Jurnal berhasil diposting ke General Ledger']);
    });

    // GET /general-ledger — rekap saldo per akun (pivot dari journal_items)
    Route::get('/general-ledger', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $q = \App\Models\JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->select(
                'journal_items.account_code',
                'journal_items.account_name',
                'journal_items.account_type',
                \Illuminate\Support\Facades\DB::raw('SUM(journal_items.debit)  as total_debit'),
                \Illuminate\Support\Facades\DB::raw('SUM(journal_items.credit) as total_credit'),
                \Illuminate\Support\Facades\DB::raw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
            )
            ->groupBy('journal_items.account_code', 'journal_items.account_name', 'journal_items.account_type')
            ->orderBy('journal_items.account_code');
        if ($req->date_from) $q->whereDate('journal_entries.entry_date', '>=', $req->date_from);
        if ($req->date_to)   $q->whereDate('journal_entries.entry_date', '<=', $req->date_to);
        if ($req->account_type) $q->where('journal_items.account_type', $req->account_type);
        return response()->json(['success' => true, 'data' => $q->get()]);
    });

    // ════════════════════════════════════════════════════════════════════════
    // FINANCIAL REPORTS
    // ════════════════════════════════════════════════════════════════════════

    // GET /reports/cash-flow — arus kas per periode
    Route::get('/reports/cash-flow', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $dateFrom = $req->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $req->date_to   ?? now()->endOfMonth()->toDateString();

        $mainIn  = \App\Models\MainCashTransaction::where('status','approved')->where('type','in')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])->sum('amount');
        $mainOut = \App\Models\MainCashTransaction::where('status','approved')->where('type','out')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])->sum('amount');
        $pettyIn  = \App\Models\PettyCashTransaction::where('status','approved')->where('type','in')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])->sum('amount');
        $pettyOut = \App\Models\PettyCashTransaction::where('status','approved')->where('type','out')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])->sum('amount');
        $supplierPaid = \App\Models\SupplierPayment::where('status','approved')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])->sum('amount');
        $payrollCost = \App\Models\PayrollItem::whereHas('period', fn($q) =>
            $q->where('status','paid')->whereBetween('payment_date', [$dateFrom, $dateTo])
        )->sum('net_salary');

        $transactions = \App\Models\MainCashTransaction::with('account','creator')
            ->where('status','approved')->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderBy('transaction_date','desc')->get();

        return response()->json(['success' => true, 'data' => [
            'period'          => ['from' => $dateFrom, 'to' => $dateTo],
            'main_cash_in'    => $mainIn,
            'main_cash_out'   => $mainOut,
            'petty_cash_in'   => $pettyIn,
            'petty_cash_out'  => $pettyOut,
            'supplier_paid'   => $supplierPaid,
            'payroll_cost'    => $payrollCost,
            'net_cash_flow'   => ($mainIn + $pettyIn) - ($mainOut + $pettyOut),
            'transactions'    => $transactions,
        ]]);
    });

    // GET /reports/supplier-payable — hutang supplier per supplier
    Route::get('/reports/supplier-payable', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $invoices = \App\Models\SupplierInvoice::with('supplier','purchaseOrder')
            ->whereIn('status', ['unpaid','partial'])
            ->orderBy('due_date','asc')
            ->get()
            ->map(fn($inv) => [
                'id'               => $inv->id,
                'invoice_number'   => $inv->invoice_number,
                'po_number'        => $inv->purchaseOrder?->po_number,
                'supplier'         => $inv->supplier?->name,
                'invoice_date'     => $inv->invoice_date,
                'due_date'         => $inv->due_date,
                'total_amount'     => $inv->total_amount,
                'paid_amount'      => $inv->paid_amount,
                'remaining_amount' => $inv->remaining_amount,
                'is_overdue'       => $inv->isOverdue(),
                'days_overdue'     => $inv->isOverdue() ? now()->diffInDays($inv->due_date) : 0,
                'status'           => $inv->status,
            ]);
        $totalPayable = $invoices->sum('remaining_amount');
        $totalOverdue = $invoices->where('is_overdue', true)->sum('remaining_amount');
        return response()->json(['success' => true, 'data' => [
            'summary' => ['total_payable' => $totalPayable, 'total_overdue' => $totalOverdue, 'invoice_count' => $invoices->count()],
            'invoices' => $invoices,
        ]]);
    });

    // GET /reports/payroll-summary — ringkasan biaya payroll per periode
    Route::get('/reports/payroll-summary', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-payroll'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $year = $req->year ?? now()->year;
        $periods = \App\Models\PayrollPeriod::with('items.employee')
            ->where('year', $year)->orderBy('month')->get()
            ->map(fn($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'month'         => $p->month,
                'year'          => $p->year,
                'status'        => $p->status,
                'payment_date'  => $p->payment_date,
                'employee_count'=> $p->items->count(),
                'total_gross'   => $p->items->sum('gross_salary'),
                'total_deduction'=> $p->items->sum('total_deduction'),
                'total_net'     => $p->items->sum('net_salary'),
                'total_bpjs'    => $p->items->sum(fn($i) => $i->deduction_bpjs_tk + $i->deduction_bpjs_kes),
                'total_loan'    => $p->items->sum('deduction_loan'),
            ]);
        return response()->json(['success' => true, 'data' => [
            'year'    => $year,
            'periods' => $periods,
            'annual'  => [
                'total_gross' => $periods->sum('total_gross'),
                'total_net'   => $periods->sum('total_net'),
            ],
        ]]);
    });

    // GET /reports/expense — laporan beban pengeluaran dari kas
    Route::get('/reports/expense', function (Request $req) {
        if (!$req->user()->hasPermissionTo('view-accounting'))
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        $dateFrom = $req->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $req->date_to   ?? now()->endOfMonth()->toDateString();
        $mainExpenses  = \App\Models\MainCashTransaction::where('status','approved')->where('type','out')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->with('account','creator')->orderBy('transaction_date','desc')->get();
        $pettyExpenses = \App\Models\PettyCashTransaction::where('status','approved')->where('type','out')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->with('account','creator')->orderBy('transaction_date','desc')->get();
        return response()->json(['success' => true, 'data' => [
            'period'         => ['from' => $dateFrom, 'to' => $dateTo],
            'main_expenses'  => $mainExpenses,
            'petty_expenses' => $pettyExpenses,
            'total_main'     => $mainExpenses->sum('amount'),
            'total_petty'    => $pettyExpenses->sum('amount'),
            'grand_total'    => $mainExpenses->sum('amount') + $pettyExpenses->sum('amount'),
        ]]);
    });

    // GET /purchase-orders/{po}/for-invoice — ambil data PO untuk ditarik ke invoice
    Route::get('/purchase-orders/{purchaseOrder}/for-invoice', function (\App\Models\PurchaseOrder $purchaseOrder) {
        $invoicedAmount = SupplierInvoice::where('purchase_order_id', $purchaseOrder->id)
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');
        $remainingInvoiceable = max(0, (float) $purchaseOrder->grand_total - $invoicedAmount);
        $fullyInvoiced = $invoicedAmount >= (float) $purchaseOrder->grand_total && (float) $purchaseOrder->grand_total > 0;

        return response()->json(['success' => true, 'data' => [
            'id'                   => $purchaseOrder->id,
            'po_number'            => $purchaseOrder->po_number,
            'vendor_name'          => $purchaseOrder->vendor_name,
            'grand_total'          => $purchaseOrder->grand_total,
            'ppn_amount'           => $purchaseOrder->ppn_amount,
            'subtotal'             => $purchaseOrder->total_amount,
            'status'               => $purchaseOrder->status,
            'invoiced_amount'      => (float) $invoicedAmount,
            'remaining_invoiceable'=> $remainingInvoiceable,
            'fully_invoiced'       => $fullyInvoiced,
        ]]);
    });

    // GET /purchase-orders-by-supplier/{supplier} — list PO untuk dropdown di invoice
    Route::get('/purchase-orders-by-supplier/{supplier}', function (\App\Models\Supplier $supplier) {
        // Strategi matching bertingkat:
        // 1. Prioritas utama: vendor_name MENGANDUNG nama supplier lengkap (atau sebaliknya)
        // 2. Hanya gunakan kata kunci jika kata tersebut UNIK dan cukup panjang (>= 5 karakter)
        //    untuk menghindari false positive seperti "Makmur" mencocokkan "Gaya Makmur Indon" DAN "Makmur"

        $supplierName = $supplier->name;

        // Ambil kata-kata signifikan: panjang >= 5 karakter, bukan kata umum
        $commonWords = ['group', 'jaya', 'putra', 'prima', 'karya', 'utama', 'abadi', 'maju', 'indo', 'nusa'];
        $significantWords = collect(explode(' ', strtolower($supplierName)))
            ->filter(fn($w) => strlen($w) >= 5 && !in_array($w, $commonWords))
            ->values();

        $pos = \App\Models\PurchaseOrder::whereNotIn('status', ['cancelled'])
            ->where(function($q) use ($supplierName, $significantWords) {
                // Match jika vendor_name mengandung nama supplier lengkap
                $q->where('vendor_name', 'ilike', "%{$supplierName}%");
                // Atau nama supplier mengandung vendor_name (vendor diketik lebih pendek)
                // Ini ditangani di PHP setelah query — ambil semua dulu lalu filter
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'po_number', 'grand_total', 'total_amount', 'ppn_amount', 'expected_date', 'status', 'vendor_name']);

        // Filter tambahan di PHP: vendor_name harus mengandung nama supplier ATAU
        // nama supplier mengandung vendor_name (bukan hanya kata tunggal yang kebetulan sama)
        $filtered = $pos->filter(function($po) use ($supplierName) {
            $vendor = strtolower($po->vendor_name ?? '');
            $supplier = strtolower($supplierName);
            return str_contains($vendor, $supplier)
                || (strlen($vendor) >= 5 && str_contains($supplier, $vendor));
        })->values();

        // Tambahkan info invoiced_amount dan fully_invoiced per PO
        $filtered = $filtered->map(function($po) {
            $invoicedAmount = SupplierInvoice::where('purchase_order_id', $po->id)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');
            $po->invoiced_amount       = (float) $invoicedAmount;
            $po->remaining_invoiceable = max(0, (float) $po->grand_total - $invoicedAmount);
            $po->fully_invoiced        = $invoicedAmount >= (float) $po->grand_total && (float) $po->grand_total > 0;
            return $po;
        });

        return response()->json(['success' => true, 'data' => $filtered]);
    });

    // Import Saldo Awal Stok
    Route::post('/import-saldo-awal/preview', [\App\Http\Controllers\Api\ImportSaldoAwalController::class, 'preview']);
    Route::post('/import-saldo-awal/import', [\App\Http\Controllers\Api\ImportSaldoAwalController::class, 'import']);

    // Stok Opname / Penyesuaian Stok
    Route::get('/stok-opname', [\App\Http\Controllers\Api\StokOpnameController::class, 'index']);
    Route::post('/stok-opname', [\App\Http\Controllers\Api\StokOpnameController::class, 'store']);
    Route::get('/stok-opname/{stokOpname}', [\App\Http\Controllers\Api\StokOpnameController::class, 'show']);
    Route::put('/stok-opname/{stokOpname}', [\App\Http\Controllers\Api\StokOpnameController::class, 'update']);
    Route::delete('/stok-opname/{stokOpname}', [\App\Http\Controllers\Api\StokOpnameController::class, 'destroy']);
    Route::post('/stok-opname/{stokOpname}/ajukan', [\App\Http\Controllers\Api\StokOpnameController::class, 'ajukan']);
    Route::post('/stok-opname/{stokOpname}/setujui', [\App\Http\Controllers\Api\StokOpnameController::class, 'setujui']);
    Route::post('/stok-opname/{stokOpname}/tolak', [\App\Http\Controllers\Api\StokOpnameController::class, 'tolak']);

    // Utility: Recalculate avg_price (jalankan sekali untuk fix data lama)
    Route::post('/utility/recalculate-avg-price', function (\Illuminate\Http\Request $request) {
        $dryRun = $request->boolean('dry_run', false);
        $results = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($dryRun, &$results) {
            $groups = \App\Models\ItemPriceHistory::select('item_id', 'warehouse_id')
                ->groupBy('item_id', 'warehouse_id')
                ->get();

            $itemAvgMap = [];

            foreach ($groups as $g) {
                $prices    = \App\Models\ItemPriceHistory::where('item_id', $g->item_id)
                    ->where('warehouse_id', $g->warehouse_id)
                    ->pluck('purchase_price')
                    ->map(fn($p) => (float) $p);

                $simpleAvg = $prices->avg();

                $stock = \App\Models\ItemStock::where('item_id', $g->item_id)
                    ->where('warehouse_id', $g->warehouse_id)
                    ->first();

                if ($stock) {
                    $results[] = [
                        'item_id'      => $g->item_id,
                        'warehouse_id' => $g->warehouse_id,
                        'avg_lama'     => (float) $stock->avg_price,
                        'avg_baru'     => $simpleAvg,
                        'n_transaksi'  => $prices->count(),
                    ];
                    if (!$dryRun) {
                        $stock->update(['avg_price' => $simpleAvg]);
                    }
                }

                $itemAvgMap[$g->item_id][] = $simpleAvg;
            }

            foreach ($itemAvgMap as $itemId => $avgList) {
                $itemAvg = array_sum($avgList) / count($avgList);
                $item    = \App\Models\Item::find($itemId);
                if ($item) {
                    foreach ($results as &$r) {
                        if ($r['item_id'] === $itemId) {
                            $r['item_name']  = $item->name;
                            $r['price_lama'] = (float) $item->price;
                            $r['price_baru'] = $itemAvg;
                        }
                    }
                    unset($r);
                    if (!$dryRun) {
                        $item->update(['price' => $itemAvg]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'dry_run' => $dryRun,
            'updated' => count($results),
            'results' => $results,
            'message' => $dryRun
                ? 'Dry run selesai. Kirim dry_run=false untuk apply.'
                : 'Recalculate selesai. Semua avg_price & price sudah diperbarui.',
        ]);
    });
});