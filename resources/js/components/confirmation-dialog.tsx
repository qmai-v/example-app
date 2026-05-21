import type { ComponentProps, FormEvent, ReactNode } from 'react';
import AppDialog from '@/components/app-dialog';
import InputError from '@/components/input-error';
import type { Button } from '@/components/ui/button';

type ConfirmationDialogProps = {
    open: boolean;
    title: ReactNode;
    description: ReactNode;
    confirmLabel: string;
    onOpenChange: (open: boolean) => void;
    onConfirm: (event: FormEvent<HTMLFormElement>) => void;
    cancelLabel?: string;
    children?: ReactNode;
    error?: string;
    processing?: boolean;
    confirmVariant?: ComponentProps<typeof Button>['variant'];
};

export default function ConfirmationDialog({
    open,
    title,
    description,
    confirmLabel,
    onOpenChange,
    onConfirm,
    cancelLabel,
    children,
    error,
    processing = false,
    confirmVariant = 'destructive',
}: ConfirmationDialogProps) {
    return (
        <AppDialog
            open={open}
            title={title}
            description={description}
            submitLabel={confirmLabel}
            submitVariant={confirmVariant}
            cancelLabel={cancelLabel}
            processing={processing}
            formClassName="space-y-4"
            onOpenChange={onOpenChange}
            onSubmit={onConfirm}
        >
            {children}
            <InputError message={error} />
        </AppDialog>
    );
}
