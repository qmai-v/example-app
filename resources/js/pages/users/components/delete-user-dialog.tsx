import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { getInitials } from '@/lib/formatters';
import type { UserRow } from '@/types';
import type { DeleteUserForm, UserStatusFilter } from '../types';

type DeleteUserDialogProps = {
    open: boolean;
    user: UserRow;
    currentPage: number;
    perPage: number;
    search: string;
    status: UserStatusFilter;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteUserDialog({
    open,
    user,
    currentPage,
    perPage,
    search,
    status,
    onOpenChange,
}: DeleteUserDialogProps) {
    const form = useForm<DeleteUserForm>({
        search,
        status,
        page: currentPage,
        per_page: perPage,
    });

    const submitDelete = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.delete(UserController.destroy.url(user.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ConfirmationDialog
            open={open}
            title="Delete user?"
            description={`This will remove ${user.name} (${user.email}) from normal user management results.`}
            confirmLabel="Delete user"
            error={form.errors.search}
            processing={form.processing}
            onOpenChange={onOpenChange}
            onConfirm={submitDelete}
        >
            <div className="rounded-lg border bg-muted/30 p-3">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-full border bg-background text-sm font-semibold text-muted-foreground">
                        {getInitials(user.name)}
                    </div>
                    <div className="min-w-0">
                        <p className="truncate font-medium">{user.name}</p>
                        <p className="truncate text-sm text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>
            </div>
        </ConfirmationDialog>
    );
}
