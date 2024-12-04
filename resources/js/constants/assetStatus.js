export const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Tidak Aktif' },
    { value: 'maintenance', label: 'Perbaikan' },
    { value: 'disposed', label: 'Dibuang' },
    { value: 'sold', label: 'Dijual' },
];

export const getStatusClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        maintenance: 'bg-yellow-100 text-yellow-800',
        disposed: 'bg-red-100 text-red-800',
        sold: 'bg-red-100 text-red-800',
    };
    return classes[status] || classes.inactive;
}; 