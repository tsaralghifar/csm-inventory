<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Pembelian — CSM Inventory</title>
<style>
/* DomPDF-safe: no flexbox, no grid, no rgba border, no calc */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:9px; color:#1e293b; background:#fff; }

.accent { height:5px; background:#1a3a5c; }

/* ── HEADER ── */
.hdr        { background:#0f2744; padding:12px 20px 10px; }
.hdr-tbl    { width:100%; border-collapse:collapse; }
.hdr-tbl td { vertical-align:top; padding:0; }
.logo       { width:32px; height:32px; background:#16a34a; border-radius:5px;
              text-align:center; font-size:16px; font-weight:900; color:#fff;
              line-height:32px; display:inline-block; }
.co-name    { font-size:12px; font-weight:bold; color:#f8fafc; }
.co-sub     { font-size:7px; color:#94a3b8; display:block; margin-top:1px; }
.rpt-title  { font-size:17px; font-weight:bold; color:#f8fafc; margin-top:7px; }
.rpt-sub    { font-size:7.5px; color:#94a3b8; margin-top:2px; }
.info-box   { border:1px solid #2a4a7a; border-radius:5px; padding:7px 11px;
              text-align:right; font-size:7.5px; color:#cbd5e1; line-height:1.8; }
.info-box strong { color:#f8fafc; }

/* ── STRIP ── */
.strip      { background:#1a3455; border-top:2px solid #1a3a5c; padding:7px 20px; }
.strip-tbl  { width:100%; border-collapse:collapse; }
.strip-tbl td { padding:0 14px 0 0; border-right:1px solid #263f63; width:20%; vertical-align:top; }
.strip-tbl td:last-child { border-right:none; padding-right:0; }
.sl { font-size:6.5px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
.sv { font-size:9px; font-weight:bold; color:#f8fafc; }

/* ── KPI ── */
.kpi-wrap { background:#f1f5f9; border-bottom:1px solid #e2e8f0; padding:10px 20px; }
.kpi-tbl  { width:100%; border-collapse:collapse; }
.kpi-tbl td { padding:0 5px; }
.kpi-tbl td:first-child { padding-left:0; }
.kpi-tbl td:last-child  { padding-right:0; }
.kpi-card { border-radius:6px; overflow:hidden; border:1px solid #e2e8f0; }
.kpi-top  { height:4px; }
.kpi-body { padding:8px 10px; text-align:center; }
.kpi-lbl  { font-size:6.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.kpi-val  { font-size:19px; font-weight:bold; line-height:1.1; }
.kpi-val-sm { font-size:9.5px; font-weight:bold; line-height:1.4; }
.kpi-sub  { font-size:7px; margin-top:2px; }

.k-navy  .kpi-top  { background:#1a3a5c; }
.k-navy  .kpi-body { background:#eff6ff; }
.k-navy  .kpi-lbl  { color:#1e40af; }
.k-navy  .kpi-val  { color:#1a3a5c; }

.k-green .kpi-top  { background:#16a34a; }
.k-green .kpi-body { background:#f0fdf4; }
.k-green .kpi-lbl  { color:#065f46; }
.k-green .kpi-val-sm { color:#047857; }

.k-blue  .kpi-top  { background:#0284c7; }
.k-blue  .kpi-body { background:#f0f9ff; }
.k-blue  .kpi-lbl  { color:#0c4a6e; }
.k-blue  .kpi-val  { color:#0369a1; }
.k-blue  .kpi-sub  { color:#0369a1; }

.k-amber .kpi-top  { background:#d97706; }
.k-amber .kpi-body { background:#fffbeb; }
.k-amber .kpi-lbl  { color:#78350f; }
.k-amber .kpi-val  { color:#b45309; }
.k-amber .kpi-sub  { color:#b45309; }

/* ── SEC HEADER ── */
.sec { background:#f8fafc; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; padding:7px 20px; }
.sec-tbl { width:100%; border-collapse:collapse; }
.sec-tbl td { vertical-align:middle; padding:0; }
.sec-title { font-size:9px; font-weight:bold; color:#1a3a5c; }
.sec-sub   { font-size:7.5px; color:#64748b; text-align:right; }

/* ── TABLE ── */
.tbl-wrap { padding:0 16px 16px; margin-top:8px; }
table.dt  { width:100%; border-collapse:collapse; font-size:7.5px; table-layout:fixed; }

table.dt thead th {
  background:#0f2744; color:#fff; font-size:7px; font-weight:bold;
  padding:5px 4px; text-align:center;
  border-right:1px solid #1e4080;
  border-bottom:2px solid #16a34a;
  white-space:nowrap; overflow:hidden;
}
table.dt thead th:last-child { border-right:none; }
table.dt thead th.tl { text-align:left; }
table.dt thead th.tr { text-align:right; }

/* PO main rows */
table.dt tr.r-po td {
  padding:4px 4px; border-right:1px solid #e8edf5;
  border-bottom:1px solid #e8edf5; vertical-align:middle; overflow:hidden;
}
table.dt tr.r-even td { background:#f8fafc; }
table.dt tr.r-odd  td { background:#ffffff; }
table.dt tr.r-overdue td { background:#fff5f5; }
table.dt tr.r-po-last td { border-bottom:2px solid #c5d8ef !important; }

/* Item sub-rows */
table.dt tr.r-item td {
  padding:3px 4px; border-right:1px solid #dde5f0;
  border-bottom:1px solid #e8edf8;
  background:#f5f8ff; font-size:7px; vertical-align:middle; overflow:hidden;
}

/* Invoice sub-rows */
table.dt tr.r-inv td {
  padding:3px 4px; border-right:1px solid #e8edf5;
  border-bottom:1px dashed #fde68a;
  background:#fffbeb; font-size:7px; vertical-align:middle; overflow:hidden;
}

/* Summary per-type rows */
table.dt tr.r-sub td {
  padding:4px 4px; border:1px solid #c5d8f0;
  background:#e8f0fb; font-size:7.5px; vertical-align:middle;
}

/* Grand total */
table.dt tr.r-total td {
  background:#0f2744; color:#fff; font-weight:bold;
  font-size:8.5px; padding:6px 4px;
  border-right:1px solid #1e4080;
}
table.dt tr.r-total td:last-child { border-right:none; }
table.dt tr.r-total td.rv { color:#4ade80; font-size:9.5px; text-align:right; }

/* BADGES */
.bdg { display:inline-block; padding:2px 5px; border-radius:3px;
       font-size:7px; font-weight:bold; text-align:center; white-space:nowrap; }
.b-cash   { background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; }
.b-kredit { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
.b-done   { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.b-sent   { background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
.b-part   { background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; }
.b-draft  { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; }
.b-cancel { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; }
.b-lunas  { background:#dcfce7; color:#15803d; border:1px solid #86efac; }
.b-unpaid { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; }
.b-pars   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
.b-src    { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; }

/* COLOURS */
.cbl  { color:#1d4ed8; font-weight:bold; }
.cgr  { color:#15803d; font-weight:bold; }
.crd  { color:#dc2626; font-weight:bold; }
.cam  { color:#d97706; font-weight:bold; }
.cmu  { color:#64748b; }
.mono { font-family:'DejaVu Sans Mono',monospace; }

/* FOOTER */
.ftr { margin:0 20px; padding:10px 0; border-top:1px solid #e2e8f0; }
.ftr-tbl td { vertical-align:bottom; padding:0; }
.ftr-text { font-size:7.5px; color:#94a3b8; line-height:1.7; }
.ftr-text strong { color:#475569; }
.sign-box { display:inline-block; border:1px solid #e2e8f0; border-radius:4px;
            padding:5px 10px; text-align:center; width:95px; margin-left:10px; }
.sign-lbl  { font-size:6.5px; color:#94a3b8; margin-bottom:1px; }
.sign-line { margin-top:26px; border-top:1px solid #94a3b8; padding-top:3px;
             font-size:6.5px; color:#475569; }
</style>
</head>
<body>

@php
  use Carbon\Carbon;

  $now       = now();
  $dateStr   = $now->locale('id')->isoFormat('D MMMM YYYY');
  $timeStr   = $now->format('H:i');

  $dateFrom    = !empty($request['date_from'])    ? Carbon::parse($request['date_from'])->locale('id')->isoFormat('D MMM Y') : '—';
  $dateTo      = !empty($request['date_to'])      ? Carbon::parse($request['date_to'])->locale('id')->isoFormat('D MMM Y')   : '—';
  $supplierNm  = $request['supplier_name']        ?? 'Semua Supplier';
  $payLabel    = match($request['payment_type'] ?? '') {
    'cash'   => 'Cash', 'kredit' => 'Kredit', default => 'Cash & Kredit',
  };
  $statusLabel = match($request['status'] ?? '') {
    'draft'            => 'Draft',
    'sent_to_vendor'   => 'Dikirim ke Vendor',
    'partial_received' => 'Diterima Sebagian',
    'completed'        => 'Selesai',
    'cancelled'        => 'Dibatalkan',
    default            => 'Semua Status',
  };

  $rp  = fn($v) => 'Rp ' . number_format(max(0,(float)$v), 0, ',', '.');
  $num = fn($v) => number_format((float)$v, 0, ',', '.');

  $sLbl = fn($s) => match($s) {
    'draft'            => 'Draft',
    'sent_to_vendor'   => 'Dikirim',
    'partial_received' => 'Sebagian',
    'completed'        => 'Selesai',
    'cancelled'        => 'Batal',
    default            => $s,
  };
  $sBdg = fn($s) => match($s) {
    'completed' => 'b-done', 'sent_to_vendor' => 'b-sent',
    'partial_received' => 'b-part', 'draft' => 'b-draft',
    'cancelled' => 'b-cancel', default => 'b-draft',
  };

  $today = now()->toDateString();
@endphp

<div class="accent"></div>

{{-- HEADER --}}
<div class="hdr">
  <table class="hdr-tbl">
    <tr>
      <td style="width:58%;vertical-align:top;">
        <table style="border-collapse:collapse;"><tr>
          <td style="vertical-align:middle;padding-right:8px;">
            <div class="logo">C</div>
          </td>
          <td style="vertical-align:middle;">
            <span class="co-name">CSM Inventory</span>
            <span class="co-sub">PT. Cipta Sarana Makmur</span>
          </td>
        </tr></table>
        <div class="rpt-title" style="margin-top:8px;">Laporan Pembelian Barang</div>
        <div class="rpt-sub" style="margin-bottom:2px;">Rekap Purchase Order — Cash, Kredit, maupun keduanya</div>
      </td>
      <td style="width:42%;vertical-align:top;text-align:right;">
        <div class="info-box">
          <strong>Tanggal Cetak</strong><br>
          {{ $dateStr }}, pukul {{ $timeStr }} WIB<br>
          <strong>Periode</strong><br>
          {{ $dateFrom }} s/d {{ $dateTo }}<br>
          <strong>Jenis Pembayaran</strong> &nbsp; {{ $payLabel }}
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- STRIP --}}
<div class="strip">
  <table class="strip-tbl">
    <tr>
      <td>
        <div class="sl">Periode</div>
        <div class="sv">{{ $dateFrom }} – {{ $dateTo }}</div>
      </td>
      <td>
        <div class="sl">Supplier</div>
        <div class="sv">{{ $supplierNm }}</div>
      </td>
      <td>
        <div class="sl">Jenis Pembayaran</div>
        <div class="sv">{{ $payLabel }}</div>
      </td>
      <td>
        <div class="sl">Status PO</div>
        <div class="sv">{{ $statusLabel }}</div>
      </td>
      <td style="text-align:right;">
        <div class="sl">Total PO</div>
        <div class="sv">{{ $summary['total_po'] }} PO</div>
      </td>
    </tr>
  </table>
</div>

{{-- KPI --}}
<div class="kpi-wrap">
  <table class="kpi-tbl">
    <tr>
      <td>
        <div class="kpi-card k-navy">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">Total PO</div>
            <div class="kpi-val">{{ $summary['total_po'] }}</div>
          </div>
        </div>
      </td>
      <td>
        <div class="kpi-card k-green">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">Total Nilai Pembelian</div>
            <div class="kpi-val-sm">{{ $rp($summary['total_nilai']) }}</div>
            @if($summary['total_ppn'] > 0)
            <div class="kpi-sub" style="color:#047857;">PPN: {{ $rp($summary['total_ppn']) }}</div>
            @endif
          </div>
        </div>
      </td>
      <td>
        <div class="kpi-card k-blue">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">PO Cash</div>
            <div class="kpi-val">{{ $summary['total_cash'] }}</div>
            <div class="kpi-sub">{{ $rp($summary['nilai_cash']) }}</div>
          </div>
        </div>
      </td>
      <td>
        <div class="kpi-card k-amber">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">PO Kredit</div>
            <div class="kpi-val">{{ $summary['total_kredit'] }}</div>
            <div class="kpi-sub">{{ $rp($summary['nilai_kredit']) }}</div>
          </div>
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- SEC HEADER --}}
<div class="sec">
  <table class="sec-tbl">
    <tr>
      <td><span class="sec-title">Daftar Purchase Order</span></td>
      <td>
        <span class="sec-sub">
          {{ $summary['total_po'] }} PO &bull;
          Cash: {{ $summary['total_cash'] }} &bull;
          Kredit: {{ $summary['total_kredit'] }} &bull;
          Detail item &amp; invoice kredit ditampilkan per PO
        </span>
      </td>
    </tr>
  </table>
</div>

{{-- TABLE --}}
<div class="tbl-wrap">
<table class="dt">
  <thead>
    <tr>
      <th style="width:18px;">#</th>
      <th class="tl" style="width:100px;">No. PO</th>
      <th class="tl" style="width:115px;">Vendor / Supplier</th>
      <th style="width:55px;">Gudang</th>
      <th style="width:52px;">Tgl. PO</th>
      <th style="width:45px;">Bayar</th>
      <th style="width:35px;">Tenor</th>
      <th style="width:55px;">Jatuh Tempo</th>
      <th class="tr" style="width:82px;">Subtotal</th>
      <th class="tr" style="width:82px;">Nilai PPN</th>
      <th class="tr" style="width:82px;">Grand Total</th>
      <th style="width:48px;">Status</th>
      <th style="width:50px;">Bayar</th>
    </tr>
  </thead>
  <tbody>

  @php $grandTotal = 0; @endphp

  @foreach($data as $idx => $po)
    @php
      $overdue = $po['payment_type'] === 'kredit'
        && !empty($po['payment_due_date'])
        && $po['payment_due_date'] < $today
        && collect($po['supplier_invoices'] ?? [])->every(fn($i) => $i['status'] !== 'paid');

      $rClass  = $overdue ? 'r-po r-overdue' : ($idx % 2 === 0 ? 'r-po r-even' : 'r-po r-odd');
      $grandTotal += $po['grand_total'];

      // invoice status
      $invs = $po['supplier_invoices'] ?? [];
      if ($po['payment_type'] === 'cash') {
        $bayarLbl = 'Lunas'; $bayarBdg = 'b-lunas';
      } elseif (empty($invs)) {
        $bayarLbl = 'Belum'; $bayarBdg = 'b-unpaid';
      } elseif (collect($invs)->every(fn($i) => $i['status'] === 'paid')) {
        $bayarLbl = 'Lunas'; $bayarBdg = 'b-lunas';
      } elseif (collect($invs)->some(fn($i) => $i['status'] === 'partial')) {
        $bayarLbl = 'Parsial'; $bayarBdg = 'b-pars';
      } elseif ($overdue) {
        $bayarLbl = 'JT'; $bayarBdg = 'b-cancel';
      } else {
        $bayarLbl = 'Belum'; $bayarBdg = 'b-unpaid';
      }
    @endphp

    {{-- ── PO ROW ── --}}
    <tr class="{{ $rClass }}">
      <td style="text-align:center;color:#94a3b8;font-size:7px;">{{ $idx+1 }}</td>
      <td>
        <span class="mono cbl" style="font-size:7px;">{{ $po['po_number'] }}</span>
        <br><span class="cmu" style="font-size:6px;">{{ $po['items_count'] }} item</span>
      </td>
      <td>
        <span style="font-weight:bold;font-size:7.5px;">{{ $po['vendor_name'] }}</span>
        @if(!empty($po['supplier']))
          <br><span class="cmu" style="font-size:6.5px;">{{ $po['supplier']['name'] }}</span>
        @endif
      </td>
      <td style="text-align:center;">
        <span class="cmu" style="font-size:7px;">
          {{ str_replace('Gudang ', '', $po['warehouse']['name'] ?? '—') }}
        </span>
      </td>
      <td style="text-align:center;color:#475569;font-size:7px;">
        {{ $po['created_at'] ? Carbon::parse($po['created_at'])->format('d/m/Y') : '—' }}
      </td>
      <td style="text-align:center;">
        @if($po['payment_type'] === 'cash')
          <span class="bdg b-cash">Cash</span>
        @else
          <span class="bdg b-kredit">Kredit</span>
        @endif
      </td>
      <td style="text-align:center;color:#64748b;font-size:7px;">
        {{ $po['payment_term_days'] ? $po['payment_term_days'].' hr' : '—' }}
      </td>
      <td style="text-align:center;font-size:7px;"
          class="{{ $overdue ? 'crd' : 'cmu' }}">
        {{ $po['payment_due_date'] ? Carbon::parse($po['payment_due_date'])->format('d/m/Y') : '—' }}
        @if($overdue)
          <br><span style="font-size:6px;" class="crd">
            Lewat {{ now()->diffInDays($po['payment_due_date']) }}h
          </span>
        @endif
      </td>
      <td style="text-align:right;color:#64748b;font-size:7.5px;">{{ $rp($po['total_amount']) }}</td>
      <td style="text-align:right;font-size:7px;">
        @if($po['ppn_percent'] > 0)
          <span style="color:#64748b;font-size:6px;">{{ $po['ppn_percent'] }}%</span>
          <br><span style="color:#0369a1;font-weight:bold;">{{ $rp($po['ppn_amount']) }}</span>
        @else
          <span class="cmu">—</span>
        @endif
      </td>
      <td style="text-align:right;" class="cbl">
        <strong style="font-size:8px;">{{ $rp($po['grand_total']) }}</strong>
      </td>
      <td style="text-align:center;">
        <span class="bdg {{ $sBdg($po['status']) }}">{{ $sLbl($po['status']) }}</span>
      </td>
      <td style="text-align:center;">
        <span class="bdg {{ $bayarBdg }}">{{ $bayarLbl }}</span>
      </td>
    </tr>

    {{-- ── ITEM ROWS ── --}}
    @foreach($po['items'] as $li => $item)
    @php $isLastItem = $li === count($po['items']) - 1 && empty($po['supplier_invoices']); @endphp
    <tr class="r-item{{ $isLastItem ? ' r-po-last' : '' }}">
      <td style="border-left:3px solid #1a3a5c;"></td>
      <td colspan="6" style="padding-left:14px;">
        <span style="color:#94a3b8;font-size:7px;margin-right:4px;">{{ $li+1 }}.</span>
        <span style="font-weight:bold;color:#1e293b;font-size:7.5px;">{{ $item['nama_barang'] }}</span>
        @if(!empty($item['part_number']))
          <span class="mono cmu" style="font-size:6.5px;margin-left:4px;">{{ $item['part_number'] }}</span>
        @endif
        @if($item['diskon_persen'] > 0)
          <span class="crd" style="font-size:6.5px;margin-left:4px;">(Diskon {{ $item['diskon_persen'] }}%)</span>
        @endif
        @if(!empty($item['item']['category']))
          <br><span style="display:inline-block;margin-top:2px;background:#e2e8f0;color:#475569;font-size:6px;padding:1px 5px;border-radius:3px;">{{ $item['item']['category'] }}</span>
        @endif
      </td>
      <td style="text-align:right;color:#475569;font-size:7px;">
        {{ $num($item['qty']) }} {{ $item['satuan'] }} &times; {{ $rp($item['harga_satuan']) }}
      </td>
      <td></td>{{-- Nilai PPN kosong untuk item --}}
      <td style="text-align:right;font-weight:bold;color:#1a3a5c;font-size:7.5px;">
        {{ $rp($item['total_harga']) }}
      </td>
      <td colspan="2"></td>
    </tr>
    @endforeach

    {{-- ── INVOICE ROWS (kredit only) ── --}}
    @if($po['payment_type'] === 'kredit' && count($po['supplier_invoices'] ?? []) > 0)
      @foreach($po['supplier_invoices'] as $inv)
      @php
        $invOverdue = !empty($inv['due_date']) && $inv['due_date'] < $today && $inv['status'] !== 'paid';
      @endphp
      <tr class="r-inv">
        <td style="text-align:center;color:#fcd34d;font-size:9px;">&#x21B3;</td>
        <td colspan="2" style="padding-left:12px;">
          <span class="bdg b-pars" style="font-size:6.5px;">Invoice</span>
          &nbsp;
          <span class="mono cam" style="font-size:7.5px;font-weight:bold;">{{ $inv['invoice_number'] }}</span>
          &nbsp;
          <span class="cmu" style="font-size:7px;">
            Tgl: {{ !empty($inv['invoice_date']) ? Carbon::parse($inv['invoice_date'])->format('d/m/Y') : '—' }}
          </span>
        </td>
        <td colspan="4"></td>
        <td style="text-align:center;font-size:7.5px;"
            class="{{ $invOverdue ? 'crd' : 'cmu' }}">
          JT: {{ !empty($inv['due_date']) ? Carbon::parse($inv['due_date'])->format('d/m/Y') : '—' }}
          @if($invOverdue)
            <br><span style="font-size:6.5px;" class="crd">Overdue</span>
          @endif
        </td>
        <td></td>
        <td style="text-align:right;font-size:7.5px;">
          <span class="cgr">Bayar: {{ $rp($inv['paid_amount']) }}</span><br>
          <span class="crd">Sisa: {{ $rp($inv['remaining_amount']) }}</span>
        </td>
        <td></td>
        <td style="text-align:center;">
          @if($inv['status'] === 'paid')
            <span class="bdg b-lunas" style="font-size:6.5px;">Lunas</span>
          @elseif($inv['status'] === 'partial')
            <span class="bdg b-pars" style="font-size:6.5px;">Parsial</span>
          @else
            <span class="bdg b-unpaid" style="font-size:6.5px;">Belum</span>
          @endif
        </td>
      </tr>
      @endforeach
    @endif

  @endforeach

  {{-- ── SUBTOTAL CASH / KREDIT ── --}}
  @if($summary['total_cash'] > 0 && $summary['total_kredit'] > 0)
  <tr class="r-sub">
    <td colspan="10" style="text-align:right;font-size:7.5px;color:#475569;padding-right:5px;">
      Subtotal PO Cash ({{ $summary['total_cash'] }} PO)
    </td>
    <td style="text-align:right;" class="cbl"><strong>{{ $rp($summary['nilai_cash']) }}</strong></td>
    <td colspan="2"></td>
  </tr>
  <tr class="r-sub">
    <td colspan="10" style="text-align:right;font-size:7.5px;color:#475569;padding-right:5px;">
      Subtotal PO Kredit ({{ $summary['total_kredit'] }} PO)
    </td>
    <td style="text-align:right;" class="cam"><strong>{{ $rp($summary['nilai_kredit']) }}</strong></td>
    <td colspan="2"></td>
  </tr>
  @endif

  {{-- ── GRAND TOTAL ── --}}
  <tr class="r-total">
    <td colspan="10" style="text-align:right;padding-right:6px;">
      TOTAL NILAI PEMBELIAN ({{ $summary['total_po'] }} PO)
    </td>
    <td class="rv">{{ $rp($grandTotal) }}</td>
    <td colspan="2"></td>
  </tr>

  </tbody>
</table>
</div>

{{-- FOOTER --}}
<div class="ftr">
  <table style="width:100%;border-collapse:collapse;">
    <tr>
      <td style="width:58%;vertical-align:bottom;">
        <p class="ftr-text">
          Laporan ini digenerate otomatis oleh <strong>CSM Inventory System</strong>
          pada <strong>{{ $dateStr }}</strong> pukul <strong>{{ $timeStr }} WIB</strong>.
        </p>
        <p class="ftr-text" style="margin-top:2px;">
          Periode: <strong>{{ $dateFrom }}</strong> s/d <strong>{{ $dateTo }}</strong> &nbsp;&bull;&nbsp;
          Jenis Pembayaran: <strong>{{ $payLabel }}</strong> &nbsp;&bull;&nbsp;
          Supplier: <strong>{{ $supplierNm }}</strong>
        </p>
      </td>
      <td style="width:42%;text-align:right;vertical-align:bottom;">
        <div class="sign-box">
          <div class="sign-lbl">Dibuat oleh</div>
          <div class="sign-line">Bagian Purchasing</div>
        </div>
        <div class="sign-box">
          <div class="sign-lbl">Disetujui oleh</div>
          <div class="sign-line">Kepala Gudang</div>
        </div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>