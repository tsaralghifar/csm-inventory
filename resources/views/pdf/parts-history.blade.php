<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Riwayat Pemakaian Part — {{ $unit->unit_code }}</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'DejaVu Sans', Arial, sans-serif;
  font-size: 9.5px;
  color: #1e293b;
  background: #fff;
}

/* ── HEADER ── */
.hdr-accent {
  height: 4px;
  background: #f59e0b; /* DomPDF no gradient – single color accent */
}
.hdr-main {
  background: #0f2744;
  padding: 12px 20px 0 20px;
}
.hdr-top-table { width: 100%; border-collapse: collapse; }
.hdr-top-table td { vertical-align: top; padding: 0; }

.logo-box {
  width: 28px; height: 28px;
  background: #f59e0b;
  border-radius: 5px;
  text-align: center;
  font-size: 14px;
  font-weight: 900;
  color: #0f2744;
  line-height: 28px;
  display: inline-block;
}
.company-name { font-size: 12px; font-weight: bold; color: #f8fafc; }
.company-sub  { font-size: 7.5px; color: #94a3b8; }
.report-title { font-size: 17px; font-weight: bold; color: #f8fafc; margin-top: 4px; }
.report-sub   { font-size: 8px; color: #94a3b8; margin-top: 2px; }

.print-box {
  border: 1px solid rgba(255,255,255,0.18);
  border-radius: 5px;
  padding: 5px 9px;
  font-size: 7.5px;
  color: #cbd5e1;
  line-height: 1.65;
  text-align: right;
}
.print-box-title { color: #f8fafc; font-weight: bold; font-size: 8px; display: block; margin-bottom: 1px; }

/* Unit strip */
.unit-strip {
  background: #1a3455;
  border-top: 2px solid #f59e0b;
  padding: 7px 20px;
}
.unit-strip-table { width: 100%; border-collapse: collapse; }
.unit-strip-table td {
  padding: 0 14px 0 0;
  border-right: 1px solid rgba(255,255,255,0.1);
  width: 20%;
}
.unit-strip-table td:last-child { border-right: none; padding-right: 0; }
.us-label { font-size: 6.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 2px; }
.us-val   { font-size: 9.5px; font-weight: bold; color: #f8fafc; }

/* ── SUMMARY ── */
.summary-wrap {
  background: #f1f5f9;
  border-bottom: 1px solid #e2e8f0;
  padding: 10px 20px;
}
.summary-table { width: 100%; border-collapse: collapse; }
.summary-table td { padding: 0 6px; }
.summary-table td:first-child { padding-left: 0; }
.summary-table td:last-child  { padding-right: 0; }

.s-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 8px 12px;
  text-align: center;
}
.s-card-top { height: 3px; border-radius: 6px 6px 0 0; margin: -8px -12px 6px; }
.s-blue   { background: #3b82f6; }
.s-teal   { background: #14b8a6; }
.s-violet { background: #8b5cf6; }
.s-red    { background: #ef4444; }
.s-val    { font-size: 13px; font-weight: bold; color: #0f172a; line-height: 1.1; }
.s-val-red{ font-size: 11.5px; font-weight: bold; color: #dc2626; line-height: 1.1; }
.s-lbl    { font-size: 7px; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 2px; }

/* ── CONTENT ── */
.content { padding: 12px 20px; }

.section-hdr {
  font-size: 8px; font-weight: bold; color: #64748b;
  text-transform: uppercase; letter-spacing: 0.8px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 4px; margin-bottom: 10px;
}

/* ── BON CARD ── */
.bon-card {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  margin-bottom: 12px;
  overflow: hidden;
  page-break-inside: avoid;
}

.bon-hdr { background: #0f2744; padding: 7px 12px; }
.bon-hdr-table { width: 100%; border-collapse: collapse; }
.bon-hdr-table td { vertical-align: middle; padding: 0; }

.bon-num  { font-size: 10.5px; font-weight: bold; color: #f8fafc; }
.bon-date { font-size: 7.5px; color: #94a3b8; margin-top: 1px; }

.wh-pill {
  background: rgba(245,158,11,0.22);
  border: 1px solid rgba(245,158,11,0.45);
  color: #fbbf24; font-size: 7.5px; font-weight: bold;
  padding: 3px 9px; border-radius: 20px; white-space: nowrap;
}

.mech-info { font-size: 7.5px; color: #94a3b8; text-align: right; line-height: 1.6; }
.mech-info b { color: #e2e8f0; }

.bon-meta {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  padding: 4px 12px;
}
.bon-meta-table { width: 100%; border-collapse: collapse; }
.bon-meta-table td { font-size: 7.5px; color: #64748b; padding: 0 16px 0 0; }
.bon-meta-table td b { color: #334155; }
.bon-meta-table td:last-child { padding-right: 0; }

/* ── TABLE ── */
.items-table {
  width: 100%;
  border-collapse: collapse;
}
.items-table thead tr { background: #f1f5f9; }
.items-table thead th {
  padding: 5px 7px;
  text-align: left;
  font-size: 7px;
  font-weight: bold;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 2px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  white-space: nowrap;
}
.items-table thead th:last-child { border-right: none; }
.items-table tbody tr { border-bottom: 1px solid #f1f5f9; }
.items-table tbody tr.even-row { background: #fafbfc; }
.items-table tbody tr.batch-row { background: #eff6ff; }
.items-table tbody td {
  padding: 5px 7px;
  font-size: 8.5px;
  color: #334155;
  border-right: 1px solid #f1f5f9;
  vertical-align: middle;
}
.items-table tbody td:last-child { border-right: none; }
.tr { text-align: right; }
.tc { text-align: center; }

.row-circle {
  width: 15px; height: 15px;
  background: #e2e8f0; border-radius: 50%;
  font-size: 6.5px; font-weight: bold; color: #475569;
  text-align: center; line-height: 15px;
  display: inline-block;
}
.pname { font-weight: bold; color: #0f172a; font-size: 9px; }
.pnum {
  font-size: 7.5px; color: #2563eb;
  background: #eff6ff; padding: 1px 4px; border-radius: 3px;
}
.fifo-badge {
  background: #0ea5e9; color: #fff;
  padding: 1px 4px; border-radius: 3px;
  font-size: 6px; font-weight: bold;
  margin-left: 2px;
}
.qval { font-weight: bold; color: #0f172a; font-size: 9px; }
.subval { font-weight: bold; color: #0f172a; }
.ket { color: #64748b; font-size: 7.5px; font-style: italic; }
.batch-lbl { color: #64748b; font-size: 7.5px; padding-left: 10px; }
.dash { color: #cbd5e1; }

/* BON TOTAL */
.bon-total-row {
  background: #fff8f8;
  border-top: 2px solid #fecaca;
  padding: 6px 12px;
}
.bon-total-row-table { width: 100%; border-collapse: collapse; }
.bon-total-row-table td { padding: 0; vertical-align: middle; }
.bon-total-lbl { font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
.bon-total-val { font-size: 12px; font-weight: bold; color: #dc2626; text-align: right; }

/* ── GRAND TOTAL ── */
.grand-total-wrap { padding: 0 20px 14px; }
.grand-total-box {
  background: #0f2744;
  border-radius: 6px;
  padding: 11px 16px;
}
.gt-table { width: 100%; border-collapse: collapse; }
.gt-table td { padding: 0; vertical-align: middle; }
.gt-eyebrow { font-size: 7px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 2px; }
.gt-desc    { font-size: 8.5px; color: #cbd5e1; }
.gt-val     { font-size: 19px; font-weight: bold; color: #f59e0b; text-align: right; }

/* ── FOOTER ── */
.footer-wrap {
  margin: 0 20px;
  border-top: 1px solid #e2e8f0;
  padding: 7px 0 10px;
}
.footer-table { width: 100%; border-collapse: collapse; }
.footer-table td { padding: 0; vertical-align: middle; }
.footer-l { font-size: 7.5px; color: #94a3b8; }
.footer-l b { color: #64748b; }
.footer-r { font-size: 7px; color: #cbd5e1; text-align: right; }
.dot { color: #f59e0b; }

/* helpers */
.page-break { page-break-before: always; }
</style>
</head>
<body>

{{-- ══════════ HEADER ══════════ --}}
<div class="hdr-accent"></div>
<div class="hdr-main">
  <table class="hdr-top-table">
    <tr>
      <td style="width:60%; padding-bottom:10px;">
        <table style="border-collapse:collapse;">
          <tr>
            <td style="vertical-align:middle; width:36px; padding:0;">
              <div class="logo-box">C</div>
            </td>
            <td style="vertical-align:middle; padding:0 0 0 8px;">
              <div class="company-name">PT. Cipta Sarana Makmur</div>
              <div class="company-sub">CSM Inventory System</div>
            </td>
          </tr>
        </table>
        <div class="report-title">Riwayat Pemakaian Part</div>
        <div class="report-sub">
          Unit: {{ $unit->unit_code }} &mdash; {{ $unit->type_unit }}
          @if($dateFrom || $dateTo)
            &nbsp;&#9658;&nbsp; Periode:
            {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Awal' }}
            &mdash;
            {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : 'Sekarang' }}
          @endif
        </div>
      </td>
      <td style="width:40%; vertical-align:top; text-align:right; padding-bottom:10px;">
        <div class="print-box">
          <span class="print-box-title">Informasi Dokumen</span>
          Dicetak: {{ now()->format('d M Y, H:i') }}<br>
          Oleh: {{ auth()->user()->name ?? '-' }}<br>
          Dok. No: {{ $unit->unit_code }}-{{ now()->format('YmdHi') }}
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- Unit Strip --}}
<div class="unit-strip">
  <table class="unit-strip-table">
    <tr>
      <td><div class="us-label">Kode Unit</div><div class="us-val">{{ $unit->unit_code }}</div></td>
      <td><div class="us-label">Tipe Unit</div><div class="us-val">{{ $unit->type_unit ?? '-' }}</div></td>
      <td><div class="us-label">Brand</div><div class="us-val">{{ $unit->brand ?? '-' }}</div></td>
      <td><div class="us-label">Status</div><div class="us-val">{{ strtoupper($unit->status ?? '-') }}</div></td>
      <td><div class="us-label">Total BON</div><div class="us-val">{{ count($bons) }} dokumen</div></td>
    </tr>
  </table>
</div>

{{-- ══════════ SUMMARY ══════════ --}}
<div class="summary-wrap">
  <table class="summary-table">
    <tr>
      <td>
        <div class="s-card">
          <div class="s-card-top s-blue"></div>
          <div class="s-val">{{ count($bons) }}</div>
          <div class="s-lbl">BON Pengeluaran</div>
        </div>
      </td>
      <td>
        <div class="s-card">
          <div class="s-card-top s-teal"></div>
          <div class="s-val">{{ $totalItems }}</div>
          <div class="s-lbl">Item Dikeluarkan</div>
        </div>
      </td>
      <td>
        <div class="s-card">
          <div class="s-card-top s-violet"></div>
          <div class="s-val">{{ $totalQty }}</div>
          <div class="s-lbl">Total Qty</div>
        </div>
      </td>
      <td>
        <div class="s-card">
          <div class="s-card-top s-red"></div>
          <div class="s-val-red">Rp {{ number_format($grandTotal, 0, ',', '.') }}</div>
          <div class="s-lbl">Total Nilai (FIFO)</div>
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- ══════════ BON SECTIONS ══════════ --}}
<div class="content">
  <div class="section-hdr">Detail Bon Pengeluaran</div>

  @foreach($bons as $bon)
    @php
      $bonTotal = 0;
      foreach($bon->items as $item) {
        $layers = $item->fifoLayers ?? collect();
        if($layers->isNotEmpty()) {
          $bonTotal += $layers->sum(fn($l) => (float)$l->nilai);
        } else {
          $bonTotal += (float)$item->fifo_price > 0
            ? (float)$item->fifo_price * (float)$item->qty
            : (float)$item->harga_satuan * (float)$item->qty;
        }
      }
    @endphp

    <div class="bon-card">

      {{-- BON Header --}}
      <div class="bon-hdr">
        <table class="bon-hdr-table">
          <tr>
            <td style="width:40%;">
              <div class="bon-num">{{ $bon->bon_number }}</div>
              <div class="bon-date">{{ \Carbon\Carbon::parse($bon->issue_date)->format('d M Y') }}</div>
            </td>
            <td style="width:30%; text-align:center;">
              <span class="wh-pill">{{ $bon->warehouse->name ?? '-' }}</span>
            </td>
            <td style="width:30%;">
              <div class="mech-info">
                Mekanik: <b>{{ $bon->mechanic ?? '-' }}</b><br>
                HM/KM: <b>{{ $bon->hm_km ?? '-' }}</b>
              </div>
            </td>
          </tr>
        </table>
      </div>

      {{-- BON Meta --}}
      <div class="bon-meta">
        <table class="bon-meta-table">
          <tr>
            <td>Dibuat: <b>{{ $bon->creator->name ?? '-' }}</b></td>
            <td>Site: <b>{{ $bon->site_name ?? '-' }}</b></td>
            <td>Jumlah Item: <b>{{ $bon->items->count() }} item</b></td>
          </tr>
        </table>
      </div>

      {{-- Items Table --}}
      <table class="items-table">
        <thead>
          <tr>
            <th style="width:3%;"  class="tc">#</th>
            <th style="width:19%;">Nama Barang</th>
            <th style="width:12%;">Part Number</th>
            <th style="width:11%;" class="tc">Tgl. Beli</th>
            <th style="width:7%;"  class="tr">Qty</th>
            <th style="width:6%;">Sat.</th>
            <th style="width:16%;" class="tr">Harga Beli</th>
            <th style="width:14%;" class="tr">Subtotal</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($bon->items as $idx => $item)
            @php $layers = $item->fifoLayers ?? collect(); @endphp

            @if($layers->isNotEmpty())
              @foreach($layers as $li => $layer)
                <tr class="{{ $li > 0 ? 'batch-row' : ($idx % 2 == 0 ? '' : 'even-row') }}">
                  <td class="tc">
                    @if($li === 0)<span class="row-circle">{{ $idx + 1 }}</span>@endif
                  </td>
                  <td>
                    @if($li === 0)
                      <span class="pname">{{ $item->nama_barang }}</span>
                    @else
                      <span class="batch-lbl">&nbsp;&nbsp;&#9492; Batch {{ $li + 1 }}</span>
                    @endif
                  </td>
                  <td>
                    @if($li === 0)
                      <span class="pnum">{{ $item->item->part_number ?? '-' }}</span>
                    @endif
                  </td>
                  <td class="tc">
                    {{ $layer->tanggal_masuk ? \Carbon\Carbon::parse($layer->tanggal_masuk)->format('d M Y') : '-' }}
                  </td>
                  <td class="tr"><span class="qval">{{ number_format($layer->qty, 2, ',', '.') }}</span></td>
                  <td>{{ $item->satuan }}</td>
                  <td class="tr">
                    Rp {{ number_format($layer->harga_satuan, 0, ',', '.') }}<span class="fifo-badge">FIFO</span>
                  </td>
                  <td class="tr"><span class="subval">Rp {{ number_format($layer->nilai, 0, ',', '.') }}</span></td>
                  <td class="ket">{{ $li === 0 ? ($item->keterangan ?? '-') : '' }}</td>
                </tr>
              @endforeach
            @else
              @php
                $harga    = (float)$item->fifo_price > 0 ? (float)$item->fifo_price : (float)$item->harga_satuan;
                $subtotal = $harga * (float)$item->qty;
              @endphp
              <tr class="{{ $idx % 2 == 0 ? '' : 'even-row' }}">
                <td class="tc"><span class="row-circle">{{ $idx + 1 }}</span></td>
                <td><span class="pname">{{ $item->nama_barang }}</span></td>
                <td><span class="pnum">{{ $item->item->part_number ?? '-' }}</span></td>
                <td class="tc dash">-</td>
                <td class="tr"><span class="qval">{{ number_format($item->qty, 2, ',', '.') }}</span></td>
                <td>{{ $item->satuan }}</td>
                <td class="tr">
                  Rp {{ number_format($harga, 0, ',', '.') }}
                  @if((float)$item->fifo_price > 0)<span class="fifo-badge">FIFO</span>@endif
                </td>
                <td class="tr"><span class="subval">Rp {{ number_format($subtotal, 0, ',', '.') }}</span></td>
                <td class="ket">{{ $item->keterangan ?? '-' }}</td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>

      {{-- BON Total --}}
      <div class="bon-total-row">
        <table class="bon-total-row-table">
          <tr>
            <td><span class="bon-total-lbl">Total Pengeluaran BON ini</span></td>
            <td><span class="bon-total-val">Rp {{ number_format($bonTotal, 0, ',', '.') }}</span></td>
          </tr>
        </table>
      </div>

    </div>
  @endforeach
</div>

{{-- ══════════ GRAND TOTAL ══════════ --}}
<div class="grand-total-wrap">
  <div class="grand-total-box">
    <table class="gt-table">
      <tr>
        <td style="width:65%;">
          <div class="gt-eyebrow">Ringkasan Keseluruhan</div>
          <div class="gt-desc">{{ count($bons) }} BON Pengeluaran &nbsp;&#9658;&nbsp; {{ $totalItems }} Item &nbsp;&#9658;&nbsp; {{ $totalQty }} Qty Total</div>
        </td>
        <td style="width:35%;"><div class="gt-val">Rp {{ number_format($grandTotal, 0, ',', '.') }}</div></td>
      </tr>
    </table>
  </div>
</div>

{{-- ══════════ FOOTER ══════════ --}}
<div class="footer-wrap">
  <table class="footer-table">
    <tr>
      <td class="footer-l"><b>CSM Inventory System</b> &mdash; PT. Cipta Sarana Makmur <span class="dot">&#9632;</span> Dokumen ini digenerate secara otomatis</td>
      <td class="footer-r">{{ now()->format('d/m/Y H:i') }}</td>
    </tr>
  </table>
</div>

</body>
</html>