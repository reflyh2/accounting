/**
 * Help Documentation Content
 * Comprehensive operational flow documentation for the accounting application
 */

export const helpSections = [
    {
        id: 'getting-started',
        title: 'Memulai',
        icon: 'RocketLaunchIcon',
        color: 'from-blue-500 to-indigo-600',
        content: [
            {
                title: 'Selamat Datang',
                description: 'Panduan untuk membantu Anda memulai menggunakan aplikasi akuntansi ini.',
            },
            {
                title: 'Langkah Awal',
                steps: [
                    {
                        step: 1,
                        title: 'Lengkapi Pengaturan Perusahaan',
                        description: 'Buka menu Pengaturan → Perusahaan untuk mengisi informasi perusahaan seperti nama, alamat, NPWP, dan logo.',
                    },
                    {
                        step: 2,
                        title: 'Tambahkan Cabang/Lokasi',
                        description: 'Jika perusahaan Anda memiliki beberapa cabang, tambahkan melalui menu Pengaturan → Perusahaan → Cabang.',
                    },
                    {
                        step: 3,
                        title: 'Atur Bagan Akun',
                        description: 'Periksa dan sesuaikan bagan akun (Chart of Accounts) di menu Akuntansi & Keuangan → Bagan Akun sesuai kebutuhan bisnis.',
                    },
                    {
                        step: 4,
                        title: 'Tambahkan Partner Bisnis',
                        description: 'Daftarkan pelanggan dan supplier di menu Pengaturan → Partner Bisnis.',
                    },
                    {
                        step: 5,
                        title: 'Siapkan Katalog Produk',
                        description: 'Buat kategori dan produk di menu Produk → Kategori Produk dan Katalog Produk.',
                    },
                ],
            },
            {
                title: 'Tips Sukses',
                tips: [
                    'Gunakan fitur onboarding tour saat pertama kali login untuk mengenal aplikasi.',
                    'Pastikan rekening bank perusahaan sudah terdaftar untuk pencatatan kas & bank.',
                    'Atur hak akses pengguna sesuai perannya dalam perusahaan.',
                ],
            },
        ],
    },
    {
        id: 'sales-flow',
        title: 'Alur Penjualan',
        icon: 'CurrencyDollarIcon',
        color: 'from-green-500 to-emerald-600',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Alur penjualan mencakup seluruh proses dari pesanan pelanggan hingga penerimaan pembayaran.',
                flowDiagram: 'Sales Order → Pengiriman → Faktur Penjualan → Pembayaran',
            },
            {
                title: 'Membuat Sales Order',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Penjualan → Sales Order',
                        description: 'Klik tombol "Buat Baru" untuk memulai pesanan penjualan.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Pelanggan',
                        description: 'Pilih pelanggan dari daftar atau tambahkan pelanggan baru jika belum terdaftar.',
                    },
                    {
                        step: 3,
                        title: 'Tambahkan Produk',
                        description: 'Pilih produk, tentukan kuantitas dan harga. Sistem akan menghitung total otomatis.',
                    },
                    {
                        step: 4,
                        title: 'Simpan dan Konfirmasi',
                        description: 'Simpan sebagai draft atau langsung konfirmasi untuk memproses pesanan.',
                    },
                ],
            },
            {
                title: 'Membuat Pengiriman (Delivery Order)',
                steps: [
                    {
                        step: 1,
                        title: 'Dari Sales Order yang Sudah Dikonfirmasi',
                        description: 'Buka Sales Order yang sudah dikonfirmasi, klik tombol "Buat Pengiriman".',
                    },
                    {
                        step: 2,
                        title: 'Atau Buat Manual',
                        description: 'Buka menu Penjualan → Pengiriman Penjualan, pilih Sales Order yang akan dikirim.',
                    },
                    {
                        step: 3,
                        title: 'Isi Detail Pengiriman',
                        description: 'Tentukan kuantitas yang dikirim, tanggal pengiriman, dan nomor referensi pengiriman.',
                    },
                    {
                        step: 4,
                        title: 'Konfirmasi Pengiriman',
                        description: 'Konfirmasi pengiriman untuk mengurangi stok dan mencatat pengeluaran barang.',
                    },
                ],
            },
            {
                title: 'Membuat Faktur Penjualan',
                steps: [
                    {
                        step: 1,
                        title: 'Dari Pengiriman atau Sales Order',
                        description: 'Klik tombol "Buat Faktur" dari Pengiriman atau Sales Order yang sudah selesai.',
                    },
                    {
                        step: 2,
                        title: 'Periksa Detail Faktur',
                        description: 'Pastikan harga, pajak, dan jumlah sudah benar sebelum menyimpan.',
                    },
                    {
                        step: 3,
                        title: 'Posting Faktur',
                        description: 'Posting faktur untuk mencatat piutang dan pendapatan dalam jurnal akuntansi.',
                    },
                ],
            },
            {
                title: 'Menerima Pembayaran',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Akuntansi → Hutang/Piutang',
                        description: 'Pilih tab Piutang untuk melihat daftar faktur yang belum dibayar.',
                    },
                    {
                        step: 2,
                        title: 'Catat Pembayaran',
                        description: 'Pilih faktur dan klik "Catat Pembayaran", masukkan jumlah dan metode pembayaran.',
                    },
                    {
                        step: 3,
                        title: 'Konfirmasi',
                        description: 'Sistem akan otomatis membuat jurnal kas/bank masuk dan mengurangi piutang.',
                    },
                ],
            },
            {
                title: 'Retur Penjualan',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Retur Penjualan',
                        description: 'Akses menu Penjualan → Retur Penjualan.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Faktur Terkait',
                        description: 'Pilih faktur penjualan yang diretur.',
                    },
                    {
                        step: 3,
                        title: 'Tentukan Item Retur',
                        description: 'Pilih produk dan kuantitas yang dikembalikan.',
                    },
                    {
                        step: 4,
                        title: 'Proses Retur',
                        description: 'Konfirmasi retur untuk menambah stok dan mengurangi piutang.',
                    },
                ],
            },
        ],
    },
    {
        id: 'purchase-flow',
        title: 'Alur Pembelian',
        icon: 'ShoppingCartIcon',
        color: 'from-orange-500 to-amber-600',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Alur pembelian mencakup perencanaan kebutuhan hingga pembayaran ke supplier.',
                flowDiagram: 'Rencana Pembelian → Purchase Order → Penerimaan Barang → Faktur Pembelian → Pembayaran',
            },
            {
                title: 'Membuat Rencana Pembelian (Opsional)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pembelian → Rencana Pembelian',
                        description: 'Gunakan untuk merencanakan kebutuhan pembelian berdasarkan stok minimum atau kebutuhan produksi.',
                    },
                    {
                        step: 2,
                        title: 'Tambahkan Item',
                        description: 'Pilih produk yang perlu dibeli dan tentukan kuantitas yang dibutuhkan.',
                    },
                    {
                        step: 3,
                        title: 'Konversi ke PO',
                        description: 'Setelah rencana disetujui, konversi ke Purchase Order.',
                    },
                ],
            },
            {
                title: 'Membuat Purchase Order',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pembelian → Purchase Orders',
                        description: 'Klik "Buat Baru" untuk membuat pesanan pembelian.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Supplier',
                        description: 'Pilih supplier dari daftar partner yang sudah terdaftar.',
                    },
                    {
                        step: 3,
                        title: 'Tambahkan Produk',
                        description: 'Pilih produk, masukkan kuantitas dan harga beli per unit.',
                    },
                    {
                        step: 4,
                        title: 'Simpan dan Kirim ke Supplier',
                        description: 'Simpan PO dan cetak untuk dikirim ke supplier.',
                    },
                ],
            },
            {
                title: 'Menerima Barang (Goods Receipt)',
                steps: [
                    {
                        step: 1,
                        title: 'Dari PO yang Sudah Dikirim',
                        description: 'Buka PO dan klik "Buat Penerimaan" saat barang tiba.',
                    },
                    {
                        step: 2,
                        title: 'Verifikasi Kuantitas',
                        description: 'Cocokkan kuantitas yang diterima dengan surat jalan supplier.',
                    },
                    {
                        step: 3,
                        title: 'Konfirmasi Penerimaan',
                        description: 'Konfirmasi untuk menambah stok di gudang yang dipilih.',
                    },
                ],
                note: 'Penerimaan parsial diperbolehkan jika supplier mengirim bertahap.',
            },
            {
                title: 'Membuat Faktur Pembelian',
                steps: [
                    {
                        step: 1,
                        title: 'Dari Penerimaan atau PO',
                        description: 'Klik "Buat Faktur" dari dokumen penerimaan atau PO.',
                    },
                    {
                        step: 2,
                        title: 'Masukkan Nomor Faktur Supplier',
                        description: 'Catat nomor faktur dari supplier untuk referensi.',
                    },
                    {
                        step: 3,
                        title: 'Posting Faktur',
                        description: 'Posting untuk mencatat hutang dagang dalam jurnal.',
                    },
                ],
            },
            {
                title: 'Membayar Supplier',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Akuntansi → Hutang/Piutang',
                        description: 'Pilih tab Hutang untuk melihat faktur yang harus dibayar.',
                    },
                    {
                        step: 2,
                        title: 'Catat Pembayaran',
                        description: 'Pilih faktur, masukkan jumlah pembayaran dan metode.',
                    },
                    {
                        step: 3,
                        title: 'Konfirmasi',
                        description: 'Sistem mencatat kas/bank keluar dan mengurangi hutang.',
                    },
                ],
            },
            {
                title: 'Retur Pembelian',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Retur Pembelian',
                        description: 'Akses menu Pembelian → Retur Pembelian.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Faktur/Penerimaan',
                        description: 'Pilih dokumen terkait barang yang akan dikembalikan.',
                    },
                    {
                        step: 3,
                        title: 'Proses Retur',
                        description: 'Sistem mengurangi stok dan mengurangi hutang.',
                    },
                ],
            },
        ],
    },
    {
        id: 'inventory',
        title: 'Operasi Persediaan',
        icon: 'CubeIcon',
        color: 'from-purple-500 to-violet-600',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Kelola persediaan barang termasuk penerimaan, pengeluaran, transfer, dan penyesuaian stok.',
            },
            {
                title: 'Penerimaan Barang Manual',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Persediaan → Penerimaan Barang',
                        description: 'Gunakan untuk penerimaan yang tidak terkait PO (bonus, hibah, dll).',
                    },
                    {
                        step: 2,
                        title: 'Pilih Lokasi/Gudang',
                        description: 'Tentukan gudang tujuan penyimpanan.',
                    },
                    {
                        step: 3,
                        title: 'Tambahkan Produk',
                        description: 'Pilih produk dan kuantitas yang diterima.',
                    },
                    {
                        step: 4,
                        title: 'Konfirmasi',
                        description: 'Stok bertambah di lokasi yang dipilih.',
                    },
                ],
            },
            {
                title: 'Pengeluaran Barang Manual',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Persediaan → Pengeluaran Barang',
                        description: 'Gunakan untuk pengeluaran non-penjualan (sampel, rusak, dll).',
                    },
                    {
                        step: 2,
                        title: 'Pilih Alasan Pengeluaran',
                        description: 'Tentukan jenis pengeluaran untuk keperluan audit.',
                    },
                    {
                        step: 3,
                        title: 'Konfirmasi',
                        description: 'Stok berkurang dari lokasi yang dipilih.',
                    },
                ],
            },
            {
                title: 'Transfer Antar Lokasi',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Persediaan → Transfer Antar Lokasi',
                        description: 'Gunakan untuk memindahkan barang antar gudang.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Lokasi Asal dan Tujuan',
                        description: 'Tentukan dari dan ke mana barang dipindahkan.',
                    },
                    {
                        step: 3,
                        title: 'Pilih Produk',
                        description: 'Pilih produk dan kuantitas yang ditransfer.',
                    },
                    {
                        step: 4,
                        title: 'Proses Transfer',
                        description: 'Stok berkurang di lokasi asal dan bertambah di lokasi tujuan.',
                    },
                ],
            },
            {
                title: 'Penyesuaian Stok (Stock Adjustment)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Persediaan → Penyesuaian Stok',
                        description: 'Gunakan setelah stock opname atau untuk koreksi.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Lokasi',
                        description: 'Pilih gudang yang akan disesuaikan.',
                    },
                    {
                        step: 3,
                        title: 'Input Hasil Hitung Fisik',
                        description: 'Masukkan kuantitas aktual dari hasil stock opname.',
                    },
                    {
                        step: 4,
                        title: 'Proses Penyesuaian',
                        description: 'Sistem menghitung selisih dan mencatat adjustment.',
                    },
                ],
                note: 'Pastikan memilih akun biaya yang tepat untuk penyesuaian stok.',
            },
        ],
    },
    {
        id: 'manufacturing',
        title: 'Alur Produksi',
        icon: 'PuzzlePieceIcon',
        color: 'from-indigo-500 to-blue-600',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Alur produksi mencakup pembuatan produk dari bahan baku hingga barang jadi.',
                flowDiagram: 'Bill of Materials → Surat Perintah Produksi → Pengeluaran Bahan → Penerimaan Produk Jadi',
            },
            {
                title: 'Membuat Bill of Materials (BOM)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Produksi → Bill of Materials',
                        description: 'BOM mendefinisikan komponen yang dibutuhkan untuk membuat produk.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Produk Jadi',
                        description: 'Pilih produk yang akan diproduksi.',
                    },
                    {
                        step: 3,
                        title: 'Tambahkan Komponen',
                        description: 'Daftarkan bahan baku dan kuantitas per unit produk jadi.',
                    },
                    {
                        step: 4,
                        title: 'Simpan BOM',
                        description: 'BOM siap digunakan untuk work order.',
                    },
                ],
            },
            {
                title: 'Membuat Surat Perintah Produksi (Work Order)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Produksi → Surat Perintah Produksi',
                        description: 'Klik "Buat Baru" untuk membuat work order.',
                    },
                    {
                        step: 2,
                        title: 'Pilih BOM',
                        description: 'Pilih BOM untuk produk yang akan diproduksi.',
                    },
                    {
                        step: 3,
                        title: 'Tentukan Kuantitas',
                        description: 'Masukkan jumlah produk yang akan diproduksi.',
                    },
                    {
                        step: 4,
                        title: 'Konfirmasi Work Order',
                        description: 'Work order siap diproses di lantai produksi.',
                    },
                ],
            },
            {
                title: 'Pengeluaran Bahan Baku',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Produksi → Pengeluaran Bahan Baku',
                        description: 'Mencatat bahan yang dikeluarkan untuk produksi.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Work Order',
                        description: 'Pilih surat perintah produksi terkait.',
                    },
                    {
                        step: 3,
                        title: 'Konfirmasi Pengeluaran',
                        description: 'Stok bahan baku berkurang, biaya masuk ke WIP (Work in Progress).',
                    },
                ],
            },
            {
                title: 'Penerimaan Produk Jadi',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Produksi → Penerimaan Produk Jadi',
                        description: 'Mencatat hasil produksi yang masuk ke gudang.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Work Order',
                        description: 'Pilih surat perintah produksi yang selesai.',
                    },
                    {
                        step: 3,
                        title: 'Input Kuantitas Hasil',
                        description: 'Masukkan jumlah produk jadi yang dihasilkan.',
                    },
                    {
                        step: 4,
                        title: 'Konfirmasi',
                        description: 'Stok produk jadi bertambah, biaya WIP menjadi Finished Goods.',
                    },
                ],
            },
            {
                title: 'Pembuangan Bahan Baku (Scrap)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Produksi → Pembuangan Bahan Baku',
                        description: 'Gunakan untuk mencatat bahan yang rusak/terbuang selama produksi.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Work Order Terkait',
                        description: 'Kaitkan dengan work order yang berjalan.',
                    },
                    {
                        step: 3,
                        title: 'Catat Kuantitas Scrap',
                        description: 'Sistem mencatat kerugian dan mengurangi WIP.',
                    },
                ],
            },
        ],
    },
    {
        id: 'accounting',
        title: 'Akuntansi & Keuangan',
        icon: 'BanknotesIcon',
        color: 'from-teal-500 to-cyan-600',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Modul akuntansi mencakup jurnal, buku besar, dan laporan keuangan.',
            },
            {
                title: 'Membuat Jurnal Manual',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Akuntansi → Jurnal',
                        description: 'Klik "Buat Baru" untuk membuat jurnal umum.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Tipe Jurnal',
                        description: 'Pilih Jurnal Umum, Kas Masuk, atau Kas Keluar.',
                    },
                    {
                        step: 3,
                        title: 'Input Transaksi',
                        description: 'Masukkan akun debet dan kredit dengan jumlah yang balance.',
                    },
                    {
                        step: 4,
                        title: 'Posting',
                        description: 'Posting jurnal untuk mempengaruhi buku besar.',
                    },
                ],
            },
            {
                title: 'Kas Masuk (Cash Receipt)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Akuntansi → Jurnal → Tab Kas Masuk',
                        description: 'Gunakan untuk mencatat penerimaan kas/bank.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Akun Kas/Bank',
                        description: 'Pilih rekening penerima.',
                    },
                    {
                        step: 3,
                        title: 'Pilih Sumber Dana',
                        description: 'Pilih akun pendapatan atau piutang yang dibayar.',
                    },
                    {
                        step: 4,
                        title: 'Posting',
                        description: 'Kas bertambah sesuai jurnal.',
                    },
                ],
            },
            {
                title: 'Kas Keluar (Cash Payment)',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Akuntansi → Jurnal → Tab Kas Keluar',
                        description: 'Gunakan untuk mencatat pengeluaran kas/bank.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Akun Kas/Bank',
                        description: 'Pilih rekening sumber dana.',
                    },
                    {
                        step: 3,
                        title: 'Pilih Tujuan Pembayaran',
                        description: 'Pilih akun biaya atau hutang yang dibayar.',
                    },
                    {
                        step: 4,
                        title: 'Posting',
                        description: 'Kas berkurang sesuai jurnal.',
                    },
                ],
            },
            {
                title: 'Melihat Laporan Keuangan',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Akuntansi → Laporan Akuntansi',
                        description: 'Akses berbagai laporan keuangan.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Jenis Laporan',
                        description: 'Buku Besar, Neraca, Laba Rugi, atau Kas & Bank.',
                    },
                    {
                        step: 3,
                        title: 'Filter Periode',
                        description: 'Tentukan rentang tanggal laporan.',
                    },
                    {
                        step: 4,
                        title: 'Export',
                        description: 'Export ke Excel, PDF, atau cetak langsung.',
                    },
                ],
            },
            {
                title: 'Mengelola Hutang Piutang',
                description: 'Hutang dan piutang secara otomatis tercatat dari faktur pembelian dan penjualan. Pembayaran dicatat melalui modul Hutang/Piutang untuk melunasi saldo.',
                tips: [
                    'Gunakan laporan Aging untuk melihat umur hutang/piutang.',
                    'Pantau jatuh tempo untuk menghindari keterlambatan.',
                    'Lakukan rekonsiliasi berkala dengan statement supplier/pelanggan.',
                ],
            },
        ],
    },
    {
        id: 'booking',
        title: 'Operasi Booking',
        icon: 'CalendarDaysIcon',
        color: 'from-pink-500 to-rose-600',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Modul booking digunakan untuk reservasi sumber daya berbasis waktu seperti ruangan, kendaraan, atau peralatan.',
            },
            {
                title: 'Menyiapkan Resource Pool',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Booking → Resource Pool',
                        description: 'Resource Pool mengelompokkan jenis sumber daya (misal: tipe kamar, tipe kendaraan).',
                    },
                    {
                        step: 2,
                        title: 'Buat Resource Pool',
                        description: 'Tentukan nama pool dan kapasitas default.',
                    },
                ],
            },
            {
                title: 'Menambahkan Resource Instance',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Booking → Resource Instance',
                        description: 'Resource Instance adalah unit spesifik yang dapat dipesan.',
                    },
                    {
                        step: 2,
                        title: 'Buat Instance',
                        description: 'Pilih pool, beri nama (misal: Kamar 101, Mobil B 1234 AB).',
                    },
                ],
            },
            {
                title: 'Membuat Booking',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Booking → Daftar Booking',
                        description: 'Klik "Buat Baru" untuk reservasi baru.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Pelanggan',
                        description: 'Pilih atau daftarkan pelanggan.',
                    },
                    {
                        step: 3,
                        title: 'Pilih Resource',
                        description: 'Pilih resource pool atau instance spesifik.',
                    },
                    {
                        step: 4,
                        title: 'Tentukan Waktu',
                        description: 'Pilih tanggal/waktu mulai dan selesai.',
                    },
                    {
                        step: 5,
                        title: 'Konfirmasi',
                        description: 'Booking tersimpan, resource terreservasi.',
                    },
                ],
            },
            {
                title: 'Lifecycle Booking',
                description: 'Status booking berubah sesuai tahapan:',
                tips: [
                    'Hold → Booking ditahan sementara (bisa expired).',
                    'Confirmed → Booking dikonfirmasi.',
                    'Checked In → Pelanggan sudah menggunakan resource.',
                    'Checked Out → Penggunaan selesai.',
                    'Completed → Proses selesai, siap diinvoice.',
                    'Cancelled → Booking dibatalkan.',
                ],
            },
        ],
    },
    {
        id: 'costing',
        title: 'Manajemen Biaya',
        icon: 'CalculatorIcon',
        color: 'from-yellow-500 to-orange-500',
        content: [
            {
                title: 'Gambaran Umum',
                description: 'Modul biaya membantu mengalokasikan dan melacak biaya untuk analisis margin dan profitabilitas.',
            },
            {
                title: 'Membuat Pool Biaya',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Manajemen Biaya → Pool Biaya',
                        description: 'Pool biaya mengelompokkan jenis biaya serupa.',
                    },
                    {
                        step: 2,
                        title: 'Buat Pool',
                        description: 'Contoh: Overhead Produksi, Biaya Kendaraan, Biaya Marketing.',
                    },
                ],
            },
            {
                title: 'Mencatat Biaya',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Manajemen Biaya → Catatan Biaya',
                        description: 'Catat biaya-biaya operasional.',
                    },
                    {
                        step: 2,
                        title: 'Input Detail Biaya',
                        description: 'Pilih pool, masukkan jumlah dan deskripsi.',
                    },
                    {
                        step: 3,
                        title: 'Simpan',
                        description: 'Biaya masuk ke pool untuk dialokasikan nanti.',
                    },
                ],
            },
            {
                title: 'Alokasi Biaya',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Manajemen Biaya → Alokasi Biaya',
                        description: 'Distribusikan biaya dari pool ke objek biaya.',
                    },
                    {
                        step: 2,
                        title: 'Pilih Pool Sumber',
                        description: 'Pilih pool biaya yang akan dialokasikan.',
                    },
                    {
                        step: 3,
                        title: 'Tentukan Basis Alokasi',
                        description: 'Pilih metode: berdasarkan revenue, kuantitas, atau manual.',
                    },
                    {
                        step: 4,
                        title: 'Proses Alokasi',
                        description: 'Sistem mendistribusikan biaya sesuai aturan.',
                    },
                ],
            },
            {
                title: 'Cost Items',
                description: 'Cost Items adalah objek yang menerima alokasi biaya, seperti produk, proyek, atau pelanggan. Gunakan untuk analisis profitabilitas detail.',
            },
        ],
    },
    {
        id: 'settings',
        title: 'Pengaturan Sistem',
        icon: 'Cog8ToothIcon',
        color: 'from-slate-500 to-gray-600',
        content: [
            {
                title: 'Pengaturan Perusahaan',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pengaturan → Perusahaan',
                        description: 'Atur informasi perusahaan.',
                    },
                    {
                        step: 2,
                        title: 'Lengkapi Data',
                        description: 'Nama, alamat, telepon, email, NPWP, dan logo.',
                    },
                    {
                        step: 3,
                        title: 'Kelola Cabang',
                        description: 'Tambah dan atur cabang jika multi-lokasi.',
                    },
                ],
            },
            {
                title: 'Manajemen Pengguna',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pengaturan → Hak Akses Pengguna',
                        description: 'Kelola role dan user.',
                    },
                    {
                        step: 2,
                        title: 'Buat Role',
                        description: 'Tentukan permissions untuk setiap role (Admin, Kasir, dll).',
                    },
                    {
                        step: 3,
                        title: 'Tambahkan User',
                        description: 'Daftarkan user dan assign role yang sesuai.',
                    },
                ],
            },
            {
                title: 'Pengaturan Pajak',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pengaturan → Pajak',
                        description: 'Atur jurisdiksi, komponen, dan kategori pajak.',
                    },
                    {
                        step: 2,
                        title: 'Buat Kategori Pajak',
                        description: 'Contoh: PPN 11%, PPh 23, dll.',
                    },
                    {
                        step: 3,
                        title: 'Atur Tax Rules',
                        description: 'Tentukan kapan pajak diterapkan (per produk/partner).',
                    },
                ],
            },
            {
                title: 'Rekening Bank',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pengaturan → Rekening Bank',
                        description: 'Daftarkan rekening bank perusahaan.',
                    },
                    {
                        step: 2,
                        title: 'Input Detail Rekening',
                        description: 'Nama bank, nomor rekening, dan akun terkait.',
                    },
                ],
            },
            {
                title: 'Template Dokumen',
                steps: [
                    {
                        step: 1,
                        title: 'Buka Menu Pengaturan → Template Dokumen',
                        description: 'Sesuaikan format cetak dokumen.',
                    },
                    {
                        step: 2,
                        title: 'Edit Template',
                        description: 'Modifikasi tampilan faktur, PO, dan dokumen lainnya.',
                    },
                ],
            },
            {
                title: 'Konfigurasi GL Event',
                description: 'GL Event Configuration menentukan akun-akun yang digunakan saat transaksi diposting. Pastikan konfigurasi sudah benar sebelum memulai transaksi.',
                tips: [
                    'Atur akun default untuk setiap jenis transaksi.',
                    'Uji dengan transaksi percobaan sebelum go-live.',
                ],
            },
        ],
    },
];

export default helpSections;
