/**
 * Onboarding step definitions
 * Each step contains content for the onboarding tour modal
 */

export const onboardingSteps = [
    {
        id: 'welcome',
        title: 'Selamat Datang! 👋',
        subtitle: 'Mari Mulai Perjalanan Anda',
        description: 'Terima kasih telah bergabung dengan sistem akuntansi kami. Panduan singkat ini akan membantu Anda mengenal fitur-fitur utama aplikasi.',
        icon: 'SparklesIcon',
        color: 'from-blue-500 to-indigo-600',
        features: [
            { icon: 'ChartBarIcon', text: 'Dashboard interaktif dengan KPI bisnis' },
            { icon: 'DocumentTextIcon', text: 'Manajemen dokumen penjualan & pembelian' },
            { icon: 'CalculatorIcon', text: 'Sistem akuntansi terintegrasi' },
        ],
    },
    {
        id: 'dashboard',
        title: 'Dashboard Overview 📊',
        subtitle: 'Pantau Bisnis Anda',
        description: 'Dashboard menampilkan ringkasan KPI bisnis Anda termasuk penjualan, pembelian, piutang, dan hutang dalam satu tampilan.',
        icon: 'ChartBarIcon',
        color: 'from-emerald-500 to-teal-600',
        features: [
            { icon: 'CurrencyDollarIcon', text: 'Total penjualan dan pembelian' },
            { icon: 'ArrowTrendingUpIcon', text: 'Grafik tren bulanan' },
            { icon: 'DocumentDuplicateIcon', text: 'Dokumen terbaru' },
        ],
    },
    {
        id: 'navigation',
        title: 'Navigasi Sidebar 🧭',
        subtitle: 'Akses Semua Fitur dengan Mudah',
        description: 'Gunakan sidebar di sebelah kiri untuk mengakses semua modul. Anda dapat memperkecil sidebar untuk tampilan yang lebih luas.',
        icon: 'Bars3Icon',
        color: 'from-purple-500 to-violet-600',
        features: [
            { icon: 'ChevronDoubleLeftIcon', text: 'Klik ikon panah untuk memperkecil sidebar' },
            { icon: 'MagnifyingGlassIcon', text: 'Sub-menu terorganisir berdasarkan modul' },
            { icon: 'UserCircleIcon', text: 'Menu profil di kanan atas' },
        ],
    },
    {
        id: 'sales',
        title: 'Modul Penjualan 🛒',
        subtitle: 'Kelola Penjualan Anda',
        description: 'Buat dan kelola Sales Order, Delivery Order, dan Faktur Penjualan. Lacak status pengiriman dan pembayaran pelanggan.',
        icon: 'ShoppingCartIcon',
        color: 'from-green-500 to-emerald-600',
        features: [
            { icon: 'DocumentTextIcon', text: 'Sales Order - Buat pesanan penjualan' },
            { icon: 'TruckIcon', text: 'Delivery Order - Kelola pengiriman' },
            { icon: 'ReceiptRefundIcon', text: 'Faktur Penjualan - Tagih pelanggan' },
        ],
    },
    {
        id: 'purchase',
        title: 'Modul Pembelian 📦',
        subtitle: 'Kelola Pembelian Anda',
        description: 'Buat Purchase Order, terima barang dengan Goods Receipt, dan kelola Faktur Pembelian dari supplier.',
        icon: 'ClipboardDocumentListIcon',
        color: 'from-orange-500 to-amber-600',
        features: [
            { icon: 'DocumentPlusIcon', text: 'Purchase Order - Pesan ke supplier' },
            { icon: 'InboxArrowDownIcon', text: 'Goods Receipt - Terima barang' },
            { icon: 'BanknotesIcon', text: 'Faktur Pembelian - Catat hutang' },
        ],
    },
    {
        id: 'accounting',
        title: 'Modul Akuntansi 📒',
        subtitle: 'Kelola Keuangan Bisnis',
        description: 'Akses jurnal umum, buku besar, kas & bank, dan laporan keuangan seperti Neraca dan Laporan Laba Rugi.',
        icon: 'CalculatorIcon',
        color: 'from-blue-600 to-cyan-600',
        features: [
            { icon: 'BookOpenIcon', text: 'Jurnal Umum - Catat transaksi' },
            { icon: 'TableCellsIcon', text: 'Buku Besar - Lihat saldo akun' },
            { icon: 'DocumentChartBarIcon', text: 'Laporan Keuangan - Neraca & Laba Rugi' },
        ],
    },
    {
        id: 'products',
        title: 'Katalog Produk 🏷️',
        subtitle: 'Kelola Produk dan Harga',
        description: 'Tambahkan produk, atur varian, kelola kategori, dan buat daftar harga untuk pelanggan yang berbeda.',
        icon: 'CubeIcon',
        color: 'from-pink-500 to-rose-600',
        features: [
            { icon: 'TagIcon', text: 'Produk & Varian - Kelola katalog' },
            { icon: 'FolderIcon', text: 'Kategori Produk - Organisasi produk' },
            { icon: 'CurrencyDollarIcon', text: 'Daftar Harga - Atur pricing' },
        ],
    },
    {
        id: 'costing',
        title: 'Modul Biaya 💰',
        subtitle: 'Analisis Biaya Produksi',
        description: 'Kelola cost pool, entri biaya, dan alokasi biaya untuk memahami struktur biaya produk Anda.',
        icon: 'ScaleIcon',
        color: 'from-yellow-500 to-orange-500',
        features: [
            { icon: 'CircleStackIcon', text: 'Cost Pool - Kelompokkan biaya' },
            { icon: 'PlusCircleIcon', text: 'Cost Entry - Catat biaya' },
            { icon: 'ArrowsPointingOutIcon', text: 'Alokasi Biaya - Distribusi ke produk' },
        ],
    },
    {
        id: 'reports',
        title: 'Laporan Bisnis 📈',
        subtitle: 'Analisis Data Anda',
        description: 'Akses berbagai laporan penjualan, pembelian, stok, dan keuangan untuk pengambilan keputusan bisnis.',
        icon: 'ChartPieIcon',
        color: 'from-indigo-500 to-purple-600',
        features: [
            { icon: 'DocumentArrowDownIcon', text: 'Export ke Excel, CSV, dan PDF' },
            { icon: 'FunnelIcon', text: 'Filter berdasarkan tanggal dan kategori' },
            { icon: 'EyeIcon', text: 'Preview sebelum export' },
        ],
    },
    {
        id: 'settings',
        title: 'Pengaturan ⚙️',
        subtitle: 'Sesuaikan Preferensi Anda',
        description: 'Atur preferensi dashboard, kelola pengguna, dan konfigurasi sistem sesuai kebutuhan bisnis Anda.',
        icon: 'Cog8ToothIcon',
        color: 'from-slate-500 to-gray-600',
        features: [
            { icon: 'AdjustmentsHorizontalIcon', text: 'Pengaturan Dashboard' },
            { icon: 'UsersIcon', text: 'Manajemen Pengguna & Role' },
            { icon: 'BuildingOfficeIcon', text: 'Pengaturan Perusahaan' },
        ],
    },
    {
        id: 'getstarted',
        title: 'Siap Memulai! 🚀',
        subtitle: 'Anda Sudah Siap',
        description: 'Selamat! Anda sudah mengenal fitur-fitur utama aplikasi. Sekarang saatnya mulai mengelola bisnis Anda.',
        icon: 'RocketLaunchIcon',
        color: 'from-violet-500 to-fuchsia-600',
        features: [
            { icon: 'PlusIcon', text: 'Mulai dengan membuat Sales Order pertama' },
            { icon: 'UserGroupIcon', text: 'Tambahkan data partner (pelanggan/supplier)' },
            { icon: 'QuestionMarkCircleIcon', text: 'Hubungi support jika butuh bantuan' },
        ],
        isLast: true,
    },
];

export default onboardingSteps;
