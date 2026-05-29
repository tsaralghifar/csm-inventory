// ══════════════════════════════════════════════════════════════════
// SignatureUploader.jsx
// Komponen upload tanda tangan di halaman Profil user.
//
// Props:
//   currentSignature  — string | null  (data URI dari API /profile)
//   onSaved           — fn(newDataUri | null) dipanggil setelah berhasil simpan/hapus
//
// Contoh pemakaian:
//   <SignatureUploader
//     currentSignature={user.signature_preview}
//     onSaved={(uri) => setUser({ ...user, signature_preview: uri })}
//   />
// ══════════════════════════════════════════════════════════════════

import { useState, useRef, useEffect } from "react";
import axios from "@/lib/axios";
import { Upload, Trash2, CheckCircle, AlertTriangle, Loader2, ZoomIn } from "lucide-react";

const MAX_SIZE_MB = 2;

export default function SignatureUploader({ currentSignature, onSaved }) {
  const [preview, setPreview]     = useState(currentSignature ?? null);
  const [uploading, setUploading] = useState(false);
  const [deleting, setDeleting]   = useState(false);
  const [error, setError]         = useState(null);
  const [success, setSuccess]     = useState(false);
  const [zoom, setZoom]           = useState(false);
  const inputRef                  = useRef(null);

  // ── Sync preview jika prop currentSignature berubah dari luar ──
  // (misal: parent refetch profil setelah aksi lain)
  useEffect(() => {
    setPreview(currentSignature ?? null);
  }, [currentSignature]);

  const clearFeedback = () => { setError(null); setSuccess(false); };

  // ── Validasi file sebelum upload ──────────────────────────────
  const validateFile = (file) => {
    if (!["image/png", "image/jpeg", "image/jpg"].includes(file.type)) {
      return "Hanya file PNG atau JPG yang diizinkan.";
    }
    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
      return `Ukuran file maksimal ${MAX_SIZE_MB}MB.`;
    }
    return null;
  };

  // ── Handle pilih file ─────────────────────────────────────────
  const handleFileChange = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    clearFeedback();
    const errMsg = validateFile(file);
    if (errMsg) { setError(errMsg); return; }

    // Tampilkan preview lokal sebelum upload selesai
    const reader = new FileReader();
    reader.onload = (ev) => setPreview(ev.target.result);
    reader.readAsDataURL(file);

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append("signature_file", file);

      const { data } = await axios.post("/api/users/signature", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      // Gunakan URL dari server (bukan preview lokal) sebagai source of truth
      setPreview(data.data.preview_url);
      onSaved?.(data.data.preview_url);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message ?? "Gagal mengupload tanda tangan.");
      // Kembalikan ke state server (bukan currentSignature prop yang mungkin stale)
      setPreview(currentSignature ?? null);
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  };

  // ── Handle hapus ──────────────────────────────────────────────
  const handleDelete = async () => {
    if (!window.confirm(
      "Hapus tanda tangan? Tanda tangan tidak akan muncul di PDF sampai Anda upload ulang."
    )) return;

    clearFeedback();
    setDeleting(true);
    try {
      await axios.delete("/api/users/signature");
      setPreview(null);
      onSaved?.(null);
    } catch (err) {
      setError(err.response?.data?.message ?? "Gagal menghapus tanda tangan.");
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-3">

      {/* Label & keterangan */}
      <div>
        <h3 className="text-sm font-semibold text-gray-700">Tanda Tangan Digital</h3>
        <p className="text-xs text-gray-500 mt-0.5">
          Upload foto/scan tanda tangan Anda (PNG/JPG, maks. {MAX_SIZE_MB}MB).
          Tanda tangan ini akan otomatis muncul di PDF saat Anda dipilih sebagai penandatangan.
        </p>
      </div>

      {/* Preview area */}
      <div
        className={`relative border-2 border-dashed rounded-xl overflow-hidden transition-colors
          ${preview ? "border-green-200 bg-green-50" : "border-gray-200 bg-gray-50"}`}
        style={{ minHeight: "100px" }}
      >
        {preview ? (
          <>
            <img
              src={preview}
              alt="Preview tanda tangan"
              className="mx-auto block max-h-24 object-contain py-3 px-4 cursor-zoom-in"
              onClick={() => setZoom(true)}
            />
            <button
              onClick={() => setZoom(true)}
              className="absolute top-2 right-2 bg-white/80 rounded-full p-1 text-gray-500 hover:text-gray-800 shadow"
              title="Lihat lebih besar"
            >
              <ZoomIn className="w-3.5 h-3.5" />
            </button>
          </>
        ) : (
          <div className="flex flex-col items-center justify-center py-8 text-gray-400 gap-1">
            <Upload className="w-8 h-8" />
            <span className="text-xs">Belum ada tanda tangan</span>
          </div>
        )}
      </div>

      {/* Lightbox zoom */}
      {zoom && preview && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm"
          onClick={() => setZoom(false)}
        >
          <img
            src={preview}
            alt="Tanda tangan"
            className="max-h-[80vh] max-w-[90vw] object-contain rounded-xl shadow-2xl bg-white p-4"
          />
        </div>
      )}

      {/* Feedback */}
      {error && (
        <div className="flex items-center gap-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          <AlertTriangle className="w-4 h-4 shrink-0" />
          {error}
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
          <CheckCircle className="w-4 h-4 shrink-0" />
          Tanda tangan berhasil disimpan!
        </div>
      )}

      {/* Tombol aksi */}
      <div className="flex gap-2">
        <label
          className={`flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg cursor-pointer transition-colors
            ${uploading
              ? "bg-blue-400 text-white cursor-not-allowed"
              : "bg-blue-600 text-white hover:bg-blue-700"
            }`}
        >
          {uploading
            ? <Loader2 className="w-4 h-4 animate-spin" />
            : <Upload className="w-4 h-4" />
          }
          {uploading ? "Mengupload..." : preview ? "Ganti Tanda Tangan" : "Upload Tanda Tangan"}
          <input
            ref={inputRef}
            type="file"
            accept="image/png,image/jpeg"
            className="hidden"
            onChange={handleFileChange}
            disabled={uploading}
          />
        </label>

        {preview && (
          <button
            onClick={handleDelete}
            disabled={deleting}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 disabled:opacity-60"
          >
            {deleting
              ? <Loader2 className="w-4 h-4 animate-spin" />
              : <Trash2 className="w-4 h-4" />
            }
            {deleting ? "Menghapus..." : "Hapus"}
          </button>
        )}
      </div>

      {/* Tips */}
      <div className="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
        <p className="text-xs text-blue-700 font-medium mb-0.5">Tips agar hasil terbaik:</p>
        <ul className="text-xs text-blue-600 space-y-0.5 list-disc list-inside">
          <li>Tanda tangan di kertas putih, foto dari atas lurus</li>
          <li>Crop rapat, sisakan sedikit ruang putih di tepi</li>
          <li>Simpan sebagai PNG agar latar transparan (opsional)</li>
          <li>Resolusi minimal 200×80 px untuk hasil cetakan tajam</li>
        </ul>
      </div>
    </div>
  );
}
