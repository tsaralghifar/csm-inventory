{{--
  ══════════════════════════════════════════════════════════════════
  PARTIAL: pdf/partials/signature-section.blade.php

  Dipakai di semua template PDF (stock, purchase, parts-history, dsb).
  Cara include:
    @include('pdf.partials.signature-section', [
        'signers'     => $signers,         // array dari ReportController::resolveSigners()
        'dateStr'     => $dateStr,         // string tanggal cetak
        'timeStr'     => $timeStr,         // string jam cetak
        'leftText'    => 'Keterangan...', // opsional, override teks kiri
        'leftSubText' => '...',           // opsional, baris kedua teks kiri (sudah di-escape)
    ])

  Jika $signers kosong, tampil kotak TTD kosong (placeholder jabatan).

  CSS hanya menggunakan properti yang didukung DomPDF:
  - TIDAK pakai: object-fit, text-overflow, white-space, flexbox, grid
  - Layout via: table, inline-block, width/height eksplisit
  ══════════════════════════════════════════════════════════════════
--}}

@php
  // Fallback ketika tidak ada signer dipilih
  $defaultSigners = [
    ['label' => 'Dibuat oleh',    'name' => '', 'position' => 'Bagian Gudang', 'signature' => null],
    ['label' => 'Disetujui oleh', 'name' => '', 'position' => 'Kepala Gudang', 'signature' => null],
  ];

  $resolvedSigners = (!empty($signers) && count($signers) > 0) ? $signers : $defaultSigners;
@endphp

<style>
/* ── Signature Section — DomPDF-safe (no flexbox, no object-fit, no text-overflow) ── */
.sign-section-wrap {
  margin: 0 20px;
  padding: 10px 0 6px 0;
  border-top: 1px solid #e2e8f0;
}
.sign-section-tbl { width: 100%; border-collapse: collapse; }
.sign-section-tbl td { vertical-align: bottom; padding: 0; }

.sign-ftr-text { font-size: 7.5px; color: #94a3b8; line-height: 1.7; }
.sign-ftr-text strong { color: #475569; }

/* Kotak tanda tangan — inline-block, lebar & tinggi eksplisit */
.sign-box {
  display: inline-block;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  padding: 5px 8px 4px 8px;
  text-align: center;
  width: 110px;
  margin-left: 8px;
  vertical-align: bottom;
}
.sign-lbl {
  font-size: 6.5px;
  color: #94a3b8;
  margin-bottom: 2px;
  letter-spacing: 0.4px;
}
/* Area gambar TTD: tinggi tetap tanpa object-fit */
.sign-img-area {
  height: 40px;
  text-align: center;
}
.sign-img-area img {
  max-height: 38px;
  max-width: 95px;
  /* object-fit tidak didukung DomPDF — gunakan max-height/max-width saja */
}
/* Placeholder kosong jika tidak ada TTD */
.sign-img-placeholder {
  height: 40px;
}
/* Nama penandatangan */
.sign-name-line {
  border-top: 1px solid #94a3b8;
  padding-top: 3px;
  margin-top: 2px;
  font-size: 7px;
  color: #0f172a;
  font-weight: bold;
  /* white-space/overflow/text-overflow tidak didukung DomPDF — potong via PHP */
}
/* Jabatan */
.sign-position {
  font-size: 6.5px;
  color: #64748b;
  margin-top: 1px;
}
/* Peringatan TTD belum diupload */
.sign-no-ttd {
  font-size: 6px;
  color: #f59e0b;
  margin-top: 1px;
}
</style>

<div class="sign-section-wrap">
  <table class="sign-section-tbl">
    <tr>
      {{-- Kolom kiri: info laporan --}}
      <td style="width:55%; vertical-align:bottom;">
        <p class="sign-ftr-text">
          {{ $leftText ?? 'Laporan ini digenerate otomatis oleh' }}
          <strong>CSM Inventory System</strong>
          pada <strong>{{ $dateStr }}</strong> pukul <strong>{{ $timeStr }} WIB</strong>.
        </p>
        @isset($leftSubText)
        {{-- {!! !!} dipakai karena leftSubText bisa berisi &bull; dari controller --}}
        <p class="sign-ftr-text" style="margin-top:2px;">{!! $leftSubText !!}</p>
        @endisset
      </td>

      {{-- Kolom kanan: kotak tanda tangan --}}
      <td style="width:45%; vertical-align:bottom; text-align:right;">
        <div>
          @foreach($resolvedSigners as $signer)
          <div class="sign-box">

            {{-- Label slot --}}
            <div class="sign-lbl">{{ $signer['label'] }}</div>

            {{-- Gambar TTD atau placeholder --}}
            @if(!empty($signer['signature']))
              <div class="sign-img-area">
                <img src="{{ $signer['signature'] }}" alt="TTD">
              </div>
            @else
              <div class="sign-img-placeholder"></div>
            @endif

            {{-- Nama — dipotong max 18 karakter via PHP agar tidak overflow di DomPDF --}}
            <div class="sign-name-line">
              {{ !empty($signer['name'])
                  ? (mb_strlen($signer['name']) > 18 ? mb_substr($signer['name'], 0, 17) . '…' : $signer['name'])
                  : '(______________)'
              }}
            </div>

            {{-- Jabatan — dipotong max 20 karakter --}}
            @if(!empty($signer['position']))
            <div class="sign-position">
              {{ mb_strlen($signer['position']) > 20
                  ? mb_substr($signer['position'], 0, 19) . '…'
                  : $signer['position'] }}
            </div>
            @endif

            {{-- Peringatan jika user dipilih tapi belum upload TTD --}}
            @if(!empty($signer['name']) && empty($signer['signature']))
            <div class="sign-no-ttd">&#9888; TTD belum diupload</div>
            @endif

          </div>
          @endforeach
        </div>
      </td>
    </tr>
  </table>
</div>
