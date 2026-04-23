/**
 * Excel Export Utility — HTML-to-XLS tanpa rowspan/colspan kompleks
 * Setiap baris punya jumlah cell yang sama persis (tidak ada rowspan)
 */

function fmtDate(val) {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' })
}
function fmtNum(val) { return Number(val||0).toLocaleString('id-ID') }

const F = 'font-family:Arial,sans-serif;font-size:10pt;border-collapse:collapse;'
const B = 'border:1px solid #D1D5DB;'

const s = {
  h1:     `${F}background:#1A3A5C;color:#fff;font-size:14pt;font-weight:bold;padding:10px 14px;border:none;`,
  h2:     `${F}background:#2563A8;color:#fff;font-size:10pt;font-weight:bold;padding:5px 14px;border:none;`,
  sec:    `${F}background:#E8EDF4;color:#1A3A5C;font-weight:bold;font-size:9pt;padding:5px 10px;border:1.5px solid #1A3A5C;`,
  lbl:    `${F}background:#F8FAFC;color:#64748B;padding:5px 10px;${B}`,
  val:    `${F}background:#fff;color:#1A3A5C;font-weight:bold;padding:5px 10px;${B}`,
  val2:   `${F}background:#fff;color:#374151;padding:5px 10px;${B}`,
  empty:  `background:#fff;border:none;padding:0;`,
  // Tabel header
  th:     `${F}background:#1A3A5C;color:#fff;font-weight:bold;font-size:9pt;padding:7px 8px;border:1px solid #0F2440;text-align:center;`,
  thL:    `${F}background:#1A3A5C;color:#fff;font-weight:bold;font-size:9pt;padding:7px 8px;border:1px solid #0F2440;text-align:left;`,
  // Data baris putih
  td:     `${F}background:#fff;color:#1F2937;padding:6px 8px;${B}vertical-align:middle;`,
  tdC:    `${F}background:#fff;color:#374151;padding:6px 8px;${B}text-align:center;vertical-align:middle;`,
  tdB:    `${F}background:#fff;color:#1F2937;font-weight:bold;padding:6px 8px;${B}vertical-align:middle;`,
  tdM:    `font-family:'Courier New',monospace;font-size:9.5pt;background:#fff;color:#1A3A5C;font-weight:bold;padding:6px 8px;${B}text-align:center;vertical-align:middle;`,
  tdR:    `${F}background:#fff;padding:6px 8px;${B}text-align:right;vertical-align:middle;`,
  tdRB:   `${F}background:#fff;font-weight:bold;padding:6px 8px;${B}text-align:right;vertical-align:middle;`,
  // Data baris stripe
  tde:    `${F}background:#F1F5F9;color:#1F2937;padding:6px 8px;${B}vertical-align:middle;`,
  tdCe:   `${F}background:#F1F5F9;color:#374151;padding:6px 8px;${B}text-align:center;vertical-align:middle;`,
  tdBe:   `${F}background:#F1F5F9;color:#1F2937;font-weight:bold;padding:6px 8px;${B}vertical-align:middle;`,
  tdMe:   `font-family:'Courier New',monospace;font-size:9.5pt;background:#F1F5F9;color:#1A3A5C;font-weight:bold;padding:6px 8px;${B}text-align:center;vertical-align:middle;`,
  tdRe:   `${F}background:#F1F5F9;padding:6px 8px;${B}text-align:right;vertical-align:middle;`,
  tdRBe:  `${F}background:#F1F5F9;font-weight:bold;padding:6px 8px;${B}text-align:right;vertical-align:middle;`,
  // Totals
  totL:   `${F}background:#F8FAFC;color:#64748B;padding:6px 10px;${B}text-align:right;`,
  totV:   `${F}background:#F8FAFC;padding:6px 10px;${B}text-align:right;`,
  ppnL:   `${F}background:#FFFBEB;color:#92400E;font-weight:bold;padding:6px 10px;${B}text-align:right;`,
  ppnV:   `${F}background:#FFFBEB;color:#92400E;font-weight:bold;padding:6px 10px;${B}text-align:right;`,
  gtL:    `${F}background:#1A3A5C;color:#fff;font-weight:bold;font-size:11pt;padding:8px 12px;border:1.5px solid #1A3A5C;text-align:right;`,
  gtV:    `${F}background:#1A3A5C;color:#fff;font-weight:bold;font-size:11pt;padding:8px 12px;border:1.5px solid #1A3A5C;text-align:right;`,
  note:   `${F}background:#FFFBEB;color:#92400E;padding:7px 12px;border-left:4px solid #F59E0B;border-top:1px solid #D1D5DB;border-bottom:1px solid #D1D5DB;border-right:1px solid #D1D5DB;`,
  // Tanda tangan
  signH:  `${F}background:#E8EDF4;color:#1A3A5C;font-weight:bold;font-size:9pt;padding:6px 10px;border:1.5px solid #1A3A5C;text-align:center;`,
  signB:  `${F}background:#fff;color:#1A3A5C;font-weight:bold;padding:6px 10px;border:1.5px solid #1A3A5C;text-align:center;height:55px;vertical-align:bottom;`,
}

// Setiap baris: isi semua cell, tidak ada rowspan
function cell(v, style, extra='') { return `<td style="${style}" ${extra}>${v??''}</td>` }
function row(...cells) { return `<tr>${cells.join('')}</tr>` }
// Cell kosong pemisah antar section
function gap() { return `<td style="${s.empty}" width="14"></td>` }
// Baris kosong
function spacer(n) { return `<tr>${Array(n).fill(`<td style="${s.empty}" height="8"></td>`).join('')}</tr>` }

function download(html, filename) {
  const blob = new Blob(['\uFEFF' + html], { type:'application/vnd.ms-excel;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), { href:url, download:filename })
  document.body.appendChild(a); a.click()
  document.body.removeChild(a); URL.revokeObjectURL(url)
}

function xlsWrap(sheetName, colgroup, body) {
  return `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
<x:Name>${sheetName}</x:Name><x:WorksheetOptions><x:FitToPage/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>
<body><table border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
${colgroup}${body}
</table></body></html>`
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORT PURCHASE ORDER  (9 kolom data)
// ─────────────────────────────────────────────────────────────────────────────
export function exportPOExcel(po, toast) {
  const mrpm    = po.material_request?.mr_number || po.permintaan_material?.nomor || '-'
  const sub     = Number(po.total_amount||0)
  const ppn     = Number(po.ppn_amount||0)
  const grand   = Number(po.grand_total||sub)
  const stMap   = { draft:'Draft', sent_to_vendor:'Dikirim ke Vendor', completed:'Selesai', cancelled:'Dibatalkan' }

  // Layout: 9 kolom (#, PartNo, Nama, KodeUnit, TipeUnit, Qty, Satuan, Harga, Total)
  // Info section: kiri 4 col, gap 1 col, kanan 4 col = total 9
  const N = 9

  const colgroup = `<colgroup>
  <col style="width:35px"/><col style="width:110px"/><col style="width:230px"/>
  <col style="width:85px"/><col style="width:85px"/><col style="width:50px"/>
  <col style="width:65px"/><col style="width:125px"/><col style="width:125px"/>
</colgroup>`

  const itemRows = (po.items||[]).map((item,i) => {
    const e = i%2===1
    return row(
      cell(i+1,                            e?s.tdCe :s.tdC),
      cell(item.item?.part_number||'-',    e?s.tdMe :s.tdM),
      cell(item.nama_barang||'-',          e?s.tdBe :s.tdB),
      cell(item.kode_unit||'-',            e?s.tdCe :s.tdC),
      cell(item.tipe_unit||'-',            e?s.tdCe :s.tdC),
      cell(item.qty,                       e?s.tdCe :s.tdC),
      cell(item.satuan,                    e?s.tdCe :s.tdC),
      cell(fmtNum(item.harga_satuan),      e?s.tdRe :s.tdR),
      cell(fmtNum(item.total_harga),       e?s.tdRBe:s.tdRB),
    )
  }).join('')

  // Info rows: 4 col kiri | 1 gap | 4 col kanan
  const infoRows = [
    ['Vendor',      po.vendor_name||'-',              'Gudang Tujuan', po.warehouse?.name||'-'],
    ['Kontak',      po.vendor_contact||'-',            'Tgl. Dibuat',   fmtDate(po.created_at)],
    ['No. MR / PM', mrpm,                              'Estimasi Tiba', po.expected_date ? fmtDate(po.expected_date) : '-'],
    ['Dibuat Oleh', po.creator?.name||'-',             'Status',        (stMap[po.status]||'').toUpperCase()],
  ].map(([l1,v1,l2,v2]) => row(
    cell(l1,s.lbl,'colspan="2"'), cell(v1,s.val2,'colspan="2"'),
    gap(),
    cell(l2,s.lbl,'colspan="2"'), cell(v2,s.val2,'colspan="2"'),
  )).join('')

  const body = [
    row(cell('PT. CIPTA SARANA MAKMUR', s.h1, `colspan="${N}"`)),
    row(cell(`PURCHASE ORDER  ·  ${po.po_number}  ·  ${(stMap[po.status]||'').toUpperCase()}`, s.h2, `colspan="${N}"`)),
    spacer(N),
    row(cell('INFORMASI VENDOR',s.sec,'colspan="4"'), gap(), cell('INFORMASI PENGIRIMAN',s.sec,'colspan="4"')),
    infoRows,
    spacer(N),
    row(cell('#',s.th), cell('Part Number',s.th), cell('Nama Barang',s.thL), cell('Kode Unit',s.th), cell('Tipe Unit',s.th), cell('Qty',s.th), cell('Satuan',s.th), cell('Harga Satuan',s.th), cell('Total',s.th)),
    itemRows,
    spacer(N),
    row(...Array(7).fill(cell('',s.empty)), cell('Subtotal',s.totL), cell(fmtNum(sub),s.totV)),
    row(...Array(7).fill(cell('',s.empty)), cell(`PPN ${po.ppn_percent||0}%`,s.ppnL), cell(fmtNum(ppn),s.ppnV)),
    row(...Array(7).fill(cell('',s.empty)), cell('GRAND TOTAL',s.gtL), cell(fmtNum(grand),s.gtV)),
    po.notes ? [spacer(N), row(cell(`Catatan: ${po.notes}`,s.note,`colspan="${N}"`))] : [],
    spacer(N),
    row(cell('ORDERED BY',s.signH,'colspan="2"'), gap(), cell('LOGISTIC',s.signH,'colspan="2"'), gap(), cell('AUTHORIZED BY',s.signH,'colspan="2"'), gap(), cell('APPROVED BY',s.signH)),
    `<tr style="height:55px;">${cell('',s.signB,'colspan="2"')}${gap()}${cell('',s.signB,'colspan="2"')}${gap()}${cell(po.creator?.name||'',s.signB,'colspan="2"')}${gap()}${cell('',s.signB)}</tr>`,
  ].flat().join('\n')

  download(xlsWrap('Purchase Order', colgroup, body), `PO-${po.po_number}.xls`)
  toast?.success(`✅ PO-${po.po_number}.xls berhasil diunduh`)
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORT PERMINTAAN MATERIAL  (8 kolom data)
// ─────────────────────────────────────────────────────────────────────────────
export function exportPMExcel(pm, toast) {
  const isPart = pm.type === 'part'
  const stMap  = {
    draft:'Draft', pending_chief:'Menunggu Chief Mekanik', pending_manager:'Menunggu Manager',
    pending_ho:'Menunggu Admin HO', manager_approved:'Disetujui Manager', approved:'Disetujui HO',
    purchasing:'Proses Purchasing', completed:'Selesai', rejected:'Ditolak',
  }

  // 8 kolom: # | PartNo | Nama | KodeUnit | TipeUnit | Qty | Satuan | Keterangan
  // Info: 3 kiri | gap | 4 kanan = total 8
  const N = 8

  const colgroup = `<colgroup>
  <col style="width:35px"/><col style="width:110px"/><col style="width:215px"/>
  <col style="width:85px"/><col style="width:85px"/><col style="width:50px"/>
  <col style="width:65px"/><col style="width:175px"/>
</colgroup>`

  const itemRows = (pm.items||[]).map((item,i) => {
    const e = i%2===1
    return row(
      cell(i+1,                                           e?s.tdCe:s.tdC),
      cell(item.part_number||item.item?.part_number||'-', e?s.tdMe:s.tdM),
      cell(item.nama_barang||'-',                         e?s.tdBe:s.tdB),
      cell(item.kode_unit||'-',                           e?s.tdCe:s.tdC),
      cell(item.tipe_unit||'-',                           e?s.tdCe:s.tdC),
      cell(item.qty,                                      e?s.tdCe:s.tdC),
      cell(item.satuan,                                   e?s.tdCe:s.tdC),
      cell(item.keterangan||'-',                          e?s.tde :s.td),
    )
  }).join('')

  // Info: kolom kiri 3, kolom kanan 4 (1 gap di tengah)
  const infoRows = [
    ['No. PM',        pm.nomor,                      'Tgl. Dibutuhkan', pm.needed_date ? fmtDate(pm.needed_date) : '-'],
    ['Gudang / Site', pm.warehouse?.name||'-',        'Chief Mekanik',   pm.chiefAuthorizer?.name||'-'],
    ['Diajukan Oleh', pm.requester?.name||'-',        'Manager',         pm.managerApprover?.name||'-'],
    ['Tanggal Dibuat',fmtDate(pm.created_at),         'Admin HO',        pm.approver?.name||'-'],
  ].map(([l1,v1,l2,v2]) => row(
    cell(l1,s.lbl), cell(v1,s.val2,'colspan="2"'),
    gap(),
    cell(l2,s.lbl,'colspan="2"'), cell(v2,s.val2,'colspan="2"'),
  )).join('')

  const body = [
    row(cell('PT. CIPTA SARANA MAKMUR', s.h1, `colspan="${N}"`)),
    row(cell(`${isPart?'MATERIAL REQUEST PART':'MATERIAL REQUEST OFFICE'}  ·  ${pm.nomor}  ·  ${(stMap[pm.status]||'').toUpperCase()}`, s.h2, `colspan="${N}"`)),
    spacer(N),
    row(cell('INFORMASI PERMINTAAN',s.sec,'colspan="3"'), gap(), cell('PERSETUJUAN',s.sec,'colspan="4"')),
    infoRows,
    spacer(N),
    row(cell('#',s.th), cell('Part Number',s.th), cell('Nama Barang / Deskripsi',s.thL), cell('Kode Unit',s.th), cell('Tipe Unit',s.th), cell('Qty',s.th), cell('Satuan',s.th), cell('Keterangan',s.thL)),
    itemRows,
    pm.notes ? [spacer(N), row(cell(`Catatan: ${pm.notes}`,s.note,`colspan="${N}"`))] : [],
    spacer(N),
    row(cell('ORDERED BY LOGISTIC',s.signH,'colspan="2"'), gap(), cell('RECEIVED BY PURCHASING',s.signH,'colspan="2"'), gap(), cell('AUTHORIZED BY',s.signH), gap(), cell('APPROVED BY',s.signH)),
    `<tr style="height:55px;">${cell('',s.signB,'colspan="2"')}${gap()}${cell('',s.signB,'colspan="2"')}${gap()}${cell(pm.chiefAuthorizer?.name||'',s.signB)}${gap()}${cell(pm.approver?.name||pm.managerApprover?.name||'',s.signB)}</tr>`,
  ].flat().join('\n')

  download(xlsWrap('Permintaan Material', colgroup, body), `PM-${pm.nomor}.xls`)
  toast?.success(`✅ PM-${pm.nomor}.xls berhasil diunduh`)
}