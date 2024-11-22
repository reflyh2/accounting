export function formatNumber(value, decimals = 2, preserveDecimalInput = false) {
    if (value === '' || value === undefined || value === null) return '';
    
    let [integerPart, decimalPart] = value.toString().split('.');
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    if (decimalPart !== undefined) {
        if (preserveDecimalInput) {
            return `${integerPart},${decimalPart}`;
        } else {
            return `${integerPart},${decimalPart.slice(0, decimals).padEnd(decimals, '0')}`;
        }
    }
    
    return decimals > 0 ? `${integerPart},${'0'.repeat(decimals)}` : integerPart;
}

export function unformatNumber(value) {
    if (!value) return '';
    return value.replace(/\./g, '').replace(',', '.');
}

export function terbilang(value) {
    const satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    
    function convert(num) {
        if (num < 12) {
            return satuan[num];
        } else if (num < 20) {
            return satuan[num % 10] + ' belas';
        } else if (num < 100) {
            return satuan[Math.floor(num / 10)] + ' puluh ' + convert(num % 10);
        } else if (num < 200) {
            return 'seratus ' + convert(num % 100);
        } else if (num < 1000) {
            return satuan[Math.floor(num / 100)] + ' ratus ' + convert(num % 100);
        } else if (num < 2000) {
            return 'seribu ' + convert(num % 1000);
        } else if (num < 1000000) {
            return convert(Math.floor(num / 1000)) + ' ribu ' + convert(num % 1000);
        } else if (num < 1000000000) {
            return convert(Math.floor(num / 1000000)) + ' juta ' + convert(num % 1000000);
        } else if (num < 1000000000000) {
            return convert(Math.floor(num / 1000000000)) + ' milyar ' + convert(num % 1000000000);
        } else if (num < 1000000000000000) {
            return convert(Math.floor(num / 1000000000000)) + ' trilyun ' + convert(num % 1000000000000);
        }
    }

    return convert(Math.floor(value)).trim();
}