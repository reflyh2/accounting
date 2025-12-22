import { getDocumentStatusMeta } from '@/constants/documentStatuses';

/**
 * Renders a status pill as HTML for use in columnRenderers.
 * Use this when you can't use the DocumentStatusPill Vue component.
 */
export function renderStatusPillHtml(documentKind, status, size = 'sm') {
    const meta = getDocumentStatusMeta(documentKind, status);
    const sizeClasses = size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-3 py-1';

    return `
        <div class="flex items-center gap-2 shrink">
            <span class="inline-flex items-center gap-2 rounded-full font-semibold tracking-tight ${meta.classes} ${sizeClasses}">
                <span class="h-2 w-2 rounded-full ${meta.dotClass}"></span>
                <span class="whitespace-nowrap">${meta.label}</span>
            </span>
        </div>
    `;
}
