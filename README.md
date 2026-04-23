# CSM Inventory System
### Sistem Manajemen Inventori Sparepart Alat Berat
**PT. Cipta Sarana Makmur**

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

- **Dashboard** - KPI stok, alert minus, aktivitas terbaru
- **Master Barang** - CRUD sparepart dengan part number
- **Master Gudang** - HO + tambah site baru
- **Stok HO** - Stok masuk, keluar, opname di gudang HO
- **Stok Site** - View stok per site operasional
- **Material Request** - Alur permintaan barang HO→Site
- **Histori Mutasi** - Tracking semua pergerakan barang
- **BBM/Solar** - Log pengeluaran bahan bakar per unit
- **APD Karyawan** - Distribusi alat pelindung diri
- **Laporan** - Export Excel/CSV laporan operasional
- **Manajemen User** - CRUD user dengan multi-role
- **Role & Akses** - Superuser atur permission per role

---

## 🛠 Tech Stack
- **Backend**: Laravel 11 + Spatie Permission + Sanctum
- **Frontend**: Vue.js 3 + Bootstrap 5 + Pinia
- **Database**: PostgreSQL 16
- **Export**: CSV/Excel built-in, PDF via DomPDF
