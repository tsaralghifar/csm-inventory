<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Stok Persediaan — CSM Inventory</title>
<style>
/* ═══════════════════════════════════════════════
   DOMPDF-SAFE CSS — NO flexbox, NO rgba border,
   NO grid, NO calc, NO shorthand gradients
   All layout via table / inline-block / float
   ═══════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'DejaVu Sans', Arial, sans-serif;
  font-size: 9px;
  color: #1e293b;
  background: #ffffff;
}

/* ── ACCENT ── */
.accent-bar { height: 5px; background: #16a34a; }

/* ── HEADER ── */
.hdr-wrap { background: #0f2744; padding: 12px 20px 10px 20px; }
.hdr-tbl  { width: 100%; border-collapse: collapse; }
.hdr-tbl td { vertical-align: top; padding: 0; }

.logo-box {
  width: 32px; height: 32px;
  background: #16a34a;
  border-radius: 5px;
  text-align: center;
  font-size: 15px; font-weight: 900;
  color: #ffffff;
  line-height: 32px;
  display: inline-block;
}
.co-name { font-size: 12px; font-weight: bold; color: #f8fafc; }
.co-sub  { font-size: 7px; color: #94a3b8; display: block; margin-top: 1px; }
.rpt-title{ font-size: 17px; font-weight: bold; color: #f8fafc; margin-top: 6px; }
.rpt-sub  { font-size: 7.5px; color: #94a3b8; margin-top: 2px; }

.info-box {
  border: 1px solid #2a4a7a;
  border-radius: 5px;
  padding: 7px 11px;
  text-align: right;
  font-size: 7.5px;
  color: #cbd5e1;
  line-height: 1.75;
}
.info-box strong { color: #f8fafc; }

/* ── STRIP ── */
.strip-wrap { background: #1a3455; border-top: 2px solid #16a34a; padding: 7px 20px; }
.strip-tbl  { width: 100%; border-collapse: collapse; }
.strip-tbl td {
  padding: 0 14px 0 0;
  border-right: 1px solid #263f63;
  width: 20%;
  vertical-align: top;
}
.strip-tbl td:last-child { border-right: none; padding-right: 0; }
.sl { font-size: 6.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.sv { font-size: 9px; font-weight: bold; color: #f8fafc; }

/* ── KPI ── */
.kpi-wrap { background: #f1f5f9; border-bottom: 1px solid #e2e8f0; padding: 10px 20px; }
.kpi-tbl  { width: 100%; border-collapse: collapse; }
.kpi-tbl td { padding: 0 5px; }
.kpi-tbl td:first-child { padding-left: 0; }
.kpi-tbl td:last-child  { padding-right: 0; }

.kpi-card {
  border-radius: 6px;
  padding: 0;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.kpi-top  { height: 4px; }
.kpi-body { padding: 8px 10px; text-align: center; }
.kpi-lbl  { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.kpi-val  { font-size: 20px; font-weight: bold; line-height: 1.1; }
.kpi-val-sm { font-size: 10px; font-weight: bold; line-height: 1.3; }

.k-blue   .kpi-top { background: #3b82f6; }
.k-blue   .kpi-body { background: #eff6ff; }
.k-blue   .kpi-lbl  { color: #1e40af; }
.k-blue   .kpi-val  { color: #1d4ed8; }

.k-green  .kpi-top { background: #16a34a; }
.k-green  .kpi-body { background: #f0fdf4; }
.k-green  .kpi-lbl  { color: #065f46; }
.k-green  .kpi-val-sm { color: #047857; }

.k-yellow .kpi-top { background: #f59e0b; }
.k-yellow .kpi-body { background: #fffbeb; }
.k-yellow .kpi-lbl  { color: #92400e; }
.k-yellow .kpi-val  { color: #d97706; }

.k-red    .kpi-top { background: #ef4444; }
.k-red    .kpi-body { background: #fff5f5; }
.k-red    .kpi-lbl  { color: #991b1b; }
.k-red    .kpi-val  { color: #dc2626; }

/* ── SECTION HEADER ── */
.sec-hdr {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  padding: 7px 20px;
}
.sec-hdr-tbl { width: 100%; border-collapse: collapse; }
.sec-hdr-tbl td { vertical-align: middle; padding: 0; }
.sec-title { font-size: 9px; font-weight: bold; color: #1a3a5c; }
.sec-sub   { font-size: 7.5px; color: #64748b; text-align: right; }

/* ── DATA TABLE ── */
.tbl-wrap { padding: 0 20px 20px 20px; margin-top: 10px; }

table.dt {
  width: 100%;
  border-collapse: collapse;
  font-size: 8.5px;
}

table.dt thead th {
  background: #0f2744;
  color: #ffffff;
  font-size: 7.5px;
  font-weight: bold;
  padding: 6px 5px;
  text-align: center;
  border-right: 1px solid #1e4080;
  border-bottom: 2px solid #16a34a;
  white-space: nowrap;
}
table.dt thead th:last-child { border-right: none; }
table.dt thead th.tl { text-align: left; }
table.dt thead th.tr { text-align: right; }

/* Main item rows */
table.dt tbody tr.r-item td {
  padding: 5px 5px;
  border-right: 1px solid #e8edf5;
  border-bottom: 1px solid #e8edf5;
  vertical-align: middle;
}
table.dt tbody tr.r-item:last-child td { border-bottom: 1px solid #e8edf5; }
table.dt tbody tr.r-e  td { background: #f8fafc; }
table.dt tbody tr.r-o  td { background: #ffffff; }
table.dt tbody tr.r-minus  td { background: #fff5f5; }
table.dt tbody tr.r-kritis td { background: #fffbeb; }

/* Layer rows */
table.dt tbody tr.r-layer td {
  padding: 3.5px 5px;
  border-right: 1px solid #e8edf5;
  border-bottom: 1px dashed #dde8f8;
  background: #f0f5ff;
  font-size: 8px;
  vertical-align: middle;
}

/* FIFO subtotal row */
table.dt tbody tr.r-sub td {
  padding: 4px 5px;
  border: 1px solid #c5d8f0;
  background: #e8f0fb;
  font-size: 8px;
  vertical-align: middle;
}

/* Grand total row */
table.dt tbody tr.r-total td {
  background: #0f2744;
  color: #ffffff;
  font-weight: bold;
  font-size: 9px;
  padding: 7px 5px;
  border-right: 1px solid #1e4080;
}
table.dt tbody tr.r-total td:last-child { border-right: none; }
table.dt tbody tr.r-total td.r-total-val {
  color: #4ade80;
  font-size: 10px;
  text-align: right;
}

/* ── BADGES ── */
.bdg {
  display: inline-block;
  padding: 2px 5px;
  border-radius: 3px;
  font-size: 7px;
  font-weight: bold;
  text-align: center;
  white-space: nowrap;
}
.b-normal { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.b-kritis { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.b-minus  { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
.b-fifo   { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; font-size: 6px; }
.b-batch  { background: #0f2744; color: #ffffff; }
.b-src    { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

/* ── QTY COLOURS ── */
.cok  { color: #15803d; font-weight: bold; }
.ckr  { color: #d97706; font-weight: bold; }
.cmn  { color: #dc2626; font-weight: bold; }
.cbl  { color: #1d4ed8; font-weight: bold; }
.cgr  { color: #047857; font-weight: bold; }
.cmu  { color: #64748b; }
.mono { font-family: 'DejaVu Sans Mono', monospace; }

/* ── FOOTER ── */
.ftr-wrap {
  margin: 0 20px;
  padding: 10px 0;
  border-top: 1px solid #e2e8f0;
}
.ftr-tbl { width: 100%; border-collapse: collapse; }
.ftr-tbl td { vertical-align: bottom; padding: 0; }
.ftr-text { font-size: 7.5px; color: #94a3b8; line-height: 1.7; }
.ftr-text strong { color: #475569; }

.sign-box {
  display: inline-block;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 5px 10px;
  text-align: center;
  width: 95px;
  margin-left: 10px;
}
.sign-lbl  { font-size: 6.5px; color: #94a3b8; margin-bottom: 1px; }
.sign-line { margin-top: 26px; border-top: 1px solid #94a3b8; padding-top: 3px; font-size: 6.5px; color: #475569; }
</style>
</head>
<body>

@php
  use Carbon\Carbon;

  $now       = now();
  $dateStr   = $now->locale('id')->isoFormat('D MMMM YYYY');
  $timeStr   = $now->format('H:i');

  $whName    = $request['warehouse_name'] ?? 'Semua Gudang';
  $catName   = $request['category_name']  ?? 'Semua Kategori';
  $fltLabel  = match($request['filter'] ?? '') {
    'critical' => 'Stok Kritis', 'minus' => 'Stok Minus', default => 'Semua Status',
  };

  $totItems  = $summary['total_items'] ?? count($data);
  $totValue  = $summary['total_value'] ?? 0;
  $totKritis = $summary['critical']    ?? 0;
  $totMinus  = $summary['minus']       ?? 0;

  $rp  = fn($v) => 'Rp ' . number_format(max(0, (float)$v), 0, ',', '.');
  $num = fn($v) => number_format((float)$v, 0, ',', '.');

  $srcMap = ['po'=>'PO','import'=>'Saldo Awal','transfer'=>'Transfer','opname'=>'Opname'];

  $multiWh = empty($request['warehouse_id']);
  $cols    = $multiWh ? 13 : 12;
@endphp

{{-- ACCENT --}}
<div class="accent-bar"></div>

{{-- ═══ HEADER ═══ --}}
<div class="hdr-wrap">
  <table class="hdr-tbl">
    <tr>
      <td style="width:58%;vertical-align:top;">
        <table style="border-collapse:collapse;"><tr>
          <td style="vertical-align:middle;padding-right:8px;">
            <div class="logo-box">C</div>
          </td>
          <td style="vertical-align:middle;">
            <span class="co-name">CSM Inventory</span>
            <span class="co-sub">PT. Cipta Sarana Makmur</span>
          </td>
        </tr></table>
        <div class="rpt-title" style="margin-top:8px;">Laporan Stok Persediaan</div>
        <div class="rpt-sub" style="margin-bottom:2px;">Posisi stok dengan rincian batch FIFO per gudang</div>
      </td>
      <td style="width:42%;vertical-align:top;text-align:right;">
        <div class="info-box">
          <strong>Tanggal Cetak</strong><br>
          {{ $dateStr }}, pukul {{ $timeStr }} WIB<br>
          <strong>Gudang / Site</strong><br>
          {{ $whName }}<br>
          <strong>Filter</strong> &nbsp; {{ $fltLabel }}
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- ═══ STRIP ═══ --}}
<div class="strip-wrap">
  <table class="strip-tbl">
    <tr>
      <td>
        <div class="sl">Gudang / Site</div>
        <div class="sv">{{ $whName }}</div>
      </td>
      <td>
        <div class="sl">Kategori</div>
        <div class="sv">{{ $catName }}</div>
      </td>
      <td>
        <div class="sl">Filter Status</div>
        <div class="sv">{{ $fltLabel }}</div>
      </td>
      <td>
        <div class="sl">Total Jenis Barang</div>
        <div class="sv">{{ $totItems }} item</div>
      </td>
      <td style="text-align:right;">
        <div class="sl">Tanggal Generate</div>
        <div class="sv">{{ $dateStr }}</div>
      </td>
    </tr>
  </table>
</div>

{{-- ═══ KPI ═══ --}}
<div class="kpi-wrap">
  <table class="kpi-tbl">
    <tr>
      <td>
        <div class="kpi-card k-blue">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">Jenis Barang</div>
            <div class="kpi-val">{{ $totItems }}</div>
          </div>
        </div>
      </td>
      <td>
        <div class="kpi-card k-green">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">Total Nilai Stok</div>
            <div class="kpi-val-sm">{{ $rp($totValue) }}</div>
          </div>
        </div>
      </td>
      <td>
        <div class="kpi-card k-yellow">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">Stok Kritis</div>
            <div class="kpi-val">{{ $totKritis }}</div>
          </div>
        </div>
      </td>
      <td>
        <div class="kpi-card k-red">
          <div class="kpi-top"></div>
          <div class="kpi-body">
            <div class="kpi-lbl">Stok Minus</div>
            <div class="kpi-val">{{ $totMinus }}</div>
          </div>
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- ═══ SECTION HEADER ═══ --}}
<div class="sec-hdr">
  <table class="sec-hdr-tbl">
    <tr>
      <td><span class="sec-title">Daftar Stok Persediaan</span></td>
      <td><span class="sec-sub">{{ $totItems }} item &nbsp;&bull;&nbsp; Harga metode FIFO per batch</span></td>
    </tr>
  </table>
</div>

{{-- ═══ TABLE ═══ --}}
<div class="tbl-wrap">
<table class="dt">
  <thead>
    <tr>
      <th style="width:20px;">#</th>
      <th class="tl" style="width:78px;">Part Number</th>
      <th class="tl" style="min-width:120px;">Nama Barang</th>
      <th style="width:60px;">Kategori</th>
      @if($multiWh)
      <th class="tl" style="width:85px;">Gudang</th>
      @endif
      <th style="width:26px;">Sat.</th>
      <th style="width:34px;">Stok</th>
      <th style="width:26px;">Min</th>
      <th style="width:55px;">Tgl. Masuk</th>
      <th style="width:38px;">Qty<br>Batch</th>
      <th class="tr" style="width:82px;">Harga Beli</th>
      <th class="tr" style="width:82px;">Nilai Layer</th>
      <th style="width:40px;">Status</th>
    </tr>
  </thead>
  <tbody>
  @php $grandTotal = 0; @endphp

  @foreach($data as $idx => $s)
    @php
      $qty     = (float)($s['qty'] ?? 0);
      $minStk  = (float)($s['item']->min_stock ?? 0);
      $minus   = $qty < 0;
      $kritis  = !$minus && $minStk > 0 && $qty <= $minStk;
      $layers  = $s['layers'] ?? [];
      $nilaiItem = collect($layers)->sum('nilai');
      $grandTotal += $nilaiItem;

      if ($minus)       $rClass = 'r-item r-minus';
      elseif ($kritis)  $rClass = 'r-item r-kritis';
      elseif ($idx % 2) $rClass = 'r-item r-o';
      else              $rClass = 'r-item r-e';

      $qClass = $minus ? 'cmn' : ($kritis ? 'ckr' : 'cok');
    @endphp

    {{-- ── ITEM ROW ── --}}
    <tr class="{{ $rClass }}">
      <td style="text-align:center;color:#94a3b8;font-size:7.5px;">{{ $idx+1 }}</td>
      <td>
        <span class="mono cbl" style="font-size:7.5px;">{{ $s['item']->part_number ?? '—' }}</span>
      </td>
      <td>
        <span style="font-weight:bold;font-size:8.5px;color:#0f172a;">{{ $s['item']->name ?? '—' }}</span>
        @if(!empty($s['item']->brand))
          <br><span style="font-size:7px;" class="cmu">{{ $s['item']->brand }}</span>
        @endif
      </td>
      <td style="text-align:center;">
        <span class="bdg b-src">{{ $s['item']->category->name ?? '—' }}</span>
      </td>
      @if($multiWh)
      <td>
        @foreach($s['gudang'] ?? [] as $g)
          <span style="font-size:7px;color:#334155;">{{ str_replace('Gudang ','',$g['name']) }}&nbsp;({{ $num($g['qty']) }})</span><br>
        @endforeach
      </td>
      @endif
      <td style="text-align:center;color:#64748b;font-size:8px;">{{ $s['item']->unit ?? '—' }}</td>
      <td style="text-align:center;">
        <span class="{{ $qClass }}" style="font-size:10px;">{{ $num($qty) }}</span>
      </td>
      <td style="text-align:center;font-size:8px;color:#94a3b8;">{{ $minStk > 0 ? $num($minStk) : '—' }}</td>
      {{-- Tgl Masuk kosong di main row --}}
      <td style="text-align:center;">
        <span class="bdg b-batch">{{ count($layers) }}&nbsp;batch</span>
      </td>
      <td style="text-align:center;font-size:8px;color:#94a3b8;">{{ $num($qty) }}</td>
      <td style="text-align:right;color:#94a3b8;font-size:8px;">—</td>
      <td style="text-align:right;" class="cbl">
        <strong>{{ $rp($nilaiItem) }}</strong>
      </td>
      <td style="text-align:center;">
        @if($minus)    <span class="bdg b-minus">Minus</span>
        @elseif($kritis) <span class="bdg b-kritis">Kritis</span>
        @else            <span class="bdg b-normal">Normal</span>
        @endif
      </td>
    </tr>

    {{-- ── LAYER ROWS ── --}}
    @foreach($layers as $li => $layer)
    <tr class="r-layer">
      <td style="text-align:center;color:#c8d8f0;font-size:9px;">&#x2514;</td>
      <td colspan="2" style="padding-left:12px;">
        <span class="bdg b-batch">Batch&nbsp;{{ $li+1 }}</span>
        &nbsp;
        <span class="mono cmu" style="font-size:7.5px;">{{ $layer['reference_no'] ?? '—' }}</span>
        &nbsp;
        <span class="bdg b-src">{{ $srcMap[$layer['source_type'] ?? ''] ?? ($layer['source_type'] ?? '') }}</span>
      </td>
      <td></td>
      @if($multiWh)<td></td>@endif
      <td></td>
      <td></td>
      <td></td>
      {{-- Tgl Masuk --}}
      <td style="text-align:center;color:#475569;font-size:8px;">
        {{ !empty($layer['tanggal_masuk']) ? Carbon::parse($layer['tanggal_masuk'])->format('d/m/Y') : '—' }}
      </td>
      {{-- Qty sisa / awal --}}
      <td style="text-align:right;">
        <span class="cgr" style="font-size:8.5px;">{{ $num($layer['qty_sisa']) }}</span>
        @if(($layer['qty_awal'] ?? 0) != ($layer['qty_sisa'] ?? 0))
          <span class="cmu" style="font-size:7px;">&nbsp;/&nbsp;{{ $num($layer['qty_awal']) }}</span>
        @endif
      </td>
      {{-- Harga FIFO --}}
      <td style="text-align:right;">
        <span class="cbl" style="font-size:8.5px;">{{ $rp($layer['harga_satuan']) }}</span>
        <span class="bdg b-fifo">FIFO</span>
      </td>
      {{-- Nilai --}}
      <td style="text-align:right;font-weight:bold;color:#1e293b;font-size:8.5px;">
        {{ ($layer['nilai'] ?? 0) > 0 ? $rp($layer['nilai']) : '—' }}
      </td>
      <td></td>
    </tr>
    @endforeach

    {{-- ── SUBTOTAL FIFO (jika > 1 layer) ── --}}
    @if(count($layers) > 1)
    <tr class="r-sub">
      <td colspan="{{ $multiWh ? 11 : 10 }}"
          style="text-align:right;font-size:7.5px;color:#475569;padding-right:5px;">
        Total Nilai FIFO &mdash; <strong>{{ $s['item']->name ?? '' }}</strong>
      </td>
      <td style="text-align:right;" class="cbl"><strong>{{ $rp($nilaiItem) }}</strong></td>
      <td></td>
    </tr>
    @endif

  @endforeach

  {{-- ── GRAND TOTAL ── --}}
  <tr class="r-total">
    <td colspan="{{ $multiWh ? 11 : 10 }}" style="text-align:right;padding-right:6px;">
      TOTAL NILAI STOK KESELURUHAN
    </td>
    <td class="r-total-val">{{ $rp($grandTotal) }}</td>
    <td></td>
  </tr>

  </tbody>
</table>
</div>

{{-- ═══ FOOTER ═══ --}}
<div class="ftr-wrap">
  <table class="ftr-tbl">
    <tr>
      <td style="width:58%;vertical-align:bottom;">
        <p class="ftr-text">
          Laporan ini digenerate otomatis oleh <strong>CSM Inventory System</strong>
          pada <strong>{{ $dateStr }}</strong> pukul <strong>{{ $timeStr }} WIB</strong>.
        </p>
        <p class="ftr-text" style="margin-top:2px;">
          Harga menggunakan metode <strong>FIFO (First-In, First-Out)</strong> per batch stok masuk.
        </p>
      </td>
      <td style="width:42%;text-align:right;vertical-align:bottom;">
        <div class="sign-box">
          <div class="sign-lbl">Dibuat oleh</div>
          <div class="sign-line">Bagian Gudang</div>
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