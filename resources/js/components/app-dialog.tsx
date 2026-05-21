import type { ComponentProps, FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type AppDialogProps = {
    open: boolean;
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    submitLabel: string;
    onOpenChange: (open: boolean) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    cancelLabel?: string;
    contentClassName?: string;
    closeOnInteractOutside?: boolean;
    formClassName?: string;
    processing?: boolean;
    submitVariant?: ComponentProps<typeof Button>['variant'];
};

export default function AppDialog({
    open,
    title,
    description,
    children,
    submitLabel,
    onOpenChange,
    onSubmit,
    cancelLabel = 'Cancel',
    contentClassName,
    closeOnInteractOutside = true,
    formClassName = 'space-y-5',
    processing = false,
    submitVariant = 'default',
}: AppDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={contentClassName}
                onInteractOutside={(event) => {
                    if (!closeOnInteractOutside) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                <form onSubmit={onSubmit} className={formClassName}>
                    {children}

                    <DialogFooter className="pt-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {cancelLabel}
                            </Button>
                        </DialogClose>
                        <Button variant={submitVariant} loading={processing}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
