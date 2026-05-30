{{--
  ══════════════════════════════════════════════════════════════════
  PARTIAL: pdf/partials/signature-section.blade.php

  Layout baru: info laporan di atas (full width), TTD di bawah (full width).
  Kotak TTD lebih besar dan tidak terpotong saat ada 3 penandatangan.
  ══════════════════════════════════════════════════════════════════
--}}

@php
  $defaultSigners = [
    ['label' => 'Dibuat oleh',    'name' => '', 'position' => 'Bagian Gudang', 'signature' => null],
    ['label' => 'Disetujui oleh', 'name' => '', 'position' => 'Kepala Gudang', 'signature' => null],
  ];
  $resolvedSigners = (!empty($signers) && count($signers) > 0) ? $signers : $defaultSigners;
  $count = count($resolvedSigners);

  // Lebar kotak otomatis berdasarkan jumlah signer
  // Total lebar area = 555px (A4 minus margin 20px kiri-kanan)
  // Kotak per signer = total / count, dikurangi gap antar kotak
  $gap     = 10;
  $totalW  = 555;
  $boxW    = (int) floor(($totalW - ($gap * ($count - 1))) / $count);
  $imgMaxW = $boxW - 20; // padding kiri+kanan 10px
  $imgMaxH = 160;        // tinggi area gambar (naik dari 100 → 160px)
@endphp

<style>
/* ── Signature Section ── */
.sign-section-wrap {
  margin: 0 20px;
  padding: 8px 0 4px 0;
  border-top: 1px solid #e2e8f0;
}

.sign-ftr-text {
  font-size: 7.5px;
  color: #94a3b8;
  line-height: 1.7;
  margin: 0 0 6px 0;
}
.sign-ftr-text strong { color: #475569; }

/* Row TTD: pakai table agar DomPDF bisa layout kolom sama rata */
.sign-row-tbl {
  width: 100%;
  border-collapse: separate;
  border-spacing: {{ $gap }}px 0;
  table-layout: fixed;
}
.sign-row-tbl td {
  vertical-align: bottom;
  padding: 0;
}

.sign-box {
  display: block;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 6px 10px 5px 10px;
  text-align: center;
  background: #fafbfc;
}

.sign-lbl {
  font-size: 7px;
  color: #94a3b8;
  margin-bottom: 3px;
  letter-spacing: 0.4px;
  text-transform: uppercase;
}

.sign-img-area {
  height: {{ $imgMaxH }}px;
  text-align: center;
  vertical-align: middle;
}
.sign-img-area img {
  max-height: {{ $imgMaxH - 2 }}px;
  max-width: {{ $imgMaxW }}px;
  height: auto;
  width: auto;
}

.sign-img-placeholder {
  height: {{ $imgMaxH }}px;
}

.sign-name-line {
  border-top: 1px solid #94a3b8;
  padding-top: 3px;
  margin-top: 3px;
  font-size: 8px;
  color: #0f172a;
  font-weight: bold;
}

.sign-position {
  font-size: 7px;
  color: #64748b;
  margin-top: 1px;
}

.sign-no-ttd {
  font-size: 6.5px;
  color: #f59e0b;
  margin-top: 2px;
}
</style>

<div class="sign-section-wrap">

  {{-- Baris info laporan (full width) --}}
  <p class="sign-ftr-text">
    {{ $leftText ?? 'Laporan ini digenerate otomatis oleh' }}
    <strong>CSM Inventory System</strong>
    pada <strong>{{ $dateStr }}</strong> pukul <strong>{{ $timeStr }} WIB</strong>.
  </p>
  @isset($leftSubText)
  <p class="sign-ftr-text" style="margin-top:2px;">{!! $leftSubText !!}</p>
  @endisset

  {{-- Baris kotak TTD (full width, dibagi rata) --}}
  <table class="sign-row-tbl">
    <tr>
      @foreach($resolvedSigners as $signer)
      <td>
        <div class="sign-box">

          <div class="sign-lbl">{{ $signer['label'] }}</div>

          @if(!empty($signer['signature']))
            <div class="sign-img-area">
              <img src="{{ $signer['signature'] }}" alt="TTD">
            </div>
          @else
            <div class="sign-img-placeholder"></div>
          @endif

          <div class="sign-name-line">
            {{ !empty($signer['name'])
                ? (mb_strlen($signer['name']) > 25 ? mb_substr($signer['name'], 0, 24).'…' : $signer['name'])
                : '(______________)'
            }}
          </div>

          @if(!empty($signer['position']))
          <div class="sign-position">
            {{ mb_strlen($signer['position']) > 28
                ? mb_substr($signer['position'], 0, 27).'…'
                : $signer['position'] }}
          </div>
          @endif

          @if(!empty($signer['name']) && empty($signer['signature']))
          <div class="sign-no-ttd">&#9888; TTD belum diupload</div>
          @endif

        </div>
      </td>
      @endforeach
    </tr>
  </table>

</div>