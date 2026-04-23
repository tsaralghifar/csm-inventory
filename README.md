# CSM Inventory System
### Sistem Manajemen Inventori Sparepart Alat Berat
CSM Inventory System adalah aplikasi berbasis web yang mengintegrasikan:

Manajemen Stok
Sistem Akuntansi (Jurnal, Kas Besar & Kecil)
Payroll Karyawan
Supplier & Invoice Management

Sistem ini dirancang untuk membantu operasional bisnis secara terpusat dalam satu platform.

---

## 🚀 Cara Setup & Instalasi

### Requirements
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & NPM
- PostgreSQL 14+
- Git

### Langkah Instalasi

```bash
# 1. Clone/extract project
cd /var/www/csm-inventory

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Copy env file
cp .env.example .env

# 5. Generate app key
php artisan key:generate

# 6. Setup database PostgreSQL
# Buat database: csm_inventory di PostgreSQL
# Edit .env dengan credentials database Anda

# 7. Jalankan migrations
php artisan migrate

# 8. Jalankan seeder (membuat superuser + data awal)
php artisan db:seed

# 9. Build frontend
npm run build
# Atau untuk development:
npm run dev

# 10. Jalankan server
php artisan serve
```

### Konfigurasi .env (Database PostgreSQL)
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=csm_inventory
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

---

## 👤 Default Login

| Role | Email | Password |
|------|-------|----------|
| Superuser | superuser@csm.co.id | superuser123 |
| Admin HO | admin.ho@csm.co.id | admin123 |

---

## 📁 Struktur Modul

  📦 Inventory
    Manajemen stok barang
    Tracking permintaan material
    Import saldo awal
  💰 Accounting
    Jurnal umum
    Kas besar & kas kecil
    Laporan keuangan (Cash Flow, Beban, dll)
  👨‍💼 Payroll
    Penggajian karyawan
    Komponen gaji
    Pinjaman karyawan
  🧾 Supplier
    Manajemen supplier
    Invoice & pembayaran

---

## 🛠 Tech Stack
- **Backend**: Laravel 11 + Spatie Permission + Sanctum
- **Frontend**: Vue.js 3 + Bootstrap 5 + Pinia
- **Database**: PostgreSQL 16
- **Export**: CSV/Excel built-in, PDF via DomPDF
