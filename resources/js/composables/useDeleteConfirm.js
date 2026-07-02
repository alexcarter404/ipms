import { useConfirm } from 'primevue/useconfirm';

/**
 * Confirmation prompt for destructive actions, rendered by the shared
 * <ConfirmDialog /> in the layout. Usage:
 *
 *   const confirmDelete = useDeleteConfirm();
 *   confirmDelete('Delete task “X”?', () => router.delete(...));
 */
export function useDeleteConfirm() {
    const confirm = useConfirm();

    return (message, accept, acceptLabel = 'Delete') =>
        confirm.require({
            message,
            header: 'Please confirm',
            rejectProps: {
                label: 'Cancel',
                severity: 'secondary',
                outlined: true,
                size: 'small',
            },
            acceptProps: {
                label: acceptLabel,
                severity: 'danger',
                size: 'small',
            },
            accept,
        });
}
