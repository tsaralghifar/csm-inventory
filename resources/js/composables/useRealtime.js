/**
 * useRealtime.js — resources/js/composables/useRealtime.js
 * Composable real-time untuk semua halaman (kecuali Users & Roles).
 * Menggunakan Laravel Reverb via Laravel Echo.
 */

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Singleton Echo instance
let echoInstance = null

function getEcho() {
  if (echoInstance) return echoInstance
  window.Pusher = Pusher
  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
      headers: {
        Authorization: 'Bearer ' + localStorage.getItem('csm_token'),
        Accept: 'application/json',
      },
    },
  })
  return echoInstance
}

function listen(channel, event, callback) {
  const ch = getEcho().channel(channel)
  // Hapus listener lama dulu sebelum pasang yang baru,
  // mencegah listener menumpuk saat navigasi bolak-balik
  ch.stopListening(event)
  ch.listen(event, callback)
}

function leave(channel) {
  try { getEcho().leaveChannel(channel) } catch {}
}

export function useRealtime() {

  // ── Transfer Barang ──────────────────────
  const listenTransfer    = (cb) => listen('transfer-barang',    '.transfer.updated', cb)
  const stopTransfer      = ()   => leave('transfer-barang')

  // ── Material Request ─────────────────────
  const listenMR          = (cb) => listen('material-request',   '.mr.updated', cb)
  const stopMR            = ()   => leave('material-request')

  // ── Permintaan Material ──────────────────
  const listenPM          = (cb) => listen('permintaan-material', '.pm.updated', cb)
  const stopPM            = ()   => leave('permintaan-material')

  // ── Purchase Order ───────────────────────
  const listenPO          = (cb) => listen('purchase-order',     '.po.updated', cb)
  const stopPO            = ()   => leave('purchase-order')

  // ── Bon Pengeluaran ──────────────────────
  const listenBon         = (cb) => listen('bon-pengeluaran',    '.bon.updated', cb)
  const stopBon           = ()   => leave('bon-pengeluaran')

  // ── Surat Jalan / Tanda Terima ───────────
  const listenSJ          = (cb) => listen('surat-jalan',        '.sj.updated', cb)
  const stopSJ            = ()   => leave('surat-jalan')

  // ── Stok (semua gudang) ──────────────────
  const listenStok        = (cb) => listen('stok',               '.stok.updated', cb)
  const stopStok          = ()   => leave('stok')

  // ── Stok (per gudang) ────────────────────
  const listenStokGudang  = (warehouseId, cb) => listen(`stok.${warehouseId}`, '.stok.updated', cb)
  const stopStokGudang    = (warehouseId)      => leave(`stok.${warehouseId}`)

  // ── Barang / Master Data ─────────────────
  const listenMaster      = (cb) => listen('master-data',        '.master.updated', cb)
  const stopMaster        = ()   => leave('master-data')

  // ── Master Data per tipe ──────────────────────────────────
  const listenGudang      = (cb) => listen('master-gudang',      '.master.updated', cb)
  const stopGudang        = ()   => leave('master-gudang')

  const listenKategori    = (cb) => listen('master-kategori',    '.master.updated', cb)
  const stopKategori      = ()   => leave('master-kategori')

  const listenUnit        = (cb) => listen('master-unit',        '.master.updated', cb)
  const stopUnit          = ()   => leave('master-unit')

  const listenKaryawan    = (cb) => listen('master-karyawan',    '.master.updated', cb)
  const stopKaryawan      = ()   => leave('master-karyawan')

  // ── BBM / Fuel ───────────────────────────
  const listenFuel        = (cb) => listen('fuel-log',           '.fuel.updated', cb)
  const stopFuel          = ()   => leave('fuel-log')

  // ── APD ──────────────────────────────────
  const listenAPD         = (cb) => listen('apd',                '.apd.updated', cb)
  const stopAPD           = ()   => leave('apd')

  // ── Retur Barang ─────────────────────────
  const listenRetur       = (cb) => listen('retur-barang',       '.retur.updated', cb)
  const stopRetur         = ()   => leave('retur-barang')

  // ── Users (Admin) ─────────────────────────
  const listenUsers       = (cb) => listen('admin-users',        '.users.updated', cb)
  const stopUsers         = ()   => leave('admin-users')

  // ── Accounting ────────────────────────────
  const listenAccounting        = (cb) => listen('accounting',          '.accounting.updated', cb)
  const stopAccounting          = ()   => leave('accounting')

  const listenSupplier          = (cb) => listen('accounting-supplier', '.accounting.updated', cb)
  const stopSupplier            = ()   => leave('accounting-supplier')

  const listenInvoiceSupplier   = (cb) => listen('accounting-invoice',  '.accounting.updated', cb)
  const stopInvoiceSupplier     = ()   => leave('accounting-invoice')

  const listenPembayaranSupplier= (cb) => listen('accounting-payment',  '.accounting.updated', cb)
  const stopPembayaranSupplier  = ()   => leave('accounting-payment')

  const listenKasBesar          = (cb) => listen('accounting-kas-besar','.accounting.updated', cb)
  const stopKasBesar            = ()   => leave('accounting-kas-besar')

  const listenKasKecil          = (cb) => listen('accounting-kas-kecil','.accounting.updated', cb)
  const stopKasKecil            = ()   => leave('accounting-kas-kecil')

  const listenJurnal            = (cb) => listen('accounting-jurnal',   '.accounting.updated', cb)
  const stopJurnal              = ()   => leave('accounting-jurnal')

  // ── Payroll ───────────────────────────────
  const listenPayroll           = (cb) => listen('payroll',             '.payroll.updated', cb)
  const stopPayroll             = ()   => leave('payroll')

  const listenKomponenGaji      = (cb) => listen('payroll-komponen',    '.payroll.updated', cb)
  const stopKomponenGaji        = ()   => leave('payroll-komponen')

  const listenPinjamanKaryawan  = (cb) => listen('payroll-pinjaman',    '.payroll.updated', cb)
  const stopPinjamanKaryawan    = ()   => leave('payroll-pinjaman')

  return {
    listenTransfer,  stopTransfer,  stopListenTransfer: stopTransfer, // alias (backward compat)
    listenMR,        stopMR,
    listenPM,        stopPM,
    listenPO,        stopPO,
    listenBon,       stopBon,
    listenSJ,        stopSJ,
    listenStok,      stopStok,
    listenStokGudang, stopStokGudang,
    listenMaster,    stopMaster,
    listenGudang,    stopGudang,
    listenKategori,  stopKategori,
    listenUnit,      stopUnit,
    listenKaryawan,  stopKaryawan,
    listenFuel,      stopFuel,
    listenAPD,       stopAPD,
    listenRetur,     stopRetur,
    listenUsers,     stopUsers,
    // Accounting
    listenAccounting,         stopAccounting,
    listenSupplier,           stopSupplier,
    listenInvoiceSupplier,    stopInvoiceSupplier,
    listenPembayaranSupplier, stopPembayaranSupplier,
    listenKasBesar,           stopKasBesar,
    listenKasKecil,           stopKasKecil,
    listenJurnal,             stopJurnal,
    // Payroll
    listenPayroll,            stopPayroll,
    listenKomponenGaji,       stopKomponenGaji,
    listenPinjamanKaryawan,   stopPinjamanKaryawan,
  }
}