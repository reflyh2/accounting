export const getStatusClass = (status) => {
   const classes = {
       active: 'bg-green-100 text-green-800',
       inactive: 'bg-red-100 text-red-800',
       suspended: 'bg-yellow-100 text-yellow-800',
   };
   return classes[status] || classes.inactive;
};