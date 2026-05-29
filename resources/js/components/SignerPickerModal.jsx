// ══════════════════════════════════════════════════════════════════
// SignerPickerModal.jsx
// Modal "Pilih Penandatangan" yang muncul sebelum user export PDF laporan.
//
// Props:
//   open          — boolean, tampil/sembunyi modal
//   onClose       — fn(), tutup modal tanpa export
//   onExport      — fn(signerIds: number[]), trigger download PDF
//   maxSigners    — jumlah max penandatangan (default: 3)
//   title         — judul modal (default: "Pilih Penandatangan")
//
// Contoh pemakaian di halaman laporan stok:
//   <SignerPickerModal
//     open={showSignerModal}
//     onClose={() => setShowSignerModal(false)}
//     onExport={(ids) => downloadStockPdf({ signer_ids: ids })}
//   />
// ══════════════════════════════════════════════════════════════════

import { useState, useEffect, useCallback } from "react";
import axios from "@/lib/axios";          // sesuaikan path axios instance Anda
import { X, FileDown, UserCheck, AlertTriangle, Loader2 } from "lucide-react";

const LABEL_COLORS = [
  "bg-blue-100 text-blue-800 border-blue-200",
  "bg-purple-100 text-purple-800 border-purple-200",
  "bg-green-100 text-green-800 border-green-200",
];

const SLOT_LABELS = ["Dibuat oleh", "Diperiksa oleh", "Disetujui oleh"];

export default function SignerPickerModal({
  open,
  onClose,
  onExport,
  maxSigners = 3,
  title = "Pilih Penandatangan",
}) {
  const [users, setUsers]       = useState([]);
  const [loading, setLoading]   = useState(false);
  const [exporting, setExporting] = useState(false);
  const [selected, setSelected] = useState([]); // array of user objects, max maxSigners

  // ── Fetch daftar user yang sudah punya TTD ─────────────────────
  const fetchSignableUsers = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await axios.get("/api/users/signable");
      setUsers(data.data ?? []);
    } catch {
      setUsers([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (open) {
      fetchSignableUsers();
      setSelected([]); // reset pilihan setiap kali modal dibuka
    }
  }, [open, fetchSignableUsers]);

  // ── Toggle pilih user ─────────────────────────────────────────
  const toggle = (user) => {
    setSelected((prev) => {
      const exists = prev.find((u) => u.id === user.id);
      if (exists) return prev.filter((u) => u.id !== user.id);
      if (prev.length >= maxSigners) return prev; // sudah penuh
      return [...prev, user];
    });
  };

  const moveUp = (idx) => {
    if (idx === 0) return;
    setSelected((prev) => {
      const arr = [...prev];
      [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
      return arr;
    });
  };

  const moveDown = (idx) => {
    setSelected((prev) => {
      if (idx === prev.length - 1) return prev;
      const arr = [...prev];
      [arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]];
      return arr;
    });
  };

  const removeSelected = (id) => {
    setSelected((prev) => prev.filter((u) => u.id !== id));
  };

  // ── Export ────────────────────────────────────────────────────
  const handleExport = async () => {
    setExporting(true);
    try {
      await onExport(selected.map((u) => u.id));
      onClose();
    } finally {
      setExporting(false);
    }
  };

  // ── Keyboard close ────────────────────────────────────────────
  useEffect(() => {
    const handler = (e) => { if (e.key === "Escape") onClose(); };
    if (open) window.addEventListener("keydown", handler);
    return () => window.removeEventListener("keydown", handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Panel */}
      <div className="relative z-10 bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <div className="flex items-center gap-2">
            <UserCheck className="w-5 h-5 text-blue-600" />
            <h2 className="text-base font-semibold text-gray-800">{title}</h2>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 rounded-lg p-1 hover:bg-gray-100"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Slot yang dipilih */}
        <div className="px-5 pt-4 pb-2">
          <p className="text-xs text-gray-500 mb-2">
            Pilih hingga <strong>{maxSigners}</strong> penandatangan. Urutan menentukan posisi di PDF.
          </p>
          <div className="space-y-1.5 min-h-[80px]">
            {selected.length === 0 && (
              <div className="text-xs text-gray-400 italic py-3 text-center border border-dashed border-gray-200 rounded-lg">
                Belum ada penandatangan dipilih — PDF akan menggunakan placeholder kosong
              </div>
            )}
            {selected.map((u, idx) => (
              <div
                key={u.id}
                className={`flex items-center gap-2 px-3 py-2 rounded-lg border text-xs ${LABEL_COLORS[idx] ?? "bg-gray-100 text-gray-700 border-gray-200"}`}
              >
                <span className="font-semibold shrink-0 w-24">{SLOT_LABELS[idx] ?? `Slot ${idx + 1}`}</span>
                <span className="flex-1 font-medium truncate">{u.name}</span>
                <span className="text-gray-500 truncate hidden sm:block">{u.position}</span>
                {/* Reorder buttons */}
                <div className="flex gap-1">
                  <button onClick={() => moveUp(idx)} disabled={idx === 0} className="text-gray-400 hover:text-gray-700 disabled:opacity-30">↑</button>
                  <button onClick={() => moveDown(idx)} disabled={idx === selected.length - 1} className="text-gray-400 hover:text-gray-700 disabled:opacity-30">↓</button>
                </div>
                <button onClick={() => removeSelected(u.id)} className="text-red-400 hover:text-red-600">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Daftar user tersedia */}
        <div className="px-5 pb-2">
          <p className="text-xs font-medium text-gray-500 mb-1.5 uppercase tracking-wide">
            User dengan tanda tangan tersedia
          </p>
          {loading ? (
            <div className="flex items-center justify-center py-6 text-gray-400">
              <Loader2 className="w-5 h-5 animate-spin mr-2" />
              <span className="text-sm">Memuat daftar user...</span>
            </div>
          ) : users.length === 0 ? (
            <div className="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700">
              <AlertTriangle className="w-4 h-4 shrink-0" />
              Belum ada user yang mengupload tanda tangan. Minta tiap user upload TTD di halaman Profil.
            </div>
          ) : (
            <div className="max-h-52 overflow-y-auto space-y-1 border border-gray-100 rounded-lg p-1">
              {users.map((u) => {
                const isSelected  = !!selected.find((s) => s.id === u.id);
                const slotIndex   = selected.findIndex((s) => s.id === u.id);
                const isDisabled  = !isSelected && selected.length >= maxSigners;

                return (
                  <button
                    key={u.id}
                    onClick={() => toggle(u)}
                    disabled={isDisabled}
                    className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left text-xs transition-colors
                      ${isSelected
                        ? "bg-blue-50 border border-blue-200"
                        : isDisabled
                          ? "opacity-40 cursor-not-allowed bg-gray-50"
                          : "hover:bg-gray-50 border border-transparent"
                      }`}
                  >
                    {/* Avatar inisial */}
                    <div className={`w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0
                      ${isSelected ? LABEL_COLORS[slotIndex] ?? "bg-blue-100 text-blue-700" : "bg-gray-100 text-gray-600"}`}>
                      {u.name?.charAt(0)?.toUpperCase()}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="font-medium text-gray-800 truncate">{u.name}</div>
                      <div className="text-gray-500 truncate">{u.position ?? u.role}</div>
                    </div>
                    {u.warehouse && (
                      <span className="text-gray-400 shrink-0 hidden sm:block">{u.warehouse}</span>
                    )}
                    {isSelected && (
                      <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded border shrink-0 ${LABEL_COLORS[slotIndex] ?? ""}`}>
                        Slot {slotIndex + 1}
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex items-center justify-between px-5 py-4 border-t border-gray-100 bg-gray-50">
          <span className="text-xs text-gray-400">
            {selected.length}/{maxSigners} penandatangan dipilih
          </span>
          <div className="flex gap-2">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-100"
            >
              Batal
            </button>
            <button
              onClick={handleExport}
              disabled={exporting}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-60"
            >
              {exporting
                ? <Loader2 className="w-4 h-4 animate-spin" />
                : <FileDown className="w-4 h-4" />
              }
              {exporting ? "Mengekspor..." : "Export PDF"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
