// =====================================
// DATA CONTOH SISTEM PENJUALAN TOKO
// =====================================

const pelanggan = [
    { id: 1, nama: "Andi", kota: "Ponorogo" },
    { id: 2, nama: "Budi", kota: "Madiun" },
    { id: 3, nama: "Citra", kota: "Surabaya" },
    { id: 4, nama: "Dewi", kota: "Ponorogo" },
    { id: 5, nama: "Eko", kota: "Magetan" },
    { id: 6, nama: "Fajar", kota: "Madiun" },
    { id: 7, nama: "Gita", kota: "Surabaya" },
    { id: 8, nama: "Hani", kota: "Ponorogo" },
    { id: 9, nama: "Indra", kota: "Ngawi" },
    { id: 10, nama: "Joko", kota: "Magetan" }
];

const kategori = [
    "Makanan",
    "Minuman",
    "Alat Tulis",
    "Elektronik"
];

const produk = [
    { nama: "Roti", kategori: "Makanan", harga: 10000, stok: 50 },
    { nama: "Biskuit", kategori: "Makanan", harga: 15000, stok: 40 },
    { nama: "Air Mineral", kategori: "Minuman", harga: 5000, stok: 100 },
    { nama: "Jus Jeruk", kategori: "Minuman", harga: 12000, stok: 30 },
    { nama: "Buku Tulis", kategori: "Alat Tulis", harga: 6000, stok: 80 },
    { nama: "Pulpen", kategori: "Alat Tulis", harga: 3000, stok: 100 },
    { nama: "Mouse", kategori: "Elektronik", harga: 75000, stok: 20 },
    { nama: "Keyboard", kategori: "Elektronik", harga: 120000, stok: 15 }
];

const transaksi = [
    { id: 1, tanggal: "2026-11-01", pelanggan: "Andi", total: 150000 },
    { id: 2, tanggal: "2026-11-02", pelanggan: "Budi", total: 250000 },
    { id: 3, tanggal: "2026-11-03", pelanggan: "Citra", total: 175000 },
    { id: 4, tanggal: "2026-11-04", pelanggan: "Dewi", total: 300000 },
    { id: 5, tanggal: "2026-11-05", pelanggan: "Eko", total: 125000 },
    { id: 6, tanggal: "2026-11-06", pelanggan: "Fajar", total: 450000 },
    { id: 7, tanggal: "2026-11-07", pelanggan: "Gita", total: 200000 }
];

const aktivitas = [
    { judul: "Transaksi baru ditambahkan", waktu: "Hari ini", simbol: "+" },
    { judul: "Data produk diperbarui", waktu: "Hari ini", simbol: "↻" },
    { judul: "Pelanggan baru terdaftar", waktu: "Kemarin", simbol: "♙" },
    { judul: "Laporan penjualan diperbarui", waktu: "Kemarin", simbol: "▤" }
];

// =====================================
// FORMAT RUPIAH
// =====================================

function rupiah(angka) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0
    }).format(angka);
}

// =====================================
// TANGGAL
// =====================================

const sekarang = new Date();

document.getElementById("currentDate").textContent =
    sekarang.toLocaleDateString("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });

document.getElementById("welcomeDate").textContent =
    sekarang.toLocaleDateString("id-ID", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric"
    });

// =====================================
// RINGKASAN DASHBOARD
// =====================================

const totalPenjualan = transaksi.reduce(
    (total, item) => total + item.total,
    0
);

document.getElementById("totalPenjualan").textContent =
    rupiah(totalPenjualan);

document.getElementById("totalTransaksi").textContent =
    transaksi.length;

document.getElementById("totalProduk").textContent =
    produk.length;

document.getElementById("totalPelanggan").textContent =
    pelanggan.length;

document.getElementById("totalKategori").textContent =
    kategori.length;

const rataRata = transaksi.length > 0
    ? totalPenjualan / transaksi.length
    : 0;

document.getElementById("rataTransaksi").textContent =
    rupiah(rataRata);

// =====================================
// GRAFIK PENJUALAN
// =====================================

const namaBulan = [
    "Jan", "Feb", "Mar", "Apr",
    "Mei", "Jun", "Jul", "Agu",
    "Sep", "Okt", "Nov", "Des"
];

const penjualanBulanan = new Array(12).fill(0);

transaksi.forEach(item => {
    const tanggal = new Date(item.tanggal + "T00:00:00");
    const bulan = tanggal.getMonth();

    penjualanBulanan[bulan] += item.total;
});

const chartElement = document.getElementById("salesChart");

if (chartElement && typeof Chart !== "undefined") {
    new Chart(chartElement, {
        type: "bar",

        data: {
            labels: namaBulan,

            datasets: [{
                label: "Total Penjualan",
                data: penjualanBulanan,
                backgroundColor: "#6d9ff0",
                hoverBackgroundColor: "#3979e8",
                borderRadius: 7,
                borderSkipped: false,
                maxBarThickness: 35
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return rupiah(context.raw);
                        }
                    }
                }
            },

            scales: {
                x: {
                    grid: {
                        display: false
                    },

                    ticks: {
                        color: "#91a0b4",
                        font: {
                            size: 10
                        }
                    }
                },

                y: {
                    beginAtZero: true,

                    grid: {
                        color: "#edf1f7",
                        drawBorder: false
                    },

                    ticks: {
                        color: "#91a0b4",

                        callback: function(value) {
                            return "Rp " +
                                new Intl.NumberFormat("id-ID", {
                                    notation: "compact"
                                }).format(value);
                        }
                    }
                }
            }
        }
    });
}

// =====================================
// DAFTAR STOK PRODUK
// =====================================

const stockList = document.getElementById("stockList");

stockList.innerHTML = produk
    .slice()
    .sort((a, b) => a.stok - b.stok)
    .slice(0, 5)
    .map(item => `
        <div class="stock-item">
            <div class="stock-product">
                <div class="product-symbol">▣</div>
                <div>
                    <strong>${item.nama}</strong>
                    <small>${item.kategori}</small>
                </div>
            </div>
            <span class="stock-number">${item.stok} unit</span>
        </div>
    `)
    .join("");

// =====================================
// TABEL TRANSAKSI
// =====================================

const transactionTable =
    document.getElementById("transactionTable");

document.getElementById("transactionCount").textContent =
    transaksi.length + " Data";

transactionTable.innerHTML = transaksi
    .slice()
    .reverse()
    .slice(0, 7)
    .map(item => {
        const tanggal = new Date(item.tanggal + "T00:00:00");

        const tanggalFormat = tanggal.toLocaleDateString(
            "id-ID",
            {
                day: "2-digit",
                month: "short",
                year: "numeric"
            }
        );

        return `
            <tr>
                <td>
                    <span class="transaction-id">
                        #TRX-${String(item.id).padStart(4, "0")}
                    </span>
                </td>

                <td>${tanggalFormat}</td>

                <td>
                    <div class="customer-name">
                        <div class="customer-avatar">
                            ${item.pelanggan.charAt(0)}
                        </div>
                        ${item.pelanggan}
                    </div>
                </td>

                <td class="amount">${rupiah(item.total)}</td>

                <td>
                    <span class="status-badge">Tercatat</span>
                </td>
            </tr>
        `;
    })
    .join("");

// =====================================
// DAFTAR PRODUK
// =====================================

document.getElementById("productList").innerHTML =
    produk.map(item => `
        <div class="simple-item">
            <span>${item.nama}</span>
            <span>${rupiah(item.harga)}</span>
        </div>
    `).join("");

// =====================================
// DAFTAR KATEGORI
// =====================================

document.getElementById("categoryList").innerHTML =
    kategori.map((item, index) => `
        <div class="category-item">
            <span>
                <span class="product-symbol">${index + 1}</span>
                ${item}
            </span>
            <span>
                ${produk.filter(p => p.kategori === item).length} Produk
            </span>
        </div>
    `).join("");

// =====================================
// DAFTAR PELANGGAN
// =====================================

document.getElementById("customerList").innerHTML =
    pelanggan.map(item => `
        <div class="customer-card">
            <div class="customer-avatar">
                ${item.nama.charAt(0)}
            </div>
            <div>
                <strong>${item.nama}</strong>
                <small>${item.kota}</small>
            </div>
        </div>
    `).join("");

// =====================================
// RIWAYAT AKTIVITAS
// =====================================

document.getElementById("activityList").innerHTML =
    aktivitas.map(item => `
        <div class="activity-item">
            <div class="activity-icon">${item.simbol}</div>
            <div>
                <strong>${item.judul}</strong>
                <small>${item.waktu}</small>
            </div>
        </div>
    `).join("");

// =====================================
// SIDEBAR RESPONSIVE
// =====================================

const menuToggle = document.getElementById("menuToggle");
const sidebar = document.getElementById("sidebar");

menuToggle.addEventListener("click", function() {
    sidebar.classList.toggle("show");
});

document.querySelectorAll(".nav-link").forEach(link => {
    link.addEventListener("click", function() {
        document.querySelectorAll(".nav-link").forEach(item => {
            item.classList.remove("active");
        });

        this.classList.add("active");

        if (window.innerWidth <= 750) {
            sidebar.classList.remove("show");
        }
    });
});