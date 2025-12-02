const pill = (label, classes, dotClass) => ({
  label,
  classes,
  dotClass,
});

export const DocumentStatusKind = Object.freeze({
  PURCHASE_ORDER: 'purchaseOrder',
  SALES_ORDER: 'salesOrder',
  WORK_ORDER: 'workOrder',
  GOODS_RECEIPT: 'goodsReceipt',
  DELIVERY: 'delivery',
  INVOICE: 'invoice',
});

const tokens = {
  draft: pill('Draft', 'border border-slate-200 bg-slate-50 text-slate-700', 'bg-slate-400'),
  approved: pill('Approved', 'border border-blue-200 bg-blue-50 text-blue-700', 'bg-blue-500'),
  sent: pill('Sent', 'border border-indigo-200 bg-indigo-50 text-indigo-700', 'bg-indigo-500'),
  partiallyReceived: pill('Partially Received', 'border border-amber-200 bg-amber-50 text-amber-700', 'bg-amber-500'),
  received: pill('Received', 'border border-emerald-200 bg-emerald-50 text-emerald-700', 'bg-emerald-500'),
  closed: pill('Closed', 'border border-zinc-200 bg-zinc-50 text-zinc-700', 'bg-zinc-500'),
  canceled: pill('Canceled', 'border border-rose-200 bg-rose-50 text-rose-700', 'bg-rose-500'),
  confirmed: pill('Confirmed', 'border border-blue-200 bg-blue-50 text-blue-700', 'bg-blue-500'),
  partiallyDelivered: pill('Partially Delivered', 'border border-amber-200 bg-amber-50 text-amber-700', 'bg-amber-500'),
  delivered: pill('Delivered', 'border border-emerald-200 bg-emerald-50 text-emerald-700', 'bg-emerald-500'),
  released: pill('Released', 'border border-cyan-200 bg-cyan-50 text-cyan-700', 'bg-cyan-500'),
  inProgress: pill('In Progress', 'border border-sky-200 bg-sky-50 text-sky-700', 'bg-sky-500'),
  completed: pill('Completed', 'border border-emerald-200 bg-emerald-50 text-emerald-700', 'bg-emerald-500'),
  posted: pill('Posted', 'border border-sky-200 bg-sky-50 text-sky-700', 'bg-sky-500'),
  partiallyPaid: pill('Partially Paid', 'border border-amber-200 bg-amber-50 text-amber-700', 'bg-amber-500'),
  paid: pill('Paid', 'border border-green-200 bg-green-50 text-green-700', 'bg-green-500'),
  quote: pill('Quote', 'border border-violet-200 bg-violet-50 text-violet-700', 'bg-violet-500'),
};

export const documentStatusCatalog = {
  [DocumentStatusKind.PURCHASE_ORDER]: {
    draft: tokens.draft,
    approved: tokens.approved,
    sent: tokens.sent,
    partially_received: tokens.partiallyReceived,
    received: tokens.received,
    closed: tokens.closed,
    canceled: tokens.canceled,
  },
  [DocumentStatusKind.SALES_ORDER]: {
    draft: tokens.draft,
    quote: tokens.quote,
    confirmed: tokens.confirmed,
    partially_delivered: tokens.partiallyDelivered,
    delivered: tokens.delivered,
    closed: tokens.closed,
    canceled: tokens.canceled,
  },
  [DocumentStatusKind.WORK_ORDER]: {
    draft: tokens.draft,
    released: tokens.released,
    in_progress: tokens.inProgress,
    completed: tokens.completed,
    closed: tokens.closed,
    canceled: tokens.canceled,
  },
  [DocumentStatusKind.GOODS_RECEIPT]: {
    draft: tokens.draft,
    posted: tokens.posted,
  },
  [DocumentStatusKind.DELIVERY]: {
    draft: tokens.draft,
    posted: tokens.posted,
  },
  [DocumentStatusKind.INVOICE]: {
    draft: tokens.draft,
    posted: tokens.posted,
    partially_paid: tokens.partiallyPaid,
    paid: tokens.paid,
    canceled: tokens.canceled,
  },
};

const catalogLookup = Object.entries(documentStatusCatalog).reduce(
  (carry, [key, value]) => {
    const normalizedKey = key.replace(/[_\s-]/g, '').toLowerCase();
    carry[key] = value;
    carry[normalizedKey] = value;
    return carry;
  },
  {},
);

const normalizeStatus = (value) => {
  if (value === undefined || value === null) {
    return '';
  }
  return value.toString().toLowerCase();
};

const humanize = (value) => {
  if (!value) {
    return 'Unknown';
  }
  return value
    .split('_')
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ');
};

export const getDocumentStatusMeta = (kind, status) => {
  const rawKind = kind ?? '';
  const normalizedStatus = normalizeStatus(status);
  const normalizedKind = rawKind.toString().replace(/[_\s-]/g, '').toLowerCase();

  const metaSource =
    catalogLookup[rawKind] ??
    catalogLookup[rawKind?.toString()] ??
    catalogLookup[normalizedKind];

  const meta = metaSource?.[normalizedStatus];

  if (!meta) {
    return {
      value: normalizedStatus,
      label: humanize(normalizedStatus),
      classes: 'border border-gray-200 bg-gray-50 text-gray-600',
      dotClass: 'bg-gray-400',
    };
  }

  return {
    value: normalizedStatus,
    ...meta,
  };
};

